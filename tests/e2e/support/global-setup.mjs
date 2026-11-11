/**
 * Preflight: fail fast with a clear message when nothing answers at the base URL, and warn when
 * the server that answers does not look like the Summit site (another app on the same port).
 * Set QA_SKIP_PREFLIGHT=1 to bypass.
 */
import { existsSync } from 'node:fs';
import path from 'node:path';
import { ROOT, pageUrl } from './qa.mjs';

export default async function globalSetup(config) {
  if (process.env.QA_SKIP_PREFLIGHT) return;
  const baseURL = config.projects[0]?.use?.baseURL ?? process.env.QA_BASE_URL ?? 'http://127.0.0.1:8000';

  let response;
  try {
    response = await fetch(pageUrl(baseURL, '/up'), { redirect: 'manual', signal: AbortSignal.timeout(10_000) });
  } catch (error) {
    throw new Error(
      [
        `[qa] Nothing answers at ${baseURL} (${error.cause?.code ?? error.message}).`,
        '     Start the site first:  php artisan serve --port=8000   (or: composer run dev)',
        '     or point the harness at it:  QA_BASE_URL=http://127.0.0.1:8001 npm run qa',
      ].join('\n'),
    );
  }

  // SecurityHeaders (global middleware) sends the Turnstile CSP on every Summit response.
  const csp = response.headers.get('content-security-policy') ?? '';
  if (!csp.includes('challenges.cloudflare.com')) {
    console.warn(
      `[qa] WARNING: ${baseURL} answered without the Summit security headers. Another app may be ` +
        'listening on that port; set QA_BASE_URL to the Summit server.',
    );
  }
  if (existsSync(path.join(ROOT, 'public/hot'))) {
    console.warn('[qa] note: the Vite dev server is active (public/hot); pages load unbundled dev assets.');
  }
}
