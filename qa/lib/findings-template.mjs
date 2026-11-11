/**
 * Template of qa/findings.md (upgrade spec §9 checklist and §12 G3/G5): the reviewer's list of deviations
 * for a Design 2 QA run. Written once by `node scripts/qa/d2-report.mjs --init-findings` (never overwritten);
 * the deviation table has the same columns as the generated qa/shots/<date>/findings-auto.md.
 */
export const FINDINGS_TEMPLATE = `# Design 2 QA findings (Phase G)

Spec: \`docs/brief/upgrade-report.md\` §9 (checklist and QA loop) and §12 Phase G. Harness: \`qa/README.md\`.

| Run | Report | Automated rows | Reviewer | Date |
|---|---|---|---|---|
| \`qa/shots/<date>/\` | \`qa/report.html\` | \`qa/shots/<date>/findings-auto.md\` | | |

How to fill it in: run the harness, open \`qa/report.html\` and look at every screenshot (the readable tiles in
\`qa/shots/<date>/tiles/\`), compare it with the spec and the checklist below, and add one row per deviation to
section 3. Copy the automated rows you confirm from \`findings-auto.md\` (same columns). Fix, re-run, update the
status. Repeat until every check in sections 1 and 2 passes.

- **Severity:** critical (blocks release: compliance, Design 1 changed, a broken page or route) · high (WCAG
  failure, broken layout or overflow, console error, wrong or missing approved copy) · medium (a visible
  deviation from the spec) · low (polish).
- **Status:** open · fixed (commit) · wontfix (reason and who decided) · needs-owner · needs-client ·
  needs-lawyer.

## 1. Measurable checklist (spec §9; each must pass)

| # | Check | How it is verified | Result | Notes |
|---|---|---|---|---|
| 1 | No empty vertical band > 160 px at 1440 / 1280 / 768 / 390 | visual spec \`bands\`: full-width gaps between visible content (text, images, icons, controls, cards, edges of surfaces) | | |
| 2 | All UI text ≥ 14 px; body ≥ 16 px; H1 ≥ 40 px on mobile | visual spec \`smallText\` (14 px floor; sample-document fine print exempt); body and H1 sizes: inspect the 390 shots | | |
| 3 | Contrast: text ≥ 4.5:1, large text and icons ≥ 3:1; gold never small text on light | axe \`color-contrast\` (serious) in the visual spec and Lighthouse accessibility | | |
| 4 | Only token values (no raw hex, radius or shadow outside \`tokens.css\`) | \`npx stylelint "resources/css/d2/**/*.css"\` | | |
| 5 | Every interactive element has hover, active, focus-visible, disabled and loading states | \`/en/styleguide/\` and \`/ar/styleguide/\` with \`summit_design=2\` (manual) | | |
| 6 | Two-column components start at the same y (±1 px): Navigator, Footer | Playwright \`boundingBox\` assertions (Phase C) | | |
| 7 | No underlined links outside running text | visual spec \`underlinedLinks\` (reported as a warning) | | |
| 8 | Hero: documents visible by 2.0 s, callouts by 3.0 s, zero layout shift | hero spec \`docsRestBy2000\`, \`calloutsBy3000\`, \`cls\`; frames 0 / 600 / 1200 / 2000 / 3000 ms (production build: see qa/README.md) | | |
| 9 | Reduced motion: no transform animations, final state at once | hero spec \`finalAtFirstFrame\`, \`noMotion\`; \`npm run qa:d2:variants\` (reduced) | | |
| 10 | RTL: phone, WhatsApp and check icons do not mirror; arrows do; RTL enters from the left | AR screenshots (manual) and hero \`entrySide\` | | |
| 11 | Lighthouse mobile: Performance ≥ 90, Accessibility 100, Best Practices ≥ 95, SEO 100; axe 0 serious / critical | \`npm run qa:d2:lighthouse\` (production build); visual spec \`axe\` | | |
| 12 | Copy audit: no banned claims (24/7, free consultation, %, years of experience, law firm, award, rated) | \`npm run compliance\`; visual spec \`banned\` (warning) | | |
| 13 | Every proposed string carries \`data-copy-status="proposed"\` outside production and is listed in \`docs/proposed-copy.md\` | \`php artisan summit:proposed-copy\`; visual spec \`proposed\` count | | |

## 2. Phase G steps (spec §12)

| Step | What | Command | Result |
|---|---|---|---|
| G1 | Full-page screenshots: home, one service, one guide (P03), one case (P04), FAQ, contact, 404 × 1440 / 1280 / 768 / 390 × EN / AR / RU; hero frames 0 / 600 / 1200 / 2000 / 3000 ms, with and without reduced motion | \`npm run qa:d2\` (and \`npm run qa:d2:variants\`) | |
| G2 | \`qa/report.html\`: each screenshot next to its \`qa/reference/\` image (else the previous capture) | \`npm run qa:d2:report -- --tiles\` | |
| G3 | Open and inspect every screenshot; list the deviations here; fix; re-run until clean | review | |
| G4 | axe 0 serious / critical; Lighthouse CI mobile gates | visual spec \`axe\`; \`npm run qa:d2:lighthouse\` | |
| G5 | Checklist: bands, text size, contrast, tokens, states, alignment, underlines, hero timings, RTL, reduced motion, compliance, proposed copy | section 1 | |
| G6 | Phase summary: changes, remaining issues, screenshot paths | summary | |
| — | Design 1 unchanged: HTML byte for byte and pixels against the \`design-1\` tag | \`node scripts/qa/d1-identity.mjs\` and \`npm run qa:d2:d1-visual\` | |

## 3. Deviations

| Page | Width | Language | Finding | Severity | Status |
|---|---|---|---|---|---|
| | | | | | open |

## 4. Decisions needed (owner, client or lawyer)

| Topic | Question | Who | Decision |
|---|---|---|---|
| | | | |
`;
