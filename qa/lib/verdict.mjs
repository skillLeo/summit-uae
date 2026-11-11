/**
 * Pass / fail for the Design 2 harness.
 *
 * Playwright restarts its worker (a new Chrome, 6-10 s here) after every failed test. While the site is
 * being built most captures fail some check, so by default a capture test only fails when the capture
 * itself breaks; its failing checks become annotations, and the global teardown (this file's default
 * export) prints one verdict for the whole invocation and exits non-zero when any capture failed a check.
 * QA_D2_STRICT=1 restores one failing soft assertion per check instead.
 */
import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { expect } from '@playwright/test';
import { runDir } from './config.mjs';

export const STRICT = process.env.QA_D2_STRICT === '1';

/**
 * When this Playwright invocation started (ISO 8601), set by global-setup.mjs before anything else, also when
 * QA_SKIP_PREFLIGHT skips the preflight (npm run qa:d2:selftest). The verdict judges only the captures written
 * since then, so a run of other projects (the harness alone) never fails on an earlier run's screenshots
 * that are still in qa/shots/<run>/.
 */
export const STARTED_ENV = 'QA_D2_INVOCATION_STARTED_AT';

/** The records written at or after `since` (ISO strings compare in time order); all of them without `since`. */
export const capturedSince = (list, since) => list.filter((r) => r && (!since || (r.capturedAt ?? '') >= since));

/** Failing checks of a record: ok === false and not marked warn / info. */
export const failingChecks = (checks = {}) => Object.entries(checks).filter(([, c]) => c && c.ok === false && !c.warn && !c.info);
export const warningChecks = (checks = {}) => Object.entries(checks).filter(([, c]) => c && c.ok === false && c.warn);

/** Annotates (or, strict, soft-asserts) every failing check; `explain` turns a check into a readable line. */
export function settle(testInfo, checks, explain = (id, c) => `${id}: ${JSON.stringify(c).slice(0, 600)}`) {
    for (const [id, check] of failingChecks(checks)) {
        const text = explain(id, check);
        if (STRICT) expect.soft(check.ok, text).toBe(true);
        else testInfo.annotations.push({ type: 'qa-fail', description: text });
    }
    for (const [id, check] of warningChecks(checks)) {
        testInfo.annotations.push({ type: 'qa-warning', description: explain(id, check) });
    }
}

function records(kind, since) {
    const dir = runDir(kind);
    if (!existsSync(dir)) return [];
    return capturedSince(
        readdirSync(dir)
            .filter((f) => f.endsWith('.json'))
            .map((f) => {
                try {
                    return JSON.parse(readFileSync(path.join(dir, f), 'utf8'));
                } catch {
                    return null;
                }
            }),
        since,
    );
}

export default async function verdict() {
    const run = existsSync(runDir('run.json')) ? JSON.parse(readFileSync(runDir('run.json'), 'utf8')) : {};
    // This invocation's captures only (run.json keeps the start of the last run that passed the preflight).
    const since = process.env[STARTED_ENV] || run.startedAt || null;
    const all = [...records('visual', since), ...records('hero', since)].sort((a, b) => a.name.localeCompare(b.name));
    // Nothing captured by this invocation (the harness project alone): no verdict to give.
    if (!all.length) return;
    const failing = all
        .map((r) => ({ name: r.name, error: r.error ?? null, checks: failingChecks(r.checks).map(([id]) => id) }))
        .filter((r) => r.error || r.checks.length);
    const byCheck = {};
    for (const r of failing) for (const id of r.checks) byCheck[id] = (byCheck[id] ?? 0) + 1;
    writeFileSync(
        runDir('verdict.json'),
        `${JSON.stringify({ run: run.run, startedAt: since, finishedAt: new Date().toISOString(), captures: all.length, failing: failing.length, byCheck, list: failing }, null, 2)}\n`,
    );
    const lines = [`\nDesign 2 QA verdict: ${all.length - failing.length} of ${all.length} captures pass every check.`];
    if (failing.length) {
        lines.push(`Failing checks: ${Object.entries(byCheck).sort((a, b) => b[1] - a[1]).map(([id, n]) => `${id} ×${n}`).join(', ')}`);
        for (const r of failing.slice(0, 120)) lines.push(`  ${r.name.padEnd(34)} ${r.error ? `ERROR ${r.error.slice(0, 120)}` : r.checks.join(', ')}`);
        if (failing.length > 120) lines.push(`  … ${failing.length - 120} more in ${path.relative(process.cwd(), runDir('verdict.json'))}`);
    }
    lines.push(`Details: npm run qa:d2:report → qa/report.html (run ${run.run ?? ''})`);
    console.log(lines.join('\n'));
    if (failing.length && !STRICT) {
        // No stack: the verdict is the message, not a code location.
        const error = new Error(`Design 2 QA: ${failing.length} of ${all.length} captures fail checks (listed above; qa/shots/${run.run}/verdict.json).`);
        error.stack = error.message;
        throw error;
    }
}
