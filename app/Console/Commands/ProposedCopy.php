<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Writes docs/proposed-copy.md from lang/{en,ar,ru}/proposed.php (the single source of new copy): Design 1
 * keys first, then the Design 2 keys (proposed.d2.*, merged from lang/{locale}/proposed-d2/*.php) by area.
 */
final class ProposedCopy extends Command
{
    protected $signature = 'summit:proposed-copy {--check : Fail if a key is missing in any language or the doc is stale}';

    protected $description = 'Generate docs/proposed-copy.md: every string that is not approved DOCX copy, in EN/AR/RU';

    /** Where each key is used, for the reviewer. */
    private const USAGE = [
        'nav.main' => 'Accessible name of the desktop navigation landmark (screen readers only).',
        'nav.quick' => 'Accessible name of the mobile bottom bar (screen readers only).',
        'nav.breadcrumb' => 'Accessible name of breadcrumb navigation (screen readers only).',
        'external.new_tab' => 'Screen-reader text after every external link that opens a new tab (specialist sites, WhatsApp).',
        'suggest.message' => 'Language suggestion bar, shown in the target language when the browser prefers it. Never redirects.',
        'suggest.action' => 'Button in the suggestion bar that opens the equivalent page.',
        'suggest.dismiss' => 'Dismiss button in the suggestion bar (remembered for 12 months).',
        'hours.open' => 'Live indicator next to the approved office hours (hero quiet line, contact section).',
        'hours.closed' => 'Live indicator next to the approved office hours when outside Mon–Fri 9–18 UAE time.',
        'navigator.heading' => 'H2 of the Matter Navigator (research brief proposal). Config alternative: the approved "Questions We Help You Answer" (SUMMIT_NAVIGATOR_HEADING=approved).',
        'navigator.intro' => 'Introduction under the Navigator heading; states that choices stay in the browser.',
        'navigator.step_audience' => 'Optional audience step (hidden until the audience-to-service mapping is approved).',
        'navigator.audience_all' => 'First segment of the optional audience step.',
        'navigator.step_pick' => 'Legend of the situation list; mobile launcher button; sheet title.',
        'navigator.step_file' => 'Label of the result ("starting file") and the mobile sheet title for step 2.',
        'navigator.filter_label' => 'Accessible label of the local filter field.',
        'navigator.filter_placeholder' => 'Placeholder of the local filter field.',
        'navigator.no_results' => 'Shown when the filter matches no situation.',
        'navigator.clear_filter' => 'Button that clears the filter.',
        'navigator.show_file' => 'Submit button of the no-JavaScript version of the Navigator.',
        'navigator.empty' => 'Empty state of the starting file before a situation is chosen.',
        'navigator.your_situation' => 'Reserved label for the chosen situation (currently the approved "This service may be relevant if:" is used).',
        'navigator.saved_note' => 'Note under the document checklist: ticks stay on this device (localStorage).',
        'navigator.clear_ticks' => 'Removes the saved ticks for the current service.',
        'navigator.print' => 'Prints the starting file (emblem, date and the approved disclaimer).',
        'navigator.copy_link' => 'Copies a link such as /en/#navigator=S05-2.',
        'navigator.link_copied' => 'Announced (aria-live) after the link is copied.',
        'navigator.change' => 'Returns to the situation list (mobile sheet, launcher once a file exists).',
        'navigator.progress' => 'Mobile sheet progress, e.g. "1 of 2".',
        'navigator.print_date' => 'Date line in the printed starting file.',
        'navigator.close' => 'Accessible label of the Navigator sheet close button.',
        'navigator.back' => 'Reserved: back control in the mobile sheet.',
        'team.portrait_pending' => 'Caption in the neutral frame reserved for Ms Sarah Zaki until her portrait is supplied (Team, Phase 2; shown in the styleguide).',
        'sheet.close' => 'Accessible label of the close button of the language, contact and search sheets (menus use the approved "Close menu").',
        'form.reference' => 'Label before the stored enquiry reference in the approved success message.',
        'preview.notice' => 'Prototype-only notice on registered routes whose templates arrive in Phase 2 (noindex pages). Remove at Phase 2.',
        'offline.title' => 'Title of the offline page served by the service worker.',
        'offline.text' => 'Text of the offline page before the approved phone, WhatsApp and email controls.',
    ];

    public function handle(): int
    {
        $langs = ['en', 'ar', 'ru'];
        $data = [];
        foreach ($langs as $lang) {
            // Empty Design 2 areas dot to an empty array: they are not strings.
            $data[$lang] = array_filter(Arr::dot(require lang_path("{$lang}/proposed.php")), 'is_string');
        }
        $keys = array_keys($data['en']);
        $problems = [];
        foreach ($langs as $lang) {
            foreach (array_diff($keys, array_keys($data[$lang])) as $missing) {
                $problems[] = "{$lang}: missing {$missing}";
            }
            foreach (array_diff(array_keys($data[$lang]), $keys) as $extra) {
                $problems[] = "{$lang}: {$extra} has no English source";
            }
        }

        $md = $this->render($keys, $data);
        $path = base_path('docs/proposed-copy.md');
        if ($this->option('check')) {
            if (! is_file($path) || file_get_contents($path) !== $md) {
                $problems[] = 'docs/proposed-copy.md is stale: run php artisan summit:proposed-copy';
            }
            foreach ($problems as $p) {
                $this->error($p);
            }

            return $problems === [] ? self::SUCCESS : self::FAILURE;
        }

        File::put($path, $md);
        foreach ($problems as $p) {
            $this->warn($p);
        }
        $d2 = count(array_filter($keys, fn (string $k) => str_starts_with($k, 'd2.')));
        $this->info(count($keys).' proposed strings written to docs/proposed-copy.md ('.(count($keys) - $d2).' Design 1, '.$d2.' Design 2)');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, array<string, string>>  $data
     */
    private function render(array $keys, array $data): string
    {
        $out = [
            '# Proposed copy: needs client approval',
            '',
            'Every visible or announced string on the Prototype 1 pages comes from the approved DOCX files (23 October 2026), **except the strings below**. They were written for interface needs the DOCX files do not cover. None of them states a fact about Summit, a result, a price or a promise.',
            '',
            '- Source of truth: `lang/{en,ar,ru}/proposed.php` (keys `proposed.*`). This file is generated by `php artisan summit:proposed-copy`; do not edit it by hand.',
            '- Status of every row: **needs client approval** in all three languages. The Arabic and Russian versions are good-faith translations and need review by a native legal-language reviewer.',
            '- If a string is rejected, change it in the three language files and re-run the command; the site picks it up immediately.',
            '',
            '| Key | Where it is used | English | العربية | Русский |',
            '|---|---|---|---|---|',
        ];
        $d2Keys = array_values(array_filter($keys, fn (string $k) => str_starts_with($k, 'd2.')));
        $keys = array_values(array_diff($keys, $d2Keys));
        foreach ($keys as $key) {
            $out[] = sprintf(
                '| `proposed.%s` | %s | %s | %s | %s |',
                $key,
                self::cell(self::USAGE[$key] ?? ''),
                self::cell($data['en'][$key] ?? '—'),
                self::cell($data['ar'][$key] ?? '**missing**'),
                self::cell($data['ru'][$key] ?? '**missing**'),
            );
        }
        $out[] = '';
        $out[] = '## Not proposed copy, recorded for transparency';
        $out[] = '';
        $out[] = '- **Composed from approved strings only.** Some accessible names join two approved strings, e.g. the link "Explore This Service" is announced together with its service title (`aria-labelledby`), and the language button is announced as "Choose language" + the current language name.';
        $out[] = '- **Language names in their own script** (العربية, English, Русский) are the approved labels from each language\'s own homepage file.';
        $out[] = '- **Dynamic year.** The approved footer line "© 2026 Summit Legal Consultancy. All rights reserved." is rendered with the current year, as its red note instructs.';
        $out[] = '- **Cross-file reuse** (all approved): the hero quiet line uses the Contact file\'s "Office Hours … UAE time"; the home case previews show the "Matter:" line from the Case Studies file; the home guides are grouped under the Knowledge Centre file\'s three categories; the consultation form uses the Contact file\'s labels and messages (the master); the Navigator uses the Practice Areas situations and each service page\'s documents, first steps, mistakes and related-guide link. See `docs/content-diff.md` for the label differences between the homepage and Contact form copy.';
        $out[] = '- **Internal styleguide annotations** (`/{locale}/styleguide/`, noindex utility page) are English technical labels in `lang/en/styleguide.php` (token and component names). They are not public website copy.';
        $out[] = '- **Numerals and symbols** (01–11 service index, 1–4 method steps, the required-field asterisk, the decorative 404 code) are not copy.';
        $out[] = '';

        return implode("\n", [...$out, ...$this->renderDesign2($d2Keys, $data)]);
    }

    /**
     * Design 2 (docs/upgrade-plan.md): lang/{locale}/proposed-d2/{area}.php, keys proposed.d2.{area}.*.
     *
     * @param  list<string>  $keys
     * @param  array<string, array<string, string>>  $data
     * @return list<string>
     */
    private function renderDesign2(array $keys, array $data): array
    {
        $out = [
            '## Design 2 (premium upgrade): proposed copy',
            '',
            'Design 2 (`docs/brief/upgrade-report.md` §12) adds interface copy the DOCX files do not cover: the hero eyebrow, sample-document text and callouts, trust-row titles, document explanations, calculator labels, notes and disclaimers, office-hours microcopy, wizard steps and toasts, the short header CTA and tab-bar labels. All of it **needs client approval in EN/AR/RU**, and the calculator texts also **need review by a UAE lawyer** before launch. Sample documents are fictional illustrations, watermarked in all three languages.',
            '',
            '- Source of truth: `lang/{en,ar,ru}/proposed-d2/{area}.php` (keys `proposed.d2.{area}.*`), rendered with `d2p()`; elements showing it carry `data-copy-status="proposed"` outside production.',
            '- *TODO-translation* marks a string whose Arabic or Russian text is missing or still identical to the English.',
            '',
        ];
        $byArea = [];
        foreach ($keys as $key) {
            $byArea[explode('.', $key, 3)[1] ?? 'misc'][] = $key;
        }
        ksort($byArea);
        foreach ($byArea as $area => $areaKeys) {
            $out[] = '### '.$area.' ('.count($areaKeys).')';
            $out[] = '';
            $out[] = '| Key | English | العربية | Русский | Status |';
            $out[] = '|---|---|---|---|---|';
            foreach ($areaKeys as $key) {
                $en = $data['en'][$key] ?? '';
                $todo = [];
                foreach (['ar', 'ru'] as $lang) {
                    $value = $data[$lang][$key] ?? null;
                    if ($value === null || $value === '' || ($value === $en && preg_match('/\pL{3,}/u', $en))) {
                        $todo[] = strtoupper($lang);
                    }
                }
                $out[] = sprintf(
                    '| `proposed.%s` | %s | %s | %s | %s |',
                    $key,
                    self::cell($en),
                    self::cell($data['ar'][$key] ?? '**missing**'),
                    self::cell($data['ru'][$key] ?? '**missing**'),
                    $todo === [] ? 'needs approval' : 'needs approval · TODO-translation ('.implode(', ', $todo).')',
                );
            }
            $out[] = '';
        }
        if ($keys === []) {
            $out[] = '_No Design 2 strings yet._';
            $out[] = '';
        }

        return $out;
    }

    private static function cell(string $s): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $s);
    }
}
