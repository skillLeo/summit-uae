<?php

namespace Tests\Feature\Design\D2;

use Tests\TestCase;

/**
 * The Lighthouse gate (scripts/qa/lighthouse-d2.mjs, brief §7.2; owner: platform). Round-2 QA: the run died after
 * three of eight pages, because its gzip proxy piped a late upstream answer into a response the browser had already
 * closed (Node 26 throws ERR_STREAM_UNABLE_TO_PIPE synchronously), and the crash skipped the clean-up, so its php -S
 * kept 127.0.0.1:8043 and the next run refused to start. The behaviour itself is tested by
 * `node --test scripts/qa/lighthouse-d2.test.mjs` (an abandoned request, a crash and three signals, against a real
 * php -S); this test keeps those protections and that self-test in place.
 */
final class LighthouseGateScriptTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(base_path('scripts/qa/lighthouse-d2.mjs'));
    }

    public function test_the_proxy_never_pipes_into_a_response_the_browser_closed(): void
    {
        $script = $this->script();
        $proxy = substr($script, (int) strpos($script, 'export function startProxy('), 3200);

        $this->assertStringContainsString('const browserLeft = () => res.destroyed || res.writableEnded;', $proxy);
        // A late answer for a closed response is dropped before anything is written or piped.
        $this->assertMatchesRegularExpression('/\(up\) => \{\s*if \(browserLeft\(\)\) \{\s*up\.destroy\(\);\s*return;\s*\}/', $proxy);
        $this->assertMatchesRegularExpression('/try \{\s*res\.writeHead\(up\.statusCode[\s\S]*?pipeline\(up, res, \(\) => \{\}\);\s*\} catch \{/', $proxy);
        // The browser leaving aborts the upstream request too; its error is then nobody's to answer.
        $this->assertStringContainsString("res.on('close', () => {", $proxy);
        $this->assertStringContainsString('if (!res.writableFinished) forward.destroy();', $proxy);
        $this->assertStringContainsString('if (browserLeft()) return;', $proxy);
        // Never a text error appended to a body already started (gzip).
        $this->assertMatchesRegularExpression('/if \(res\.headersSent\) \{\s*res\.destroy\(\);/', $proxy);
    }

    public function test_the_server_and_chrome_never_outlive_the_script(): void
    {
        $script = $this->script();

        $this->assertStringContainsString("process.on('exit', stopAllSync);", $script);
        $this->assertStringContainsString("process.on('uncaughtException', (error) => {", $script);
        $this->assertStringContainsString("process.on('unhandledRejection', (reason) => {", $script);
        $this->assertStringContainsString("for (const [signal, code] of [['SIGINT', 130], ['SIGTERM', 143], ['SIGHUP', 129]]) process.once(signal, () => process.exit(code));", $script);

        // The synchronous stop reaches the whole process group (php -S and its workers): SIGTERM, then SIGKILL.
        $stop = substr($script, (int) strpos($script, 'export function stopServerSync('), 700);
        $this->assertStringContainsString('process.kill(-server.child.pid, signal);', $stop);
        $this->assertStringContainsString('Atomics.wait(', $stop);
        $this->assertStringContainsString("group('SIGKILL');", $stop);
        $this->assertStringContainsString('detached: true', $script, 'its own process group, so one signal reaches the workers');

        // Tracked from the moment it exists, and the guard is installed before the server starts.
        $this->assertStringContainsString('live.server = server;', $script);
        $this->assertStringContainsString('live.chrome = chrome;', $script);
        $main = substr($script, (int) strpos($script, 'async function main()'));
        $this->assertLessThan(strpos($main, 'await startServer(opts.port)'), strpos($main, 'guardProcess();'));
        // The port in use names its listeners, so a server left by kill -9 can be found.
        $this->assertStringContainsString("spawnSync('lsof', ['-nP', `-iTCP:\${port}`, '-sTCP:LISTEN', '-t']", $script);
    }

    public function test_the_self_test_covers_an_abandoned_request_a_crash_and_the_signals(): void
    {
        $script = $this->script();
        $test = (string) file_get_contents(base_path('scripts/qa/lighthouse-d2.test.mjs'));

        // Importing the script for the self-test never starts a run.
        $this->assertStringContainsString('if (invokedDirectly()) {', $script);
        $this->assertStringContainsString("import { startProxy } from './lighthouse-d2.mjs';", $test);
        $this->assertStringContainsString("abandon(proxy.port, '/en/late', { abortAfter: 50 })", $test);
        $this->assertStringContainsString("abandon(proxy.port, '/en/stream', { afterFirstChunk: true })", $test);
        $this->assertStringContainsString("serverIn('crash')", $test);
        $this->assertStringContainsString("[['SIGINT', 130], ['SIGTERM', 143], ['SIGHUP', 129]]", $test);
        $this->assertStringContainsString("assert.equal(groupAlive(run.pid), false, 'no php -S process (master or worker) is left');", $test);
        $this->assertStringContainsString('node --test scripts/qa/lighthouse-d2.test.mjs', $script, 'the script names its self-test');
    }
}
