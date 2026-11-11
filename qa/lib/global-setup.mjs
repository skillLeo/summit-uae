/**
 * Preflight for playwright.d2.config.mjs: note when this invocation started (for the verdict), fail fast when
 * nothing answers at QA_BASE_URL or when it does not render Design 2 for the QA cookies, then record the run
 * (qa/shots/<date>/run.json).
 */
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { BASE_URL, ROOT, RUN, runDir } from './config.mjs';
import { STARTED_ENV } from './verdict.mjs';

export default async function globalSetup() {
    // First, also without the preflight: the verdict (global teardown, same process) judges only the captures
    // written from now on.
    process.env[STARTED_ENV] = new Date().toISOString();
    if (process.env.QA_SKIP_PREFLIGHT) return;
    let html = '';
    try {
        const res = await fetch(`${BASE_URL}/en/`, {
            headers: { cookie: 'summit_design=2; summit_qa=1' },
            redirect: 'manual',
            signal: AbortSignal.timeout(20_000),
        });
        html = await res.text();
        if (res.status !== 200) throw new Error(`GET /en/ answered ${res.status}`);
    } catch (error) {
        throw new Error(
            [
                `[qa:d2] Nothing usable answers at ${BASE_URL} (${error.cause?.code ?? error.message}).`,
                '        Point the harness at a Summit server: QA_BASE_URL=http://127.0.0.1:8042 npm run qa:d2',
            ].join('\n'),
        );
    }
    if (!html.includes('data-design="2"')) {
        throw new Error(`[qa:d2] ${BASE_URL}/en/ did not render Design 2 for the cookie summit_design=2 (no data-design="2" on <html>).`);
    }
    const hot = path.join(ROOT, 'public/hot-d2');
    const devServer = existsSync(hot) && html.includes(readFileSync(hot, 'utf8').trim());
    if (devServer) {
        console.warn('[qa:d2] note: pages load unbundled Vite dev assets (public/hot-d2); hero timings are indicative only.');
    }
    let commit = null;
    try {
        commit = execFileSync('git', ['rev-parse', '--short', 'HEAD'], { cwd: ROOT, encoding: 'utf8' }).trim();
    } catch {
        // not a git checkout
    }
    mkdirSync(runDir(), { recursive: true });
    const file = runDir('run.json');
    const previous = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : null;
    writeFileSync(
        file,
        `${JSON.stringify(
            {
                run: RUN,
                baseUrl: BASE_URL,
                startedAt: new Date().toISOString(),
                devServer,
                commit,
                argv: process.argv.slice(2),
                previousStartedAt: previous?.startedAt ?? null,
            },
            null,
            2,
        )}\n`,
    );
}
