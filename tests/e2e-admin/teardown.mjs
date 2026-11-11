import { execFileSync } from 'node:child_process';
import fs from 'node:fs';

const STATE = 'storage/app/d2/phase-4/flows';

export default async function teardown() {
  fs.rmSync(`${STATE}/admin-state.json`, { force: true });
  if (process.env.QA_KEEP === '1') return;
  const since = fs.readFileSync(`${STATE}/since.txt`, 'utf8').trim();
  execFileSync('php', ['scripts/qa/cms-reset.php', 'restore', `${STATE}/settings.json`, since], { stdio: 'inherit' });
  fs.rmSync(`${STATE}/since.txt`);
  fs.rmSync(`${STATE}/settings.json`);
}
