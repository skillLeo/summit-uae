<?php

namespace App\Content;

use App\Content\Cms\ContentCache;
use App\Content\Cms\CopySource;
use App\Content\Cms\Records;
use App\Content\Cms\Schema;
use App\Content\Cms\Sources;
use App\Content\Cms\Status;
use App\Content\Cms\Tree;
use App\Support\SiteUrl;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The one read API over the published content (docs/briefs/design-2.1-and-admin-cms.md §8.6): every text, link and
 * setting both designs show. Views use content(), ui(), setting(), proposed() and d2p().
 *
 * Source: the CMS tables (published rows, not in the Trash), filled by `php artisan summit:content-import`. While a
 * table is still empty, or a key is missing, the same content is read from today's files instead
 * (App\Content\Cms\Sources: database/content/*.json, the lang files, config), so the site never depends on the import
 * having run. Interface text (proposed(), d2p(), the switcher) reads the database through
 * spatie/laravel-translation-loader (App\Models\Content\UiString → lines()); the lang files fill the rest.
 *
 * Cache: one snapshot of the shared data (interface text, settings, menus, situations, document samples, rule sets) and
 * one of the records per language, stored under a versioned key (App\Content\Cms\ContentCache) that every content change
 * replaces. There is no cross-language fallback for content: a missing translation is a content defect and is shown as
 * ⟦key⟧ outside production.
 */
final class ContentRepository
{
    public const SOURCE_DATABASE = 'database';

    public const SOURCE_FILES = 'files';

    /** @var array<string, mixed>|null */
    private ?array $shared = null;

    /** @var array<string, array<string, mixed>> locale => records snapshot */
    private array $snapshots = [];

    private string $source = self::SOURCE_DATABASE;

    /**
     * Preview overlays (App\Http\Middleware\CmsPreview, brief §8.5): table => key column, the rows read instead of the
     * published ones, and optionally a scope (column => value) whose published rows they replace. Never cached.
     *
     * @var array<string, array{key: string, rows: list<array<string, mixed>>, scope?: array<string, mixed>}>
     */
    private array $overlays = [];

    /** Replaces the cache version (the next read rebuilds) and forgets what this instance memoised. */
    public function flush(): void
    {
        ContentCache::bump(flushRepository: false);
        $this->forget();
    }

    /** Forgets what this instance memoised (ContentCache::bump() calls it). */
    public function forget(): void
    {
        $this->shared = null;
        $this->snapshots = [];
    }

    /**
     * Where the content comes from: "database" (the default: the CMS tables, files where they are empty) or "files"
     * (today's files only, never cached; the parity test renders both and compares them).
     */
    public function useSource(string $source): void
    {
        $this->source = $source === self::SOURCE_FILES ? self::SOURCE_FILES : self::SOURCE_DATABASE;
        $this->forget();
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * Render with unpublished rows (a signed preview of a draft): see $overlays. The snapshot is then built for this
     * request only, never cached.
     *
     * @param  array<string, array{key: string, rows: list<array<string, mixed>>, scope?: array<string, mixed>}>  $overlays
     */
    public function preview(array $overlays): void
    {
        $this->overlays = $overlays;
        $this->forget();
    }

    public function previewing(): bool
    {
        return $this->overlays !== [];
    }

    public function locale(?string $locale = null): string
    {
        return $locale ?? app()->getLocale();
    }

    // ------------------------------------------------------------------ strings

    /** An approved interface string of the DOCX files (group "ui"). */
    public function ui(string $key, ?string $locale = null): string
    {
        $locale = $this->locale($locale);
        foreach (['ui', 'file_ui'] as $map) {
            $value = $this->shared()[$map][$key][$locale] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $this->missing("ui:{$key}");
    }

    public function hasUi(string $key, ?string $locale = null): bool
    {
        $locale = $this->locale($locale);

        return isset($this->shared()['ui'][$key][$locale]) || isset($this->shared()['file_ui'][$key][$locale]);
    }

    /**
     * Interface text of one translation group in one language, nested like a lang file (the published ui_strings rows;
     * spatie/laravel-translation-loader merges it over the lang files).
     *
     * @return array<string, mixed>
     */
    public function lines(string $group, string $locale): array
    {
        return $this->shared()['lines'][$group][$locale] ?? [];
    }

    /**
     * A setting by the name the views use (phone_display, phone_href, email, email_href, whatsapp_draft, whatsapp_url,
     * link_registry). The WA01 link is built from the WhatsApp number and the language's WA01 draft.
     */
    public function setting(string $key, ?string $locale = null): mixed
    {
        $locale = $this->locale($locale);
        $contact = $this->settings('contact');

        return match ($key) {
            'phone_display', 'phone_href', 'email', 'email_href' => $contact[$key] ?? $this->missing("setting:{$key}"),
            'whatsapp_number' => $contact['whatsapp_number'] ?? $this->missing("setting:{$key}"),
            'whatsapp_draft' => $this->whatsappDraft($locale),
            'whatsapp_url' => $this->whatsappUrl($locale),
            'link_registry' => (fn (array $r) => $r[$locale] ?? $r['en'] ?? null)((array) ($this->settings('site_map')['link_registry'] ?? [])),
            default => $this->missing("setting:{$key}"),
        };
    }

    /**
     * One settings group (App\Settings: contact, whatsapp, hours, languages, content, site_map): the stored values over
     * today's defaults.
     *
     * @return array<string, mixed>
     */
    public function settings(string $group): array
    {
        return $this->shared()['settings'][$group] ?? [];
    }

    public function whatsappUrl(?string $locale = null): ?string
    {
        $draft = $this->whatsappDraft($this->locale($locale));
        $number = (string) ($this->settings('contact')['whatsapp_number'] ?? '');
        if ($draft === null || $number === '') {
            return $this->missing('setting:whatsapp_url');
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($draft);
    }

    private function whatsappDraft(string $locale): ?string
    {
        $draft = (array) ($this->settings('whatsapp')['draft'] ?? []);

        return $draft[$locale] ?? $draft['en'] ?? null;
    }

    // ------------------------------------------------------------------ records

    /** @return array<string, mixed> */
    public function page(string $code, ?string $locale = null): array
    {
        return $this->record('pages', $code, $locale) ?? [];
    }

    /** @return list<array<string, mixed>> ordered by position */
    public function services(?string $locale = null): array
    {
        return $this->all('services', $locale);
    }

    /** @return array<string, mixed>|null */
    public function service(string $code, ?string $locale = null): ?array
    {
        return $this->record('services', $code, $locale);
    }

    /** @return list<array<string, mixed>> */
    public function guides(?string $locale = null): array
    {
        return $this->all('guides', $locale);
    }

    /** @return array<string, mixed>|null */
    public function guide(string $code, ?string $locale = null): ?array
    {
        return $this->record('guides', $code, $locale);
    }

    /** @return list<array<string, mixed>> */
    public function guideCategories(?string $locale = null): array
    {
        return $this->all('guide_categories', $locale);
    }

    /** @return array<string, mixed>|null */
    public function caseStudy(string $code, ?string $locale = null): ?array
    {
        return $this->record('case_studies', $code, $locale);
    }

    /** @return list<array<string, mixed>> the enabled associated sites (X01–X06) */
    public function specialistSites(?string $locale = null): array
    {
        return $this->all('specialist_sites', $locale);
    }

    /** @return list<array<string, mixed>> */
    public function teamMembers(?string $locale = null): array
    {
        return $this->all('team_members', $locale);
    }

    /** @return list<array<string, mixed>> */
    public function faqs(?string $locale = null): array
    {
        return $this->all('faqs', $locale);
    }

    /** @return list<array<string, mixed>> */
    public function faqCategories(?string $locale = null): array
    {
        return $this->all('faq_categories', $locale);
    }

    /** @return list<array<string, mixed>> every published record of a dataset, by position */
    public function all(string $dataset, ?string $locale = null): array
    {
        return array_values($this->snapshot($this->locale($locale))['records'][$dataset] ?? []);
    }

    /** @return array<string, mixed>|null */
    public function record(string $dataset, string $code, ?string $locale = null): ?array
    {
        return $this->snapshot($this->locale($locale))['records'][$dataset][$code] ?? null;
    }

    // ------------------------------------------------------------------ blocks, navigation, collections

    /**
     * The blocks of a page in one language (order, on/off, anchor, background, copy_source and the resolved data).
     *
     * @return list<array{id: string, type: string, enabled: bool, anchor: string|null, background: string, copy_source: string, data: array<string, mixed>}>
     */
    public function blocks(string $code, ?string $locale = null): array
    {
        return $this->snapshot($this->locale($locale))['blocks'][$code] ?? [];
    }

    /** The first block of a page with this type, or null. */
    public function block(string $code, string $type, ?string $locale = null): ?array
    {
        foreach ($this->blocks($code, $locale) as $block) {
            if ($block['type'] === $type) {
                return $block;
            }
        }

        return null;
    }

    /**
     * The enabled items of a navigation menu, resolved for the language: link_key, label (the item's own, else the
     * target record's), href, new_tab, target_type, target, options and, for a record target, the record.
     *
     * @return list<array{link_key: string|null, label: string|null, href: string, new_tab: bool, target_type: string, target: string|null, options: array<string, mixed>, record: array<string, mixed>|null}>
     */
    public function menu(string $key, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $out = [];
        foreach ($this->shared()['menus'][$key] ?? [] as $item) {
            if (! ($item['enabled'] ?? true)) {
                continue;
            }
            $type = (string) $item['target_type'];
            $target = $item['target'] !== null ? (string) $item['target'] : null;
            $record = $type === 'record' && $target !== null ? $this->targetRecord($target, $locale) : null;
            $label = is_array($item['label'] ?? null) ? ($item['label'][$locale] ?? null) : null;
            $label ??= match ($type) {
                'tel' => $this->setting('phone_display', $locale),
                'email' => $this->setting('email', $locale),
                default => $record['title'] ?? $record['label'] ?? null,
            };
            $out[] = [
                'link_key' => $item['link_key'] ?? null,
                'label' => $label,
                'href' => $this->href($type, $target, $locale, $record),
                'new_tab' => (bool) ($item['new_tab'] ?? false),
                'target_type' => $type,
                'target' => $target,
                'options' => (array) ($item['options'] ?? []),
                'record' => $record,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null the record a site-map id names (S06, X02, G04, case-01 …) */
    private function targetRecord(string $target, string $locale): ?array
    {
        $dataset = match (true) {
            (bool) preg_match('/^S\d{2}$/', $target) => 'services',
            (bool) preg_match('/^X\d{2}$/', $target) => 'specialist_sites',
            (bool) preg_match('/^G\d{2}$/', $target) => 'guides',
            (bool) preg_match('/^case-\d{2}$/', $target) => 'case_studies',
            (bool) preg_match('/^(P0\d|L0[12]|U01)$/', $target) => 'pages',
            default => null,
        };

        return $dataset === null ? null : $this->record($dataset, $target, $locale);
    }

    private function href(string $type, ?string $target, string $locale, ?array $record): string
    {
        return match ($type) {
            'tel' => (string) $this->setting('phone_href', $locale),
            'email' => (string) $this->setting('email_href', $locale),
            'whatsapp' => (string) $this->setting('whatsapp_url', $locale),
            'url' => (string) $target,
            default => $record !== null && isset($record['url']) && preg_match('/^X\d{2}$/', (string) $target)
                ? (string) $record['url']
                : SiteUrl::target((string) $target, $locale),
        };
    }

    /**
     * The situation codes of the "Start your matter" quick picks (situations.featured), in their order.
     *
     * @return list<string>
     */
    public function featuredSituations(): array
    {
        $featured = array_filter($this->shared()['situations'], fn (array $s) => (bool) ($s['featured'] ?? false));
        uasort($featured, fn (array $a, array $b) => [(int) ($a['featured_position'] ?? 0), $a['code']] <=> [(int) ($b['featured_position'] ?? 0), $b['code']]);

        return array_values(array_map(fn (array $s) => (string) $s['code'], $featured));
    }

    /**
     * The enabled sample documents of "Anatomy of a Document", in order, resolved for the language.
     *
     * @return list<array{code: string, title: string|null, practice_area: string|null, parts: list<array{key: string, label: string|null, can_show: string|null, we_check: string|null}>}>
     */
    public function documentSamples(?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $out = [];
        foreach ($this->shared()['samples'] as $sample) {
            if (! ($sample['enabled'] ?? true)) {
                continue;
            }
            $parts = [];
            foreach ((array) ($sample['parts'] ?? []) as $part) {
                $resolved = Tree::resolve($part, $locale);
                $parts[] = [
                    'key' => (string) ($part['key'] ?? ''),
                    'label' => is_array($resolved) ? ($resolved['label'] ?? null) : null,
                    'can_show' => is_array($resolved) ? ($resolved['can_show'] ?? null) : null,
                    'we_check' => is_array($resolved) ? ($resolved['we_check'] ?? null) : null,
                ];
            }
            $out[] = [
                'code' => (string) $sample['code'],
                'title' => $sample['title'][$locale] ?? null,
                'practice_area' => $sample['practice_area'] ?? null,
                'parts' => $parts,
            ];
        }

        return $out;
    }

    /**
     * Team portrait settings (brief §8.11: focal point, photo pending): member code => focal point [x, y] in % or null,
     * and the pending flag.
     *
     * @return array<string, array{focus: array{0: float, 1: float}|null, pending: bool}>
     */
    public function portraits(): array
    {
        return $this->shared()['portraits'];
    }

    /** The published calculator rule set in effect today (gratuity | rent), or null. */
    public function ruleSet(string $calculator): ?array
    {
        return $this->shared()['rules'][$calculator] ?? null;
    }

    // ------------------------------------------------------------------ loading

    /** @return array<string, mixed> */
    private function shared(): array
    {
        if ($this->shared !== null) {
            return $this->shared;
        }
        if ($this->source === self::SOURCE_FILES || $this->overlays !== []) {
            return $this->shared = $this->buildShared();
        }

        return $this->shared = Cache::rememberForever(ContentCache::key('shared'), fn () => $this->buildShared());
    }

    /** @return array<string, mixed> */
    private function snapshot(string $locale): array
    {
        if (isset($this->snapshots[$locale])) {
            return $this->snapshots[$locale];
        }
        if ($this->source === self::SOURCE_FILES || $this->overlays !== [] || ! in_array($locale, Tree::LOCALES, true)) {
            return $this->snapshots[$locale] = $this->buildSnapshot($locale);
        }

        return $this->snapshots[$locale] = Cache::rememberForever(ContentCache::key('records.'.$locale), fn () => $this->buildSnapshot($locale));
    }

    /** @return array<string, mixed> */
    private function buildShared(): array
    {
        $files = (new Sources)->build();

        $ui = [];
        $fileUi = [];
        $lines = [];
        foreach ($files['ui_strings'] as $row) {
            if ($row['group'] === 'ui') {
                $fileUi[$row['key']] = $row['text'];
            }
        }
        foreach ($this->dbRows('ui_strings', 'id') as $row) {
            $text = $this->decode($row['text']);
            if (! is_array($text)) {
                continue;
            }
            if ($row['group'] === 'ui') {
                $ui[$row['key']] = $text;

                continue;
            }
            foreach ($text as $locale => $value) {
                if (is_string($value)) {
                    $lines[$row['group']][$locale] ??= [];
                    Arr::set($lines[$row['group']][$locale], (string) $row['key'], $value);
                }
            }
        }

        $settings = $files['settings'];
        foreach ($this->tableRows('settings', fn ($q) => $q->orderBy('id')) as $row) {
            $settings[$row['group']][$row['name']] = $this->decode($row['payload']);
        }

        $menus = [];
        $menuRows = $this->dbRows('navigation_menus', 'id');
        if ($menuRows !== []) {
            $items = [];
            foreach ($this->dbRows('navigation_items', 'position') as $item) {
                $items[$item['menu_id']][] = $item;
            }
            foreach ($menuRows as $menu) {
                foreach ($items[$menu['id']] ?? [] as $item) {
                    if ($item['parent_id'] !== null) {
                        continue; // nested items: none yet (the admin adds them in Phase 4)
                    }
                    $menus[$menu['key']][] = [
                        'link_key' => $item['link_key'],
                        'label' => $this->decode($item['label']),
                        'target_type' => $item['target_type'],
                        'target' => $item['target'],
                        'new_tab' => (bool) $item['new_tab'],
                        'enabled' => (bool) $item['enabled'],
                        'options' => $this->decode($item['options']) ?? [],
                    ];
                }
            }
        } else {
            foreach ($files['menus'] as $menu) {
                $menus[$menu['key']] = $menu['items'];
            }
        }

        $situations = [];
        foreach ($this->dbRows('situations', 'position') ?: $files['situations'] as $row) {
            $row['title'] = $this->decode($row['title']);
            $situations[$row['code']] = $row;
        }
        uasort($situations, fn ($a, $b) => [$a['practice_area'], (int) $a['position']] <=> [$b['practice_area'], (int) $b['position']]);

        $samples = [];
        foreach ($this->dbRows('document_samples', 'position') ?: $files['document_samples'] as $row) {
            $row['title'] = $this->decode($row['title']);
            $row['parts'] = $this->decode($row['parts']) ?? [];
            $row['enabled'] = (bool) $row['enabled'];
            $samples[$row['code']] = $row;
        }
        uasort($samples, fn ($a, $b) => [(int) $a['position'], $a['code']] <=> [(int) $b['position'], $b['code']]);

        $rules = [];
        $today = now()->toDateString();
        foreach ($this->dbRows('calculator_rule_sets', 'version') ?: $files['calculator_rule_sets'] as $row) {
            if ((string) $row['effective_from'] > $today) {
                continue;
            }
            $row['rules'] = $this->decode($row['rules']);
            $row['legal_basis'] = $this->decode($row['legal_basis']);
            $current = $rules[$row['calculator']] ?? null;
            if ($current === null || (int) $row['version'] > (int) $current['version']) {
                $rules[$row['calculator']] = $row;
            }
        }

        $portraits = [];
        foreach ($this->datasetRows('team_members', $files) as $code => $row) {
            $focus = $this->decode($row['focal_point'] ?? null);
            $portraits[$code] = [
                'focus' => is_array($focus) && count($focus) === 2 ? [(float) $focus[0], (float) $focus[1]] : null,
                'pending' => (bool) ($row['photo_pending'] ?? false),
            ];
        }

        return [
            'ui' => $ui,
            'file_ui' => $fileUi,
            'lines' => $lines,
            'settings' => $settings,
            'menus' => $menus,
            'situations' => $situations,
            'samples' => array_values($samples),
            'rules' => $rules,
            'portraits' => $portraits,
        ];
    }

    /** @return array{records: array<string, array<string, array<string, mixed>>>, blocks: array<string, list<array<string, mixed>>>} */
    private function buildSnapshot(string $locale): array
    {
        $files = $this->source === self::SOURCE_FILES || $this->anyEmpty() ? (new Sources)->build() : null;
        $bySituation = [];
        foreach ($this->shared()['situations'] as $situation) {
            $bySituation[$situation['practice_area']][] = $situation;
        }

        $out = ['records' => [], 'blocks' => []];
        foreach (array_keys(Schema::DATASETS) as $dataset) {
            foreach (Sources::ordered($this->datasetRows($dataset, $files)) as $code => $row) {
                if ($dataset === 'specialist_sites' && ! ($row['enabled'] ?? true)) {
                    continue;
                }
                $out['records'][$dataset][$code] = Records::record($dataset, $row, $locale, $bySituation);
                if ($dataset === 'pages') {
                    $out['blocks'][$code] = Records::blocks((array) ($row['blocks'] ?? []), $locale);
                }
            }
        }

        return $out;
    }

    /**
     * The published rows of a dataset, decoded, keyed by code; today's files while the table has none.
     *
     * @param  array<string, mixed>|null  $files  Sources::build(), when already built
     * @return array<string, array<string, mixed>>
     */
    private function datasetRows(string $dataset, ?array $files = null): array
    {
        $rows = [];
        foreach ($this->dbRows(Schema::table($dataset), 'code') as $row) {
            foreach ([...Schema::jsonColumns($dataset), 'blocks'] as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = $this->decode($row[$column]);
                }
            }
            $rows[(string) $row['code']] = $row;
        }

        return $rows !== [] ? $rows : (($files ?? (new Sources)->build())['records'][$dataset] ?? []);
    }

    /** Whether any content dataset still reads from the files. */
    private function anyEmpty(): bool
    {
        foreach (array_keys(Schema::DATASETS) as $dataset) {
            if ($this->dbRows(Schema::table($dataset), 'code', limit: 1) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Published rows of a CMS table (not in the Trash); none in "files" mode or when the table does not exist yet.
     *
     * @return list<array<string, mixed>>
     */
    private function dbRows(string $table, string $order, ?int $limit = null): array
    {
        $rows = $this->tableRows($table, function ($query) use ($order, $limit) {
            $query->where('status', Status::PUBLISHED)->whereNull('deleted_at')->orderBy($order);
            if ($limit !== null) {
                $query->limit($limit);
            }
        });

        return isset($this->overlays[$table]) && $this->source !== self::SOURCE_FILES ? $this->overlaid($table, $rows, $order) : $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function overlaid(string $table, array $rows, string $order): array
    {
        $overlay = $this->overlays[$table];
        $key = $overlay['key'];
        $ids = array_map(fn (array $row) => (string) ($row[$key] ?? ''), $overlay['rows']);
        $scope = $overlay['scope'] ?? [];
        $rows = array_values(array_filter($rows, function (array $row) use ($key, $ids, $scope) {
            if (in_array((string) ($row[$key] ?? ''), $ids, true)) {
                return false;
            }
            foreach ($scope as $column => $value) {
                if ((string) ($row[$column] ?? '') !== (string) $value) {
                    return true;
                }
            }

            return $scope === [];
        }));
        $rows = [...$rows, ...$overlay['rows']];
        usort($rows, fn (array $a, array $b) => [$a[$order] ?? null, (string) ($a[$key] ?? '')] <=> [$b[$order] ?? null, (string) ($b[$key] ?? '')]);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function tableRows(string $table, callable $scope): array
    {
        if ($this->source === self::SOURCE_FILES) {
            return [];
        }
        try {
            $query = DB::table($table);
            $scope($query);

            return $query->get()->map(fn ($row) => (array) $row)->all();
        } catch (QueryException $e) {
            // Before `php artisan migrate`: the files are the content.
            return [];
        }
    }

    private function decode(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    private function missing(string $what): string
    {
        if (app()->isProduction()) {
            Log::warning("Missing content: {$what}");

            return '';
        }

        return '⟦'.$what.'⟧';
    }

    /** @internal for the importer's report and the tests: the copy_source values in use */
    public static function copySources(): array
    {
        return CopySource::ALL;
    }
}
