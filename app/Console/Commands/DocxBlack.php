<?php

namespace App\Console\Commands;

use App\Content\Docx\DocxReader;
use App\Content\Import\ImportException;
use App\Content\Import\ImportRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Design 2 QA (brief 2.1 §5.3): the approved black text of every DOCX, per page and locale, in document order, for
 * scripts/qa/docx-conformance.mjs.
 *
 * It reuses the content importer: DocxReader separates black (public) runs from red designer notes, and the mappers
 * (ImportRunner, the same run as `summit:import --dry-run`, nothing written to database/content) record which field
 * consumed every black block (the ledger). Each black line gets a kind from RULES, an explicit, ordered list keyed on
 * that field path. Nothing is skipped by similarity: a line is visible copy unless a rule names its field.
 *
 *   visible            approved copy that must be rendered, complete and in the DOCX order
 *   group              visible copy whose order is checked only within its group (the homepage form lists its labels,
 *                      then its options, then its notices, while the form renders each option under its label)
 *   control            an interface string the DOCX gives by role, not by position (search, filter and toggle
 *                      buttons, "Back to FAQ categories"): present anywhere on the page, order not checked
 *   label              an accessible name or attribute (aria-label, alt, placeholder) or visible text
 *   state              a message shown only after an action (validation, delivery states, live messages)
 *   conditional        rendered only when a condition outside the page holds (secure uploads, optional trackers)
 *   seo-title          the HTML <title>
 *   meta-description   <meta name="description">
 *   wa-draft           the prefilled WhatsApp message inside every wa.me link
 *
 * Pages: PAGES maps each DOCX file (by its prefix, the same in every locale) to its route. 00_Site_Map is the link
 * registry and release checklist (destinations, link IDs, registry labels), not a page, so it is not checked; its black
 * lines still count as approved copy when they appear elsewhere. Every page except the home also gets the shared header
 * and footer lines of the homepage DOCX (each inner-page DOCX says "Reuse the approved header, footer and mobile
 * controls from 01_Homepage"). The calculators page has no DOCX: only the shared lines are expected and the rest of
 * its text must be registered or marked pending.
 *
 *   php artisan summit:docx-black                      summary table
 *   php artisan summit:docx-black --json               everything the conformance script needs (pages, rules, registry)
 *   php artisan summit:docx-black --page=home --locale=ar   one page, annotated
 */
final class DocxBlack extends Command
{
    protected $signature = 'summit:docx-black
        {--json : Print JSON (pages, lines, rules, pending blocks and the string registry)}
        {--locale=* : Only these locales (en, ar, ru)}
        {--page=* : Only these page slugs (home, p02-practice-areas, s06-labor-law, 404, calculators ...)}
        {--no-registry : JSON without the string registry}';

    protected $description = 'Print the approved black DOCX text per page and locale, in order, with the DOCX-to-page mapping (Design 2 QA)';

    /**
     * DOCX filename prefix => page. slug = the route slug of scripts/qa/text-snapshot.mjs; path is relative to /{locale}/.
     *
     * @var array<string, array{slug: string, code: string, path: string, status: int}|null>
     */
    public const PAGES = [
        '00_Site_Map' => null, // link registry and release checks, not a page
        '01_Homepage' => ['slug' => 'home', 'code' => 'P01', 'path' => '', 'status' => 200],
        '02_Services_Overview' => ['slug' => 'p02-practice-areas', 'code' => 'P02', 'path' => 'practice-areas/', 'status' => 200],
        '03_Knowledge_Centre' => ['slug' => 'p03-resources', 'code' => 'P03', 'path' => 'resources/', 'status' => 200],
        '04_Case_Studies' => ['slug' => 'p04-case-studies', 'code' => 'P04', 'path' => 'case-studies/', 'status' => 200],
        '05_FAQ_Centre' => ['slug' => 'p05-faq', 'code' => 'P05', 'path' => 'faq/', 'status' => 200],
        '06_About_Us' => ['slug' => 'p06-about', 'code' => 'P06', 'path' => 'about/', 'status' => 200],
        '07_Contact_Us' => ['slug' => 'p07-contact', 'code' => 'P07', 'path' => 'contact/', 'status' => 200],
        '08_Legal_Notice' => ['slug' => 'p08-legal-disclaimer', 'code' => 'P08', 'path' => 'legal-disclaimer/', 'status' => 200],
        'L01_Privacy_Policy' => ['slug' => 'l01-privacy-policy', 'code' => 'L01', 'path' => 'privacy-policy/', 'status' => 200],
        'L02_Terms_and_Conditions' => ['slug' => 'l02-terms-and-conditions', 'code' => 'L02', 'path' => 'terms-and-conditions/', 'status' => 200],
        'L03_404_Page' => ['slug' => '404', 'code' => 'U01', 'path' => 'no-such-page-qa/', 'status' => 404],
    ];

    /** Pages without a DOCX (their copy is pending client approval): only the shared header and footer lines apply. */
    public const EXTRA_PAGES = [
        ['slug' => 'calculators', 'code' => 'calculators', 'path' => 'calculators/', 'status' => 200, 'pending' => true],
    ];

    /**
     * Kind rules, first match wins, on the importer's field path (ledger). Every rule is documented in the report.
     * [id, kind, field pattern, group (for kind "group"), why]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: ?string, 4: string}>
     */
    public const RULES = [
        ['seo-title', 'seo-title', '/(^|\.)seo_title$/', null, 'SEO title value: the HTML title, never a visible heading (DOCX notes "SEO title value", "Browser title").'],
        ['meta-description', 'meta-description', '/(^|\.)meta_description$/', null, 'Meta description value: the meta description, not body copy.'],
        ['wa-draft', 'wa-draft', '/(^|\.)whatsapp_draft$/', null, 'The WA01 prefilled message: the text parameter of every wa.me link, "not a separate visible paragraph".'],
        ['registry-label', 'conditional', '/(^|\.)registry_label(#.*)?$/', null, 'Site-map registry labels (00_Site_Map only): link documentation, not page copy.'],
        ['a11y-label', 'label', '/^ui\.a11y\./', null, 'Mobile controls (Open menu, Close menu, Choose language, Skip to content): "interface labels, not body headings": accessible names of icon controls.'],
        ['brand', 'label', '/^ui\.(brand\.(name|logo_alt)|footer\.brand)$/', null, 'The brand name in the header and footer and the logo alt text: rendered as the official lockup, whose alt text or accessible name carries it (brief §3.8: the wordmark is never set in a font).'],
        ['floating-whatsapp', 'label', '/^ui\.footer\.whatsapp$/', null, 'The floating WhatsApp control\'s "same public label": its accessible name.'],
        ['image-alt', 'label', '/\.alt$/', null, 'Image alt text (team portraits).'],
        ['input-placeholder', 'label', '/^ui\.(faq\.search_placeholder|resources\.search\.placeholder)$/', null, 'Search input placeholders: the placeholder attribute.'],
        ['upload', 'conditional', '/^(P01\.form_home_variant\.(labels\.attachment|attachment_hint|states\.invalid_attachment)|ui\.form\.attachment\..+)$/', null, 'The attachment field, its hint, remove button and errors: "If the backend cannot meet these rules, keep uploads disabled and remove the upload field and its hint" (homepage and Contact notes, brief §5.2).'],
        ['form-state', 'state', '/(^|\.)states?\./', null, 'Form feedback and delivery states (submitting, success, field errors, failure, rate limit, new enquiry): conditional interface messages shown only after an action.'],
        ['field-error', 'state', '/^ui\.form\..+\.error(_[a-z]+)?$/', null, 'Field validation messages: "Display only when this field is invalid".'],
        ['live-message', 'state', '/^ui\.(faq\.(no_results|link_copied|copy_failed|collapse_all)|resources\.no_results)$/', null, 'Live messages and the second state of a toggle: no results, link copied, copy failed, "Hide all answers".'],
        ['search-controls', 'control', '/^ui\.(faq\.(search_label|search_submit|search_reset|expand_all|copy_link|back_to_categories)|resources\.(search\.label|topic\.label|topic\.all|clear))$/', null, 'Search, filter and toggle controls: "Use the following strings as interface text in the exact roles described. They are not a list of body paragraphs" (FAQ Centre, Knowledge Centre); "Back to FAQ categories" is repeated per category in the DOCX: its presence is checked, not its count.'],
        ['consent-link', 'control', '/^ui\.form\.consent\.link$/', null, 'The privacy-policy link label after the consent sentence: the link is the phrase inside that sentence, and in Russian "the separate linked label below supplies the same destination and the standalone footer wording; do not substitute its nominative form into the sentence" (07_Contact_Us_RU): present anywhere (the consent link, or the footer).'],
        ['contact-group-help', 'control', '/^ui\.form\.contact_group\.help$/', null, 'The contact-group guidance: placed "next to phone/email" by its note, although the DOCX lists it after the attachment.'],
        ['cookie-ui', 'conditional', '/^ui\.cookies\./', null, 'Cookie and privacy preference interface: "Implement the preference panel only when the matching optional tools are available" (L01); the site uses no optional tools (brief §1.4).'],
        ['header-nav', 'group', '/^ui\.nav\./', 'header-nav', 'The seven header navigation labels: "keep the same order" among themselves; the header arranges its controls (navigation row, language menu) freely.'],
        ['header-languages', 'group', '/^ui\.lang\./', 'header-languages', 'The three language labels (Arabic, English, Russian): in order among themselves wherever the header places the language menu.'],
        ['footer-copyright', 'group', '/^ui\.footer\.copyright$/', 'footer-copyright', 'The copyright line: the owner placed the Specialist Resources group as the footer\'s last group directly above it (Phase 1, components/d2/shell/footer.blade.php), so its position after the disclaimer is not checked.'],
        ['home-form-labels', 'group', '/^P01\.form_home_variant\.labels\./', 'home-form-labels', 'Homepage form field labels: in order among themselves (the DOCX lists all labels, then the options, then the hints).'],
        ['home-form-language', 'group', '/^P01\.form_home_variant\.language_(placeholder|options\..+)$/', 'home-form-language', 'Homepage form language options: in order among themselves.'],
        ['home-form-matter', 'group', '/^P01\.form_home_variant\.matter_(placeholder|other)$/', 'home-form-matter', 'Homepage form matter options: the placeholder, then the eleven service titles (reused), then "Other Matter".'],
        ['home-form-notices', 'group', '/^P01\.form_home_variant\./', 'home-form-notices', 'Homepage form hints, notices, consent and submit: in order among themselves.'],
    ];

    /** Shared header and footer lines of the homepage DOCX (field prefixes). */
    private const SHARED_HEADER = '/^ui\.(nav|lang|a11y|brand)\./';

    private const SHARED_FOOTER = '/^(ui\.footer\.|specialist_sites\.)/';

    public function handle(DocxReader $reader): int
    {
        $runner = new ImportRunner($reader, ImportContent::discoverMappers(), base_path('package/docx'));
        try {
            $runner->run();
        } catch (ImportException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'summit-ledger-');
        try {
            $runner->writeLedger($tmp);
            $ledger = json_decode((string) file_get_contents($tmp), true) ?: [];
        } finally {
            @unlink($tmp);
        }

        $locales = $this->option('locale') ?: ImportRunner::LOCALES;
        $onlyPages = $this->option('page') ?: null;
        $pages = [];
        $approved = [];
        $hosts = [];
        foreach ($locales as $locale) {
            if (! in_array($locale, ImportRunner::LOCALES, true)) {
                $this->error("Unknown locale {$locale}");

                return self::INVALID;
            }
            $docs = [];
            foreach (glob(base_path("package/docx/{$locale}/*.docx")) ?: [] as $file) {
                $name = basename($file);
                $page = $this->pageFor($name);
                $doc = $reader->read($file, $locale);
                $lines = [];
                foreach ($doc->publicBlocks() as $block) {
                    foreach ($block->links as $link) {
                        $host = parse_url($link['href'], PHP_URL_HOST);
                        if (is_string($host) && $host !== '') {
                            $hosts[$locale][preg_replace('/^www\./', '', strtolower($host))] = true;
                        }
                    }
                    $field = $ledger[$locale][$name][$block->index] ?? null;
                    $lines[] = $this->blackLine($block->index, $block->text, $field, $block->headingLevel, $block->bullet, $block->number, $name);
                    $approved[$locale][$block->text] = true;
                }
                $docs[$name] = ['page' => $page, 'lines' => $lines];
            }
            $home = null;
            foreach ($docs as $name => $info) {
                if (($info['page']['slug'] ?? null) === 'home') {
                    $home = $info['lines'];
                }
            }
            $header = array_values(array_filter($home ?? [], fn ($l) => $l['field'] !== null && preg_match(self::SHARED_HEADER, $l['field'])));
            $footer = array_values(array_filter($home ?? [], fn ($l) => $l['field'] !== null && preg_match(self::SHARED_FOOTER, $l['field'])));
            $mark = fn (array $lines, string $part) => array_map(fn ($l) => ['part' => $part] + $l, $lines);

            foreach ($docs as $name => $info) {
                $page = $info['page'];
                if ($page === null) {
                    continue;
                }
                $lines = $page['slug'] === 'home'
                    ? array_map(fn ($l) => ['part' => $this->partOf($l['field'])] + $l, $info['lines'])
                    : [...$mark($header, 'shared-header'), ...$mark($info['lines'], 'page'), ...$mark($footer, 'shared-footer')];
                $pages[] = $this->pageEntry($page, $locale, $name, $lines);
            }
            foreach (self::EXTRA_PAGES as $page) {
                $pages[] = $this->pageEntry($page, $locale, null, [...$mark($header, 'shared-header'), ...$mark($footer, 'shared-footer')]);
            }
        }
        if ($onlyPages !== null) {
            $pages = array_values(array_filter($pages, fn ($p) => in_array($p['slug'], $onlyPages, true)));
        }

        if ($this->option('json')) {
            $out = [
                'source' => 'php artisan summit:docx-black --json (DocxReader + the summit:import mappers, nothing written)',
                'locales' => array_values($locales),
                'rules' => array_map(fn ($r) => ['id' => $r[0], 'kind' => $r[1], 'field' => $r[2], 'group' => $r[3], 'why' => $r[4]], self::RULES),
                'excluded' => [['file' => '00_Site_Map*', 'why' => 'The link registry and release checks (destinations, link IDs, registry labels); not a page. Its black lines still count as approved copy wherever they appear.']],
                'pending' => [
                    'show' => \App\Design\D2\Pending::visible(),
                    'homeBlocks' => config('design.d2.home.blocks'),
                ],
                'pages' => $pages,
            ];
            if (! $this->option('no-registry')) {
                $out['registry'] = $this->registry($locales, $approved, $hosts);
            }
            $this->output->writeln(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        if ($onlyPages !== null && count($pages) <= 3) {
            foreach ($pages as $page) {
                $this->line("<info>{$page['locale']} {$page['slug']}</info> {$page['url']} ({$page['file']})");
                foreach ($page['lines'] as $l) {
                    $this->output->writeln(sprintf('%5s %-13s %-17s %-44s %s', $l['i'] ?? '', $l['part'], $l['kind'].($l['group'] ? ':'.$l['group'] : ''), mb_strimwidth((string) $l['field'], 0, 44), mb_strimwidth($l['text'], 0, 120, '…')), OutputInterface::OUTPUT_RAW);
                }
            }

            return self::SUCCESS;
        }
        $rows = [];
        foreach ($pages as $page) {
            $kinds = array_count_values(array_map(fn ($l) => $l['kind'], $page['lines']));
            ksort($kinds);
            $rows[] = [$page['locale'], $page['slug'], $page['url'], $page['file'] ?? '(none)', count($page['lines']), implode(', ', array_map(fn ($k, $n) => "{$k} {$n}", array_keys($kinds), $kinds))];
        }
        $this->table(['Locale', 'Page', 'URL', 'DOCX', 'Lines', 'Kinds'], $rows);

        return self::SUCCESS;
    }

    /** @return array{slug: string, code: string, path: string, status: int}|null */
    private function pageFor(string $filename): ?array
    {
        foreach (self::PAGES as $prefix => $page) {
            if (str_starts_with($filename, $prefix)) {
                return $page;
            }
        }
        if (preg_match('/^02_(S\d\d)_/', $filename, $m)) {
            $code = $m[1];
            $slug = config('summit.services.'.$code);
            if (is_string($slug)) {
                return ['slug' => strtolower($code).'-'.$slug, 'code' => $code, 'path' => 'practice-areas/'.$slug.'/', 'status' => 200];
            }
        }
        throw new \RuntimeException("No page mapping for {$filename}: add it to DocxBlack::PAGES");
    }

    private function partOf(?string $field): string
    {
        if ($field !== null && preg_match(self::SHARED_HEADER, $field)) {
            return 'header';
        }
        if ($field !== null && preg_match(self::SHARED_FOOTER, $field)) {
            return 'footer';
        }

        return 'page';
    }

    /** @return array<string, mixed> */
    private function blackLine(int $index, string $text, ?string $field, ?int $heading, bool $bullet, ?int $number, string $file): array
    {
        $kind = 'visible';
        $rule = null;
        $group = null;
        foreach (self::RULES as [$id, $k, $pattern, $g]) {
            if ($field !== null && preg_match($pattern, $field)) {
                [$kind, $rule, $group] = [$k, $id, $g];
                break;
            }
        }

        return [
            'file' => $file,
            'i' => $index,
            'text' => $text,
            'field' => $field,
            'kind' => $kind,
            'rule' => $rule,
            'group' => $group,
            'tag' => $heading !== null ? 'h'.$heading : ($bullet ? 'li' : ($number !== null ? 'ol' : 'p')),
            'number' => $number,
        ];
    }

    /** @return array<string, mixed> */
    private function pageEntry(array $page, string $locale, ?string $file, array $lines): array
    {
        return [
            'slug' => $page['slug'],
            'code' => $page['code'],
            'locale' => $locale,
            'url' => '/'.$locale.'/'.$page['path'],
            'status' => $page['status'],
            'file' => $file,
            'pending' => $page['pending'] ?? false,
            'lines' => array_values($lines),
        ];
    }

    /**
     * Strings the page may show besides its own DOCX lines, per locale: approved copy of any DOCX, the imported
     * content and interface strings (database/content), Laravel lang files, and proposed copy (lang proposed*.php).
     *
     * @param  list<string>  $locales
     * @param  array<string, array<string, true>>  $approved
     * @param  array<string, array<string, true>>  $hosts  the host names of the DOCX hyperlinks (shown beside a link)
     * @return array<string, array<string, list<string>>>
     */
    private function registry(array $locales, array $approved, array $hosts): array
    {
        $out = [];
        foreach ($locales as $locale) {
            $ui = [];
            $content = [];
            foreach (glob(database_path('content/*.json')) ?: [] as $file) {
                $data = json_decode((string) file_get_contents($file), true) ?: [];
                if (basename($file) === 'ui_strings.json') {
                    self::collect($data, $locale, $ui);
                } else {
                    self::collect($data, $locale, $content);
                }
            }
            $lang = [];
            $proposed = [];
            foreach (File::allFiles(lang_path($locale)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                $isProposed = str_starts_with($relative, 'proposed');
                if (str_starts_with($relative, 'proposed-d2/')) {
                    continue; // read through proposed.php ('d2' => every proposed-d2 file)
                }
                $data = require $file->getPathname();
                if (! is_array($data)) {
                    continue;
                }
                array_walk_recursive($data, function ($value) use (&$lang, &$proposed, $isProposed) {
                    if (is_string($value) && trim($value) !== '') {
                        $isProposed ? $proposed[$value] = true : $lang[$value] = true;
                    }
                });
            }
            $out[$locale] = [
                'approved' => array_map('strval', array_keys($approved[$locale] ?? [])),
                'hosts' => array_map('strval', array_keys($hosts[$locale] ?? [])),
                'ui' => array_map('strval', array_keys($ui)),
                'content' => array_map('strval', array_keys($content)),
                'lang' => array_map('strval', array_keys($lang)),
                'proposed' => array_map('strval', array_keys($proposed)),
            ];
        }

        return $out;
    }

    /**
     * String leaves of a translatable content structure for one locale: values under that locale's key (at any depth)
     * and language-neutral leaves (slugs, domains); never the values of the other locales.
     *
     * @param  array<string, true>  $into
     */
    private static function collect(mixed $value, string $locale, array &$into): void
    {
        if (is_string($value)) {
            if (trim($value) !== '') {
                $into[$value] = true;
            }

            return;
        }
        if (! is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, ImportRunner::LOCALES, true) && $key !== $locale) {
                continue;
            }
            self::collect($item, $locale, $into);
        }
    }
}
