// Admin screenshots (Design 2.1 brief §14.3 contact sheets for Phase 4): signs in with the local admin of
// storage/app/d2/admin/credentials.txt (never printed), opens each admin path and saves a JPEG per path, interface
// language and width under storage/app/d2/phase-4/admin/.
//   node scripts/qa/admin-shots.mjs [--base=http://127.0.0.1:8042] [--locales=en,ar] [--widths=1440,390] path1 path2 …
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { adminSignIn, credentials } from './admin-login.mjs';

const args = Object.fromEntries(process.argv.slice(2).filter((a) => a.startsWith('--')).map((a) => a.slice(2).split('=')));
const paths = process.argv.slice(2).filter((a) => !a.startsWith('--'));
const base = args.base || 'http://127.0.0.1:8042';
const locales = (args.locales || 'en,ar').split(',');
const widths = (args.widths || '1440,390').split(',').map(Number);
const out = args.out || 'storage/app/d2/phase-4/admin';
const full = args.full !== 'false';
fs.mkdirSync(out, { recursive: true });

const { email } = credentials();

// The admin's interface language is the user's own choice (users.admin_locale): set it for each run, restore after.
const setLocale = (value) => execFileSync('php', ['artisan', 'tinker', '--execute',
  `$u = App\\Models\\User::where('email', base64_decode('${Buffer.from(email).toString('base64')}'))->first(); $old = $u->admin_locale; $u->forceFill(['admin_locale' => ${value === null ? 'null' : `'${value}'`}])->save(); echo $old;`]).toString().trim();
const original = setLocale('en') || null;

const browser = await chromium.launch({ channel: 'chrome' });
// the user's own language is put back even when a capture fails
process.on('exit', () => { try { setLocale(original); } catch { /* best effort */ } });
for (const locale of locales) {
  setLocale(locale);
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, timezoneId: 'Asia/Dubai' });
  const page = await context.newPage();
  await adminSignIn(page, base);
  // interface language through the user menu action is a Livewire action; the session key is set by visiting with ?locale
  for (const width of widths) {
    await page.setViewportSize({ width, height: width < 500 ? 844 : 900 });
    for (const p of paths) {
      const url = `${base}${p}`;
      const res = await page.goto(url, { waitUntil: 'networkidle' });
      await page.waitForTimeout(300);
      const name = `${p.replace(/^\/admin\/?/, '').replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'dashboard'}-${locale}-${width}.jpg`;
      await page.screenshot({ path: path.join(out, name), type: 'jpeg', quality: 70, fullPage: full });
      console.log(res?.status(), name);
    }
  }
  await context.close();
}
await browser.close();
setLocale(original);
