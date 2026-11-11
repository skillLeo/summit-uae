# Summit Legal Consultancy — Website

The public website of **Summit Legal Consultancy** (سَمِت للاستشارات القانونية), a legal consultancy in the
United Arab Emirates. It is a trilingual site in English, Arabic (right to left) and Russian, built with Laravel.
It turns the firm's approved Word documents into a fast, accessible and content-exact site.

Designed and developed by **Hassam (SkillLeo)**.

---

## Purpose

Summit advises individuals, businesses, investors and residents on debt recovery, criminal matters,
cryptocurrency disputes, corporate and commercial law, property and tenancy, employment, tax, family law and
wills, compliance, legal translation and business services.

The site has one job: help a visitor understand their position and start a well-prepared consultation request.
It does that without overpromising. There are no invented statistics, ratings or testimonials, and it never
suggests the firm is a law firm or that a result is guaranteed.

## Visitor flow

1. **Arrive.** The neutral root `/` offers the three languages, or returns the visitor to the language they
   chose before. Each language has its own URLs: `/en/`, `/ar/`, `/ru/`.
2. **Orient.** The home page explains the eleven practice areas, who the firm helps and how a review works, in
   the exact order of the approved content.
3. **Self-assess.** Optional interactive tools help visitors prepare:
   - **Start your matter:** pick a situation and see the documents that usually help.
   - **Anatomy of a Document:** explore what each part of a cheque, contract, tenancy, transfer receipt or
     notice can show.
   - **Where Do You Stand?:** 44 real situations with a starting file of documents, first steps and mistakes
     to avoid.
   - **Calculators:** a UAE end-of-service gratuity estimator and a Dubai rent increase check. Both run
     entirely in the browser.
4. **Read.** Practice-area pages, a legal knowledge centre with nine guides, case studies, an FAQ centre with
   search and the legal pages.
5. **Act.** A five-step consultation request: contact details, matter, deadline, documents, review. It validates
   as you go and sends nothing until the visitor submits. A WhatsApp button always opens a pre-written,
   language-specific message. It never sends automatically.

## Highlights

- **Content exactly as approved.** Every visible line comes from the client's Word documents. An importer reads
  them and separates public copy from designer notes. An automated check compares every rendered page with the
  source documents, so no line is missing, cut, altered or out of order.
- **Three languages, one codebase.** Full right-to-left layout for Arabic with logical CSS properties, and a
  per-locale setting for Western or Eastern Arabic digits. Phone numbers, e-mails and codes stay left to right.
- **Two design directions.** A refined editorial design and a modern premium design share the same routes,
  content and backend. A session- and cookie-based switch lets the client compare them side by side. The
  second design adds an animated document stage, interactive tools, a mobile bottom bar, bottom sheets and
  view transitions.
- **Accessibility.** Targets WCAG 2.2 AA: one H1 per page, skip link, visible focus, 44–48 px touch targets,
  4.5:1 contrast, keyboard-operable menus, carousels and tools, and full support for reduced motion.
- **Performance.** Lighthouse mobile scores of 98–100. Each page loads only the JavaScript it uses (45–80 KB
  gzip) and at most 52 KB of CSS, with no layout shift and at most two preloaded fonts.
- **Privacy and compliance by design.** No third-party trackers. A strict Content Security Policy (no inline
  styles or scripts without a nonce). Enquiries are stored on the server only. Every unapproved text is marked
  and can be hidden with one switch.
- **Quality gates.** 750+ PHPUnit tests, 550+ Vitest tests, Playwright suites for layout, accessibility (axe),
  keyboard, motion and visual regression, a design-rule lint, and Lighthouse budgets.

## Architecture

| Layer | What |
|---|---|
| Framework | Laravel 13 on PHP 8.4+ |
| Admin | Filament 5 on Livewire 4 |
| Front end | Blade components, Alpine.js (CSP build), modern CSS (cascade layers, container queries, subgrid, logical properties), Vite 8 |
| Content | Translatable models (`spatie/laravel-translatable`, JSON columns `{en, ar, ru}`) behind a cached `ContentRepository` |
| Database | SQLite by default (MySQL or MariaDB supported through `DB_CONNECTION`) |
| Testing | PHPUnit, Vitest, Playwright, axe-core, Lighthouse |

### Content pipeline

```
package/docx/{en,ar,ru}/*.docx
        │  php artisan summit:import   (DOCX parser + per-page mappers)
        ▼
database/content/*.json                (translatable shape: {field: {en, ar, ru}})
        │  php artisan db:seed
        ▼
content tables  ──►  ContentRepository (cached)  ──►  Blade views
```

Interface strings live in `lang/{en,ar,ru}/`. Texts written for the site that are not yet in the approved
documents are kept separately in `lang/*/proposed*/`, so they can be reviewed and approved as a set.

### Design switch

`?design=1` or `?design=2` sets a cookie (`summit_design`); `SUMMIT_DEFAULT_DESIGN` sets the default.
The second design lives in its own folders (`resources/views/designs/d2`, `resources/views/components/d2`,
`resources/css/d2`, `resources/js/d2`) and builds with `vite.d2.config.js`. Any view it does not provide falls
back to the first design.

## Project structure

```
app/
  Content/            DOCX reader, importers, ContentRepository, Navigator data
  Design/             design switch, D2 helpers (digits, hours, icons, hero stage, calculators, pending copy)
  Http/Controllers/   home, pages, calculators, enquiries
  Filament/           admin panel
config/summit.php     locales, pages, services, guides, anchors
config/design.php     design switch and design settings
database/content/     imported content (JSON)
lang/{en,ar,ru}/      interface and proposed texts
package/docx/         the approved source documents
resources/
  views/              design 1 views; designs/d2 and components/d2 for design 2
  scss/, css/d2/      styles (design tokens, components, sections, pages)
  js/, js/d2/         Alpine components, hero stage, tools, wizard, search
scripts/              build helpers and QA scripts
tests/                PHPUnit feature tests and text snapshots
qa/                   Playwright suites and shared QA library
```

## Getting started

Requirements: PHP 8.4+ with `pdo_sqlite`, `intl`, `mbstring`, `fileinfo`; Composer; Node 22+.

```sh
git clone https://github.com/skillLeo/summit-uae.git
cd summit-uae
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed

composer run dev          # app server, queue, logs and Vite together
```

Open `http://127.0.0.1:8000/en/` (add `?design=2` for the second design).

### Production build

```sh
composer install --no-dev --optimize-autoloader
npm run build                                  # design 1
npx vite build --config vite.d2.config.js      # design 2
npx vite build --config vite.admin.config.js   # admin
php artisan migrate --force && php artisan db:seed --force
php artisan optimize
```

## Commands

| Command | Purpose |
|---|---|
| `php artisan summit:import` | Re-import every approved Word document into `database/content/*.json` |
| `php artisan db:seed` | Load the imported content into the database |
| `php artisan summit:docx-dump <file>` | Print one Word document with its designer notes marked |
| `php artisan summit:proposed-copy` | List every text that still needs client approval |
| `php artisan test` | PHPUnit test suite |
| `npx vitest run` | JavaScript unit tests |
| `npm run qa` | Playwright: pages, accessibility, keyboard |
| `npm run qa:lint` | Design-rule lint across every page, language and width |
| `npm run qa:docx` | Rendered pages against the approved documents |
| `npm run qa:budgets` | Per-page JavaScript, CSS and font budgets |
| `npm run qa:lighthouse` | Lighthouse mobile gate |

## Configuration

| Variable | Meaning |
|---|---|
| `SUMMIT_DEFAULT_DESIGN` | `1` or `2`, the design shown by default |
| `SUMMIT_DESIGN_SWITCHER` | show the floating design switch (review environments only) |
| `SUMMIT_SHOW_PENDING` | show texts awaiting client approval (default: on outside production) |
| `SUMMIT_ARABIC_DIGITS` | `western` (0-9) or `eastern` (٠-٩) on Arabic pages |
| `SUMMIT_LIVE_STATUS` | show the "open now" office-hours status in the contact block |
| `SUMMIT_NOINDEX` | ask search engines not to index the site (staging) |
| `TURNSTILE_*` | optional Cloudflare Turnstile for the consultation form |

## Contact details on the site

Phone `+971 52 466 6191` · E-mail `info@summitlegaluae.com` · WhatsApp `+971 52 466 6191`

---

© Summit Legal Consultancy. Website by Hassam (SkillLeo). All rights reserved.
