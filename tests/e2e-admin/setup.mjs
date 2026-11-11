import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import { chromium } from '@playwright/test';

const STATE = 'storage/app/d2/phase-4/flows';

export default async function setup(config) {
  fs.mkdirSync(STATE, { recursive: true });
  // one sign-in for the whole run (the login is rate limited): the session is reused by every flow
  const baseURL = config.projects[0].use.baseURL;
  const creds = fs.readFileSync('storage/app/d2/admin/credentials.txt', 'utf8');
  const browser = await chromium.launch({ channel: 'chrome' });
  const page = await browser.newPage();
  await page.goto(`${baseURL}/admin/login`);
  await page.fill('input[type=email]', creds.match(/^email:\s*(.+)$/m)[1].trim());
  await page.fill('input[type=password]', creds.match(/^password:\s*(.+)$/m)[1].trim());
  await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login')), page.click('button[type=submit]')]);
  await page.context().storageState({ path: `${STATE}/admin-state.json` });
  await browser.close();
  // an earlier run kept its changes (QA_KEEP=1): keep its starting point, so the final restore undoes everything
  if (fs.existsSync(`${STATE}/since.txt`) && fs.existsSync(`${STATE}/settings.json`)) return;
  const since = new Date(Date.now() - 1000).toISOString().slice(0, 19).replace('T', ' ');
  fs.writeFileSync(`${STATE}/since.txt`, since);
  execFileSync('php', ['scripts/qa/cms-reset.php', 'snapshot', `${STATE}/settings.json`], { stdio: 'inherit' });
}
