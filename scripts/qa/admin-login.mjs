// Signs the local admin in to /admin for the QA scripts (docs/briefs/design-2.1-and-admin-cms.md §12.2): the e-mail
// and password from storage/app/d2/admin/credentials.txt, then, since Phase 7, the authenticator code computed from
// the secret in storage/app/d2/admin/two-factor.txt (both gitignored, mode 600; never printed). A code is accepted
// once per 30-second step, so a rejected code is retried with the next step.
import crypto from 'node:crypto';
import fs from 'node:fs';

const read = (file) => (fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : '');

export function credentials() {
  const creds = read('storage/app/d2/admin/credentials.txt');
  const twoFactor = read('storage/app/d2/admin/two-factor.txt');
  return {
    email: creds.match(/^email:\s*(.+)$/m)[1].trim(),
    password: creds.match(/^password:\s*(.+)$/m)[1].trim(),
    secret: (twoFactor.match(/^secret:\s*(.+)$/m) || [])[1]?.trim() || null,
  };
}

function base32(secret) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const c of secret.replace(/=+$/, '').toUpperCase()) bits += alphabet.indexOf(c).toString(2).padStart(5, '0');
  const bytes = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
  return Buffer.from(bytes);
}

/** RFC 6238 TOTP (SHA1, 6 digits, 30 s) for the given time. */
export function totp(secret, at = Date.now()) {
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(at / 1000 / 30)));
  const hmac = crypto.createHmac('sha1', base32(secret)).update(counter).digest();
  const offset = hmac[hmac.length - 1] & 0xf;
  const code = ((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).toString().padStart(6, '0');
  return code;
}

/** Sign in on `page` against `base`; resolves when the panel (not the login or challenge) is shown. */
export async function adminSignIn(page, base) {
  const { email, password, secret } = credentials();
  await page.goto(`${base}/admin/login`);
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', password);
  await page.click('button[type=submit]');
  for (let attempt = 0; attempt < 3; attempt++) {
    await page.waitForLoadState('networkidle');
    const code = page.locator('input[autocomplete="one-time-code"]').first();
    if (!(await code.isVisible().catch(() => false))) break;
    if (!secret) throw new Error('two-factor is required: storage/app/d2/admin/two-factor.txt is missing');
    // a code is accepted once per 30-second step (also across QA processes): wait for a fresh step when needed
    const stepFile = 'storage/app/d2/admin/.totp-step';
    const step = () => Math.floor(Date.now() / 1000 / 30);
    const used = Number(read(stepFile) || 0);
    const left = 30 - (Math.floor(Date.now() / 1000) % 30);
    if (step() <= used || left < 4 || attempt > 0) await page.waitForTimeout((left + 1) * 1000);
    fs.writeFileSync(stepFile, String(step()), { mode: 0o600 });
    // Filament's code field is six boxes: clear them, then type the digits from the first one
    await code.click();
    for (let i = 0; i < 6; i++) await page.keyboard.press('Backspace');
    await code.click();
    await page.keyboard.type(totp(secret), { delay: 30 });
    // the sixth digit submits by itself; press the button only if the form is still waiting
    await page.waitForTimeout(2500);
    const submit = page.locator('button[type=submit]:visible').last();
    if (/\/login/.test(new URL(page.url()).pathname) && (await submit.isEnabled().catch(() => false)) && (await code.isVisible().catch(() => false))) {
      await submit.click().catch(() => {});
      await page.waitForTimeout(1500);
    }
  }
  await page.waitForURL((u) => !/\/login|multi-factor/.test(u.pathname), { timeout: 60_000 });
}
