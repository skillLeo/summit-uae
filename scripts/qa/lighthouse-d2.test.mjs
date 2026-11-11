/**
 * Self-test of scripts/qa/lighthouse-d2.mjs (no Lighthouse run, no build):
 *
 *   node --test scripts/qa/lighthouse-d2.test.mjs
 *
 * 1. The gzip proxy survives the browser giving up on a request (before the answer and mid-body) and aborts
 *    the upstream request with it: a late answer used to be piped into the closed response, which Node 26
 *    throws on synchronously (ERR_STREAM_UNABLE_TO_PIPE), killing the run after a few pages.
 * 2. A normal request through the proxy: gzip, X-Summit-QA: 1 and the summit_design=2 cookie.
 * 3. The php -S server never outlives the script: a crash (uncaught exception) and SIGHUP both end its
 *    process group, so the port is free for the next run. Each case starts the app on a free local port.
 */
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import http from 'node:http';
import net from 'node:net';
import { after, test } from 'node:test';
import { pathToFileURL } from 'node:url';
import { gunzipSync } from 'node:zlib';
import { ROOT } from '../../qa/lib/config.mjs';
import { startProxy } from './lighthouse-d2.mjs';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const listen = (server) => new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve(server.address().port)));
const closing = [];
after(async () => {
    for (const close of closing.reverse()) await close();
});

/** An upstream that answers after `delay` ms (or streams `chunks` slowly) and records how each request ended. */
async function upstream({ delay = 0, chunks = null, type = 'text/html; charset=utf-8' } = {}) {
    const seen = [];
    const server = http.createServer((req, res) => {
        const entry = { url: req.url, headers: req.headers, finished: false, closedEarly: false };
        seen.push(entry);
        res.on('close', () => {
            entry.finished = res.writableFinished;
            entry.closedEarly = !res.writableFinished;
        });
        setTimeout(async () => {
            if (res.destroyed) return;
            res.writeHead(200, { 'content-type': type });
            if (!chunks) {
                res.end('<p>answer</p>'.repeat(50));
                return;
            }
            for (const chunk of chunks) {
                if (res.destroyed) return;
                res.write(chunk);
                await sleep(150);
            }
            if (!res.destroyed) res.end();
        }, delay);
    });
    const port = await listen(server);
    closing.push(() => new Promise((resolve) => server.close(resolve)));
    return { origin: new URL(`http://127.0.0.1:${port}`), seen, server };
}

async function proxyFor(origin) {
    const proxy = await startProxy(origin);
    closing.push(async () => {
        proxy.agent.destroy();
        await new Promise((resolve) => proxy.server.close(resolve));
    });
    return proxy;
}

/** A browser request that gives up after `abortAfter` ms (or after its first body chunk). */
function abandon(port, path, { abortAfter = null, afterFirstChunk = false } = {}) {
    return new Promise((resolve) => {
        const controller = new AbortController();
        const request = http.get({ host: '127.0.0.1', port, path, signal: controller.signal, headers: { 'accept-encoding': 'gzip' } }, (response) => {
            response.on('error', () => {});
            response.once('data', () => {
                if (afterFirstChunk) controller.abort();
            });
        });
        request.on('error', () => {});
        if (abortAfter !== null) setTimeout(() => controller.abort(), abortAfter);
        setTimeout(resolve, 900);
    });
}

test('a request the browser abandons before the answer is aborted upstream, and nothing crashes', async () => {
    const up = await upstream({ delay: 300 });
    const proxy = await proxyFor(up.origin);
    const crashes = [];
    const onCrash = (error) => crashes.push(error);
    process.on('uncaughtException', onCrash);
    try {
        await abandon(proxy.port, '/en/late', { abortAfter: 50 });
    } finally {
        process.off('uncaughtException', onCrash);
    }
    assert.deepEqual(crashes, []);
    assert.equal(up.seen.length, 1);
    assert.equal(up.seen[0].closedEarly, true, 'the proxy aborts the upstream request when the browser leaves');
});

test('a browser that leaves mid-body ends both sides without a crash', async () => {
    const up = await upstream({ chunks: ['<p>one</p>', '<p>two</p>', '<p>three</p>', '<p>four</p>'] });
    const proxy = await proxyFor(up.origin);
    const crashes = [];
    const onCrash = (error) => crashes.push(error);
    process.on('uncaughtException', onCrash);
    try {
        await abandon(proxy.port, '/en/stream', { afterFirstChunk: true });
    } finally {
        process.off('uncaughtException', onCrash);
    }
    assert.deepEqual(crashes, []);
    assert.equal(up.seen.length, 1);
    assert.equal(up.seen[0].closedEarly, true, 'the upstream response is cut when the browser leaves mid-body');
});

test('a normal request is gzipped and carries X-Summit-QA and the Design 2 cookie', async () => {
    const up = await upstream();
    const proxy = await proxyFor(up.origin);
    const { status, headers, body } = await new Promise((resolve, reject) => {
        http.get({ host: '127.0.0.1', port: proxy.port, path: '/en/', headers: { 'accept-encoding': 'gzip, br', cookie: 'summit_qa=1' } }, (response) => {
            const parts = [];
            response.on('data', (d) => parts.push(d));
            response.on('end', () => resolve({ status: response.statusCode, headers: response.headers, body: Buffer.concat(parts) }));
        }).on('error', reject);
    });
    assert.equal(status, 200);
    assert.equal(headers['content-encoding'], 'gzip');
    assert.match(gunzipSync(body).toString('utf8'), /^<p>answer<\/p>/);
    const sent = up.seen[0].headers;
    assert.equal(sent['x-summit-qa'], '1');
    assert.equal(sent['accept-encoding'], undefined, 'php -S is asked for an uncompressed body');
    assert.match(sent.cookie, /(^|;\s*)summit_qa=1(;|$)/);
    assert.match(sent.cookie, /(^|;\s*)summit_design=2(;|$)/);
});

// ------------------------------------------------------------------------------------ the server never outlives a crash
async function freePort() {
    const server = net.createServer();
    const port = await listen(server);
    await new Promise((resolve) => server.close(resolve));
    return port;
}

async function answers(origin) {
    try {
        const res = await fetch(`${origin}/up`, { signal: AbortSignal.timeout(1500) });
        await res.arrayBuffer().catch(() => null);
        return res.status;
    } catch {
        return 0;
    }
}

const groupAlive = (pid) => {
    try {
        process.kill(-pid, 0);
        return true;
    } catch {
        return false;
    }
};

/**
 * Starts the app with the script's own guardProcess() and startServer() in a child process; once the app
 * answers, the child crashes (an exception thrown in an event callback, like the proxy's old pipe error) or
 * gets a signal. Returns the child's exit and the server's process group id.
 */
async function serverIn(ending) {
    const port = await freePort();
    const moduleUrl = pathToFileURL(`${ROOT}/scripts/qa/lighthouse-d2.mjs`).href;
    const code = `
        import { guardProcess, startServer } from ${JSON.stringify(moduleUrl)};
        guardProcess();
        const server = await startServer(${port});
        if (!server) process.exit(3);
        console.log(JSON.stringify({ pid: server.child.pid, origin: server.origin }));
        process.stdin.once('data', () => {
            throw new Error('simulated crash after the server started');
        });
    `;
    const proc = spawn(process.execPath, ['--input-type=module', '-e', code], { cwd: ROOT, stdio: ['pipe', 'pipe', 'pipe'] });
    const exited = new Promise((resolve) => proc.on('exit', (exitCode, signal) => resolve({ exitCode, signal })));
    let out = '';
    let err = '';
    proc.stderr.on('data', (d) => (err += d));
    try {
        const started = await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error(`the server did not start: ${err}`)), 30_000);
            proc.stdout.on('data', (d) => {
                out += d;
                const line = out.split('\n').find((l) => l.startsWith('{'));
                if (line) {
                    clearTimeout(timer);
                    resolve(JSON.parse(line));
                }
            });
            proc.once('exit', (exitCode) => {
                clearTimeout(timer);
                reject(new Error(`the child exited (${exitCode}) before the server started: ${err}`));
            });
        });
        assert.equal(await answers(started.origin), 200, 'the app answers on its port while the script runs');
        assert.equal(groupAlive(started.pid), true);
        if (ending === 'crash') proc.stdin.write('crash\n');
        else proc.kill(ending);
        const result = await exited;
        for (let i = 0; i < 20 && groupAlive(started.pid); i += 1) await sleep(100);
        return { ...started, ...result, stderr: err };
    } finally {
        if (proc.exitCode === null && proc.signalCode === null) {
            proc.kill('SIGTERM'); // a failed assertion: still never leave the server behind
            await exited;
        }
    }
}

test('a crash stops the php -S process group: the port is free for the next run', async () => {
    const run = await serverIn('crash');
    assert.equal(run.exitCode, 2);
    assert.match(run.stderr, /lighthouse-d2 crashed: Error: simulated crash/);
    assert.equal(groupAlive(run.pid), false, 'no php -S process (master or worker) is left');
    assert.equal(await answers(run.origin), 0, 'nothing answers on the port any more');
});

for (const [signal, code] of [['SIGINT', 130], ['SIGTERM', 143], ['SIGHUP', 129]]) {
    test(`${signal} stops the php -S process group too`, async () => {
        const run = await serverIn(signal);
        assert.equal(run.exitCode, code);
        assert.equal(groupAlive(run.pid), false, 'no php -S process (master or worker) is left');
        assert.equal(await answers(run.origin), 0, 'nothing answers on the port any more');
    });
}
