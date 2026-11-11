<?php

namespace App\Content\Cms;

use App\Content\Import\ContentSchema;
use App\Content\Import\ImportException;
use App\Design\D2\Docs;
use Illuminate\Support\Arr;

/**
 * Today's sources as CMS rows (docs/briefs/design-2.1-and-admin-cms.md §8.8 step 2; the Phase 0 text-source maps in
 * docs/d2.1/phase-0/text-sources-*.md):
 *
 *   database/content/*.json            the DOCX import (php artisan summit:import): records, interface strings
 *                                      (ui_strings.json, copy_source docx), contacts, WA01 drafts, the site-map link
 *                                      registry, SEO titles and descriptions
 *   lang/{en,ar,ru}/proposed.php       proposed copy of both designs (incl. proposed-d2/*.php): interface text,
 *   lang/{en,ar,ru}/switcher.php       copy_source pending; the Anatomy parts go to document_samples instead
 *   config/summit.php, config/design.php   office hours and WhatsApp number, the "Start your matter" quick picks,
 *                                      the team portrait focal points (the home blocks' defaults: HomeBlocks)
 *   resources/js/d2/tools/*.js         the calculators' constants (rule set version 1)
 *
 * build() returns one row per record of every table, with JSON columns as PHP arrays. The importer
 * (App\Content\Cms\ContentImporter) writes them; App\Content\ContentRepository reads them directly while a table is
 * still empty (the file fallback of §8.6). Nothing here is invented: every text is copied from a source file.
 */
final class Sources
{
    /** pages.key (brief §8.4) for each page code. */
    public const PAGE_KEYS = [
        'P01' => 'home', 'P02' => 'services', 'P03' => 'resources', 'P04' => 'case_studies', 'P05' => 'faq', 'P06' => 'about',
        'P07' => 'contact', 'P08' => 'legal_notice', 'L01' => 'privacy', 'L02' => 'terms', 'U01' => 'not_found',
    ];

    /** Interface-text groups imported from lang/{locale}/{group}.php (copy_source pending). */
    public const LANG_GROUPS = ['proposed', 'switcher'];

    /** Interface-text keys that live in their own table instead (document_samples). */
    public const LANG_EXCLUDE = ['proposed' => ['d2.anatomy.fields']];

    /** The date rule set version 1 took effect on this site (the calculators page of Phase 2); see the Phase 3 report. */
    public const RULES_EFFECTIVE_FROM = '2026-11-10';

    /** Calculator constants copied into rule set version 1 (resources/js/d2/tools/{calculator}.js). */
    private const RULES = [
        'gratuity' => ['YEAR_DAYS', 'MONTH_DAYS', 'FIRST_YEARS', 'DAYS_FIRST_YEARS', 'DAYS_LATER_YEARS', 'CAP_MONTHS', 'NOTICE_MIN', 'NOTICE_MAX', 'SCOPES', 'IN_SCOPE', 'CONTRACTS'],
        'rent' => ['NOTICE_DAYS', 'BANDS'],
    ];

    /**
     * Menus (brief §8.2 Navigation). Each item: [link_key, label source, target_type, target, options]. The label source
     * is a ui_strings key (approved copy), or null for the target record's own label. link_key is the site-map key
     * (00_Site_Map: H-L001 …); null where the site map has none (the mega menu and the footer's practice areas list the
     * practice-area records).
     */
    private const MENUS = [
        'primary' => ['Primary navigation (header row and menu sheet)', [
            ['H-L001', 'nav.home', 'page', 'P01', ['nav' => 'home']],
            ['H-L002', 'nav.practice_areas', 'page', 'P02', ['nav' => 'practice', 'mega' => true]],
            ['H-L003', 'nav.resources', 'page', 'P03', ['nav' => 'resources']],
            ['H-L004', 'nav.case_studies', 'page', 'P04', ['nav' => 'cases']],
            ['H-L005', 'nav.faq', 'page', 'P05', ['nav' => 'faq']],
            ['H-L006', 'nav.about', 'page', 'P06', ['nav' => 'about']],
            ['H-L007', 'nav.contact', 'page', 'P07', ['nav' => 'contact']],
        ]],
        'actions' => ['Header call to action', [
            ['H-L011', 'cta.request_consultation', 'anchor', 'A01', []],
        ]],
        'mega' => ['Practice Areas mega menu and services sheet', 'services'],
        'footer_practice' => ['Footer: Practice Areas', 'services'],
        'footer_resources' => ['Footer: Legal Resources', [
            ['H-L043', 'footer.resources_centre', 'page', 'P03', []],
            ['H-L044', 'footer.faq', 'page', 'P05', []],
            ['H-L045', 'footer.case_studies', 'page', 'P04', []],
        ]],
        'footer_useful' => ['Footer: Useful Links', [
            ['H-L046', 'footer.about', 'page', 'P06', []],
            ['H-L047', 'footer.contact', 'page', 'P07', []],
            ['H-L048', 'footer.legal_notice', 'page', 'P08', []],
            ['H-L049', 'footer.privacy', 'page', 'L01', []],
            ['H-L050', 'footer.terms', 'page', 'L02', []],
        ]],
        'footer_contact' => ['Footer: contact channels', [
            ['H-L040', null, 'tel', null, ['channel' => 'TEL']],
            ['H-L041', 'footer.whatsapp', 'whatsapp', null, ['channel' => 'WA01']],
            ['H-L042', null, 'email', null, ['channel' => 'EMAIL']],
        ]],
        'footer_specialist' => ['Footer: Specialist Resources and Services Associated with Summit', 'sites'],
        'tabbar' => ['Mobile bar', [
            ['H-L001', 'nav.home', 'page', 'P01', ['nav' => 'home']],
            ['H-L002', 'nav.practice_areas', 'page', 'P02', ['nav' => 'practice', 'sheet' => 'services']],
            ['H-L003', 'nav.resources', 'page', 'P03', ['nav' => 'resources']],
            ['H-L007', 'nav.contact', 'page', 'P07', ['nav' => 'contact']],
        ]],
    ];

    /** @var array<string, array<string, mixed>> process memo: sources hash => build() */
    private static array $memo = [];

    public function __construct(
        private readonly ?string $contentDir = null,
        private readonly ?string $langDir = null,
    ) {}

    /**
     * Every row, built once per process for the current sources.
     *
     * @return array{records: array<string, array<string, array<string, mixed>>>, ui_strings: list<array<string, mixed>>, situations: list<array<string, mixed>>, document_samples: list<array<string, mixed>>, menus: list<array<string, mixed>>, settings: array<string, array<string, mixed>>, calculator_rule_sets: list<array<string, mixed>>, notes: list<string>}
     */
    public function build(): array
    {
        return self::$memo[$this->hash()] ??= $this->fresh();
    }

    /** A hash of every source file (path, size, modification time) and of the config values read. */
    public function hash(): string
    {
        $parts = [];
        foreach ($this->files() as $file) {
            $parts[] = $file.':'.@filesize($file).':'.@filemtime($file);
        }
        $parts[] = json_encode([config('summit.contact'), config('design.d2.home.quick_picks'), config('design.d2.team.portraits')]);

        return md5(implode('|', $parts));
    }

    /** @return list<string> */
    public function files(): array
    {
        $files = glob($this->contentDir().'/*.json') ?: [];
        foreach (Tree::LOCALES as $locale) {
            foreach (self::LANG_GROUPS as $group) {
                $files[] = $this->langDir()."/{$locale}/{$group}.php";
            }
            foreach (glob($this->langDir()."/{$locale}/proposed-d2/*.php") ?: [] as $file) {
                $files[] = $file;
            }
        }
        foreach (array_keys(self::RULES) as $calculator) {
            $files[] = resource_path("js/d2/tools/{$calculator}.js");
        }
        sort($files);

        return $files;
    }

    /** @return array<string, mixed> */
    private function fresh(): array
    {
        $notes = [];
        $data = [];
        foreach (array_keys(Schema::DATASETS) as $dataset) {
            $data[$dataset] = $this->json($dataset);
        }
        $ui = $this->json('ui_strings');
        $settingsJson = $this->json('settings');

        $records = [];
        $situations = [];
        foreach (Schema::DATASETS as $dataset => $schema) {
            foreach ($data[$dataset] as $code => $fields) {
                $code = (string) $code;
                [$row, $derived] = $this->row($dataset, $code, (array) $fields + $this->registryDefaults($dataset, $code), $notes);
                $records[$dataset][$code] = $row;
                if (isset($derived['situations'])) {
                    array_push($situations, ...$this->situations($code, $derived['situations']));
                }
            }
        }

        return [
            'records' => $records,
            'ui_strings' => [...$this->uiRows($ui), ...$this->langRows()],
            'situations' => $situations,
            'document_samples' => $this->documentSamples(),
            'menus' => $this->menus($ui, $records, $settingsJson['link_registry'] ?? []),
            'settings' => $this->settings($settingsJson),
            'calculator_rule_sets' => $this->ruleSets(),
            'notes' => $notes,
        ];
    }

    // ------------------------------------------------------------------------------------------- records

    /**
     * One table row of a content record, and the fields that live elsewhere (services.situations).
     *
     * @param  array<string, mixed>  $fields
     * @param  list<string>  $notes
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function row(string $dataset, string $code, array $fields, array &$notes): array
    {
        $schema = Schema::DATASETS[$dataset];
        $row = ['code' => $code];
        $derived = [];
        $pageFields = [];
        $blocksField = null;
        foreach ($schema['plain'] as $column) {
            $row[$column] = null;
        }
        foreach ($fields as $field => $value) {
            if (in_array($field, $schema['plain'], true) || in_array($field, $schema['json'], true)) {
                $row[$field] = $value;
            } elseif ($field === $schema['blocks']) {
                $blocksField = self::byLocale($value);
            } elseif (in_array($field, $schema['translatable'], true)) {
                $row[$field] = self::byLocale($value) ?: null;
            } elseif (in_array($field, Schema::DERIVED[$dataset] ?? [], true)) {
                $derived[$field] = self::byLocale($value);
            } elseif ($dataset === 'pages' && ! ContentSchema::isPlain($dataset, $field)) {
                // Page-level fields that are not columns (the 404 page U01: code_label, text, actions, more_heading,
                // links) form the page's own block, read back at the top level of the record.
                $pageFields[$field] = self::byLocale($value);
            } else {
                throw new ImportException("Content import: {$dataset}.{$code}.{$field} has no column (add one in a migration and in App\\Content\\Cms\\Schema)");
            }
        }

        if ($dataset === 'pages') {
            $row['key'] = self::PAGE_KEYS[$code] ?? null;
        }
        if ($dataset === 'team_members') {
            $portrait = config('design.d2.team.portraits.'.$code);
            $focus = is_array($portrait) ? ($portrait['focus'] ?? null) : null;
            $row['focal_point'] = is_array($focus) && count($focus) === 2 ? array_values(array_map('floatval', $focus)) : null;
            $row['photo_pending'] = (bool) (is_array($portrait) ? ($portrait['photo_pending'] ?? false) : false);
        }
        if ($dataset === 'specialist_sites') {
            $row['practice_area'] = $this->siteService($code);
            $row['enabled'] = true;
        }
        if ($schema['blocks'] !== null) {
            $row['blocks'] = $this->blocks($dataset, $code, $blocksField, $pageFields, $notes);
        }
        $row['status'] = Status::PUBLISHED;
        $row['copy_source'] = CopySource::DOCX;

        return [$row, $derived];
    }

    /** The practice area whose page links to this specialist site (services.specialist), if any. */
    private function siteService(string $site): ?string
    {
        foreach ($this->json('services') as $code => $service) {
            if (($service['specialist'] ?? null) === $site) {
                return (string) $code;
            }
        }

        return null;
    }

    /**
     * Blocks of a record (brief §8.4): one structure for every language, texts as {en, ar, ru} inside.
     *
     * @param  array<string, mixed>|null  $byLocale  the record's content / sections / body per language
     * @param  array<string, array<string, mixed>>  $pageFields  page-level fields per language (U01)
     * @param  list<string>  $notes
     * @return list<array<string, mixed>>
     */
    private function blocks(string $dataset, string $code, ?array $byLocale, array $pageFields, array &$notes): array
    {
        $blocks = [];
        if ($pageFields !== []) {
            $data = [];
            $locales = [];
            foreach ($pageFields as $field => $values) {
                if ($values === []) {
                    continue;
                }
                $data[$field] = Tree::transpose($values, "{$dataset}.{$code}.{$field}", $field);
                if (count($values) < count(Tree::LOCALES)) {
                    $locales[$field] = array_keys($values);
                }
            }
            $blocks[] = $this->block($code, $code === 'U01' ? 'not_found' : 'page', 'page', $data, $locales, ['into' => 'page']);
        }
        if ($byLocale === null || $byLocale === []) {
            return $blocks;
        }

        $tree = Tree::transpose($byLocale, "{$dataset}.{$code}.".Schema::DATASETS[$dataset]['blocks']);
        $partial = count($byLocale) < count(Tree::LOCALES) ? array_keys($byLocale) : null;
        $keys = is_array($tree) && ! array_is_list($tree) ? array_keys($tree) : [];
        $locales = fn (array $fields) => $partial === null ? [] : array_fill_keys($fields, $partial);

        if ($dataset === 'pages' && $code === HomeBlocks::PAGE) {
            $used = [];
            foreach (HomeBlocks::BLOCKS as $def) {
                $fields = array_values(array_filter($def['keys'], fn ($k) => array_key_exists($k, $tree)));
                $used = [...$used, ...$fields];
                $blocks[] = $this->block($code, $def['type'], $def['type'], array_intersect_key($tree, array_flip($fields)), $locales($fields), [
                    'enabled' => $this->homeEnabled($def),
                    'anchor' => $def['anchor'],
                    'background' => $def['background'],
                    'copy_source' => $def['copy_source'],
                ]);
            }
            foreach (array_diff($keys, $used) as $key) {
                $notes[] = "P01 content key '{$key}' has no home block type: imported as a 'section' block that the Design 2 home does not render";
                $blocks[] = $this->block($code, 'section', $key, [$key => $tree[$key]], $locales([$key]), ['enabled' => true]);
            }
            $order = array_merge(...array_map(fn ($b) => array_keys($b['data']), $blocks));
            if ($order !== array_values(array_filter($keys, fn ($k) => in_array($k, $order, true)))) {
                $notes[] = 'P01 content keys are not in the home block order: the record keys follow the blocks';
            }

            return $blocks;
        }

        if ($keys === []) {
            // A body that is not a group of sections: one block holding it whole.
            $field = Schema::DATASETS[$dataset]['blocks'];
            $notes[] = "{$dataset}.{$code}.{$field} is not a group of sections: one block";

            return [...$blocks, $this->block($code, 'section', $field, [$field => $tree], $locales([$field]), ['into' => 'self'])];
        }
        foreach ($keys as $key) {
            $blocks[] = $this->block($code, 'section', (string) $key, [$key => $tree[$key]], $locales([$key]), []);
        }

        return $blocks;
    }

    /** The default switch of a home block (HomeBlocks; config('design.d2.home.blocks') before Phase 3). */
    private function homeEnabled(array $def): bool
    {
        return (bool) $def['enabled'];
    }

    /**
     * @param  array<string, mixed>  $data  content key => transposed tree
     * @param  array<string, list<string>>  $locales  content key => the languages it exists in (only when not all)
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function block(string $code, string $type, string $key, array $data, array $locales, array $attributes): array
    {
        return [
            'id' => $code.'.'.$key,
            'type' => $type,
            'enabled' => $attributes['enabled'] ?? true,
            'anchor' => $attributes['anchor'] ?? null,
            'background' => $attributes['background'] ?? 'white',
            'copy_source' => $attributes['copy_source'] ?? CopySource::DOCX,
            'into' => $attributes['into'] ?? 'field',
            'data' => $data,
            'locales' => $locales,
            'limits' => Tree::limits($data),
        ];
    }

    /**
     * Slugs, anchors and positions come from config/summit.php, not from the copy (as ContentSeeder did before Phase 3).
     *
     * @return array<string, mixed>
     */
    private function registryDefaults(string $dataset, string $code): array
    {
        return match ($dataset) {
            'pages' => [
                'slug' => config("summit.pages.{$code}.path"),
                'position' => array_search($code, array_keys((array) config('summit.pages')), true) ?: 0,
            ],
            'services' => [
                'slug' => config("summit.services.{$code}"),
                'position' => (int) substr($code, 1),
            ],
            'guides' => [
                'anchor' => config("summit.guides.{$code}"),
                'position' => (int) substr($code, 1),
            ],
            'case_studies', 'faq_categories', 'faqs' => [
                'anchor' => $code,
                'position' => (int) preg_replace('/\D/', '', $code),
            ],
            'guide_categories' => ['position' => (int) preg_replace('/\D/', '', $code)],
            'team_members' => ['anchor' => $code, 'position' => 0],
            default => [],
        };
    }

    /**
     * Situations of a practice area: the approved P02 lines, code = practice area + 1-based position (S05-2). The
     * "Start your matter" quick picks (config design.d2.home.quick_picks until Phase 3) are the featured ones.
     *
     * @param  array<string, list<string>>  $byLocale
     * @return list<array<string, mixed>>
     */
    private function situations(string $service, array $byLocale): array
    {
        $lists = array_map(fn ($list) => array_values((array) $list), $byLocale);
        $count = max(array_map('count', $lists) ?: [0]);
        foreach ($lists as $locale => $list) {
            if (count($list) !== $count) {
                throw new ImportException("Content import: services.{$service}.situations has ".count($list)." lines in {$locale}, {$count} in another language");
            }
        }
        $picks = array_values(array_filter((array) config('design.d2.home.quick_picks', []), 'is_string'));
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $code = $service.'-'.($i + 1);
            $featured = array_search($code, $picks, true);
            $rows[] = [
                'code' => $code,
                'practice_area' => $service,
                'position' => $i + 1,
                'title' => array_map(fn ($list) => $list[$i], $lists),
                'documents' => null,
                'first_steps' => null,
                'mistakes' => null,
                'featured' => $featured !== false,
                'featured_position' => $featured === false ? null : $featured + 1,
                'status' => Status::PUBLISHED,
                'copy_source' => CopySource::DOCX,
            ];
        }

        return $rows;
    }

    // ------------------------------------------------------------------------------------------- interface text

    /**
     * The approved interface strings of the DOCX files (ui(): navigation, form, controls), group "ui".
     *
     * @param  array<string, array<string, string|null>>  $ui
     * @return list<array<string, mixed>>
     */
    private function uiRows(array $ui): array
    {
        $rows = [];
        foreach ($ui as $key => $values) {
            $rows[] = [
                'group' => 'ui',
                'key' => (string) $key,
                'text' => self::byLocale($values),
                'copy_source' => CopySource::DOCX,
                'note' => 'database/content/ui_strings.json (summit:import)',
                'status' => Status::PUBLISHED,
            ];
        }

        return $rows;
    }

    /**
     * The proposed interface text of both designs (lang/{locale}/proposed.php with proposed-d2/*.php, switcher.php):
     * one row per leaf, in file order, copy_source pending.
     *
     * @return list<array<string, mixed>>
     */
    private function langRows(): array
    {
        $rows = [];
        foreach (self::LANG_GROUPS as $group) {
            $byKey = [];
            foreach (Tree::LOCALES as $locale) {
                $file = $this->langDir()."/{$locale}/{$group}.php";
                $lines = is_file($file) ? (array) (static fn (string $f) => require $f)($file) : [];
                foreach (Arr::dot($lines) as $key => $text) {
                    if (is_array($text)) {
                        continue; // an empty group ([]): nothing to store, the file keeps it
                    }
                    foreach (self::LANG_EXCLUDE[$group] ?? [] as $prefix) {
                        if (str_starts_with((string) $key, $prefix.'.')) {
                            continue 2;
                        }
                    }
                    if (str_contains((string) $key, '..')) {
                        throw new ImportException("Content import: lang {$group} key '{$key}' contains an empty segment");
                    }
                    $byKey[(string) $key][$locale] = $text;
                }
            }
            foreach ($byKey as $key => $text) {
                $rows[] = [
                    'group' => $group,
                    'key' => $key,
                    'text' => $text,
                    'copy_source' => CopySource::PENDING,
                    'note' => $this->langNote($group, $key),
                    'status' => Status::PUBLISHED,
                ];
            }
        }

        return $rows;
    }

    private function langNote(string $group, string $key): string
    {
        if ($group === 'proposed' && str_starts_with($key, 'd2.')) {
            $area = explode('.', $key)[1] ?? '';

            return "lang/{locale}/proposed-d2/{$area}.php (Design 2 proposed copy)";
        }

        return "lang/{locale}/{$group}.php".($group === 'proposed' ? ' (Design 1 proposed copy)' : '');
    }

    // ------------------------------------------------------------------------------------------- collections

    /**
     * Anatomy of a Document: the five sample documents in the order of the home block, their parts (the illustration's
     * field ids, App\Design\D2\Docs::HOTSPOTS) with the proposed texts of lang proposed-d2/anatomy.php, the document
     * names of proposed-d2/docs.php and the service each one leads to. copy_source pending.
     *
     * @return list<array<string, mixed>>
     */
    private function documentSamples(): array
    {
        $anatomy = [];
        $docs = [];
        foreach (Tree::LOCALES as $locale) {
            $anatomy[$locale] = (array) (static fn (string $f) => require $f)($this->langDir()."/{$locale}/proposed-d2/anatomy.php");
            $docs[$locale] = (array) (static fn (string $f) => require $f)($this->langDir()."/{$locale}/proposed-d2/docs.php");
        }
        $rows = [];
        foreach (Docs::ANATOMY as $i => $type) {
            $parts = [];
            foreach (Docs::HOTSPOTS[$type] as $id) {
                $part = ['key' => $id];
                foreach (['label' => 'name', 'can_show' => 'show', 'we_check' => 'check'] as $field => $source) {
                    $part[$field] = self::byLocale(array_map(fn ($a) => $a['fields'][$type][$id][$source] ?? null, $anatomy));
                }
                $parts[] = $part;
            }
            $rows[] = [
                'code' => $type,
                'position' => $i + 1,
                'enabled' => true,
                'practice_area' => Docs::SERVICE[$type] ?? null,
                'title' => self::byLocale(array_map(fn ($d) => $d['name'][$type] ?? null, $docs)),
                'parts' => $parts,
                'status' => Status::PUBLISHED,
                'copy_source' => CopySource::PENDING,
            ];
        }

        return $rows;
    }

    /**
     * Navigation menus with their items (MENUS). Labels are copied from the approved interface strings; the site map's
     * own label of each link key is kept as registry_label.
     *
     * @param  array<string, array<string, string|null>>  $ui
     * @param  array<string, array<string, array<string, mixed>>>  $records
     * @param  array<string, array<string, string>|null>  $registry
     * @return list<array<string, mixed>>
     */
    private function menus(array $ui, array $records, array $registry): array
    {
        $registryLabel = function (?string $key) use ($registry): ?array {
            if ($key === null) {
                return null;
            }
            $labels = self::byLocale(array_map(fn ($list) => is_array($list) ? ($list[$key] ?? null) : null, $registry));

            return $labels ?: null;
        };
        $menus = [];
        $position = 0;
        foreach (self::MENUS as $key => [$name, $items]) {
            $rows = [];
            if ($items === 'services') {
                foreach (self::ordered($records['services'] ?? []) as $code => $service) {
                    $rows[] = [null, null, 'record', (string) $code, []];
                }
            } elseif ($items === 'sites') {
                foreach (self::ordered($records['specialist_sites'] ?? []) as $code => $site) {
                    $rows[] = [$this->siteLinkKey((string) $site['domain'], $registry), null, 'record', (string) $code, []];
                }
            } else {
                $rows = $items;
            }
            $out = [];
            foreach ($rows as $i => [$linkKey, $labelKey, $type, $target, $options]) {
                $out[] = [
                    'link_key' => $linkKey,
                    'label' => $labelKey === null ? null : (self::byLocale($ui[$labelKey] ?? []) ?: throw new ImportException("Content import: menu {$key} label ui:{$labelKey} is missing")),
                    'registry_label' => $registryLabel($linkKey),
                    'target_type' => $type,
                    'target' => $target,
                    'new_tab' => in_array($type, ['whatsapp'], true) || ($type === 'record' && str_starts_with((string) $target, 'X')),
                    'position' => $i + 1,
                    'enabled' => true,
                    'options' => $options ?: null,
                    'status' => Status::PUBLISHED,
                    'copy_source' => CopySource::DOCX,
                ];
            }
            $menus[] = [
                'key' => $key,
                'name' => $name,
                'description' => null,
                'position' => ++$position,
                'status' => Status::PUBLISHED,
                'copy_source' => CopySource::DOCX,
                'items' => $out,
            ];
        }

        return $menus;
    }

    /** The site-map link key whose label names this domain (H-L051 "Employment Law laborlawuae.com" → X01). */
    private function siteLinkKey(string $domain, array $registry): ?string
    {
        foreach ((array) ($registry['en'] ?? []) as $key => $label) {
            if ($domain !== '' && str_contains((string) $label, $domain)) {
                return (string) $key;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------------------------------- settings

    /**
     * Settings groups (spatie/laravel-settings, App\Settings\*): the contacts and the WA01 drafts of the DOCX import, the
     * office hours of config/summit.php, the Design 2 switches of config/design.php, the site-map link registry.
     *
     * @param  array<string, array<string, mixed>>  $json  database/content/settings.json
     * @return array<string, array<string, mixed>>
     */
    private function settings(array $json): array
    {
        $single = function (string $key) use ($json): string {
            $values = array_values(array_unique(array_filter((array) ($json[$key] ?? []), fn ($v) => $v !== null)));
            if (count($values) !== 1) {
                throw new ImportException("Content import: settings.{$key} must be the same in every language (found ".count($values).' values)');
            }

            return (string) $values[0];
        };
        $hours = (array) config('summit.contact.hours');
        $draft = self::byLocale($json['whatsapp_draft'] ?? []);
        $number = (string) config('summit.contact.whatsapp_number');
        foreach ($draft as $locale => $text) {
            $url = $json['whatsapp_url'][$locale] ?? null;
            if ($url !== null && $url !== 'https://wa.me/'.$number.'?text='.rawurlencode((string) $text)) {
                throw new ImportException("Content import: the WA01 link in {$locale} is not the WhatsApp number with the WA01 draft");
            }
        }

        return [
            'contact' => [
                'phone_display' => $single('phone_display'),
                'phone_href' => $single('phone_href'),
                'email' => $single('email'),
                'email_href' => $single('email_href'),
                'whatsapp_number' => $number,
            ],
            'whatsapp' => [
                'draft' => $draft,
            ],
            'hours' => [
                'timezone' => (string) config('summit.contact.timezone', 'Asia/Dubai'),
                'days' => array_values(array_map('intval', (array) ($hours['days'] ?? [1, 2, 3, 4, 5]))),
                'open' => (string) ($hours['open'] ?? '09:00'),
                'close' => (string) ($hours['close'] ?? '18:00'),
                'closures' => [],
                'live_status' => true,
                'review_target_hours' => null,
            ],
            'languages' => [
                'arabic_digits' => 'western',
            ],
            'content' => [
                'show_pending' => null,
            ],
            'site_map' => [
                'link_registry' => array_filter((array) ($json['link_registry'] ?? []), fn ($v) => $v !== null),
            ],
        ];
    }

    // ------------------------------------------------------------------------------------------- calculators

    /** @return list<array<string, mixed>> */
    private function ruleSets(): array
    {
        $tools = [];
        foreach (Tree::LOCALES as $locale) {
            $tools[$locale] = (array) (static fn (string $f) => require $f)($this->langDir()."/{$locale}/proposed-d2/tools.php");
        }
        $rows = [];
        foreach (self::RULES as $calculator => $names) {
            $rows[] = [
                'calculator' => $calculator,
                'version' => 1,
                'effective_from' => self::RULES_EFFECTIVE_FROM,
                'rules' => self::jsConstants(resource_path("js/d2/tools/{$calculator}.js"), $names),
                'legal_basis' => self::byLocale(array_map(fn ($t) => $t[$calculator]['result']['references'] ?? null, $tools)),
                'status' => Status::PUBLISHED,
                'copy_source' => CopySource::PENDING,
            ];
        }

        return $rows;
    }

    /**
     * Exported constants of a calculator module: numbers, string lists and lists of plain objects
     * (export const BANDS = [{ upTo: 10, increase: 0 }, …];), as JSON values keyed by the constant name in snake case.
     *
     * @param  list<string>  $names
     * @return array<string, mixed>
     */
    public static function jsConstants(string $file, array $names): array
    {
        $js = (string) @file_get_contents($file);
        $out = [];
        foreach ($names as $name) {
            if (! preg_match('/export const '.$name.'\s*=\s*(.+?);\s*(?:\/\/[^\n]*)?\n/s', $js, $m)) {
                throw new ImportException("Content import: {$name} is not exported by {$file}");
            }
            $literal = (string) preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $m[1]);
            $literal = str_replace("'", '"', $literal);
            $literal = (string) preg_replace('/([{,]\s*)([A-Za-z_]\w*)\s*:/', '$1"$2":', $literal);
            $literal = (string) preg_replace('/,\s*([\]}])/', '$1', $literal);
            try {
                $out[strtolower($name)] = json_decode(trim($literal), true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new ImportException("Content import: {$name} in {$file} is not a plain literal");
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------- helpers

    /**
     * A field's values per language, without the languages that have none (null), in en, ar, ru order.
     *
     * @return array<string, mixed>
     */
    public static function byLocale(mixed $value): array
    {
        $out = [];
        foreach (Tree::LOCALES as $locale) {
            if (is_array($value) && array_key_exists($locale, $value) && $value[$locale] !== null) {
                $out[$locale] = $value[$locale];
            }
        }

        return $out;
    }

    /**
     * Rows in the order the site lists them: by position, then by code.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    public static function ordered(array $rows): array
    {
        uksort($rows, fn ($a, $b) => [(int) ($rows[$a]['position'] ?? 0), (string) $a] <=> [(int) ($rows[$b]['position'] ?? 0), (string) $b]);

        return $rows;
    }

    /** @return array<string, mixed> */
    private function json(string $dataset): array
    {
        $file = $this->contentDir().'/'.$dataset.'.json';

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
    }

    private function contentDir(): string
    {
        return $this->contentDir ?? database_path('content');
    }

    private function langDir(): string
    {
        return $this->langDir ?? lang_path();
    }
}
