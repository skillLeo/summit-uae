<?php

namespace App\Content;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Read API over the imported content. Everything is loaded once (cached forever, flushed by the
 * ContentSeeder) and resolved per language on access. There is no cross-language fallback:
 * a missing translation is a content defect and is shown as ⟦key⟧ outside production.
 */
final class ContentRepository
{
    private const CACHE_KEY = 'summit.content.v1';

    /** table => [translatable core columns, plain core columns] */
    private const TABLES = [
        'pages' => [['h1', 'seo_title', 'meta_description'], ['slug', 'position']],
        'services' => [['title'], ['slug', 'position']],
        'guides' => [['title'], ['anchor', 'category', 'position']],
        'guide_categories' => [['name'], ['position']],
        'case_studies' => [['title'], ['anchor', 'position']],
        'faq_categories' => [['name'], ['anchor', 'position']],
        'faqs' => [['question'], ['anchor', 'category', 'position']],
        'team_members' => [['name'], ['anchor', 'position', 'portrait']],
        'specialist_sites' => [['label'], ['position', 'domain', 'url']],
    ];

    /** @var array<string, mixed>|null */
    private ?array $raw = null;

    /** @var array<string, array<string, mixed>> memoised resolved records */
    private array $resolved = [];

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->raw = null;
        $this->resolved = [];
    }

    public function locale(?string $locale = null): string
    {
        return $locale ?? app()->getLocale();
    }

    // ------------------------------------------------------------------ strings

    public function ui(string $key, ?string $locale = null): string
    {
        $value = $this->raw()['ui_strings'][$key][$this->locale($locale)] ?? null;

        return is_string($value) && $value !== '' ? $value : $this->missing("ui:{$key}");
    }

    public function hasUi(string $key, ?string $locale = null): bool
    {
        return isset($this->raw()['ui_strings'][$key][$this->locale($locale)]);
    }

    public function setting(string $key, ?string $locale = null): mixed
    {
        $values = $this->raw()['settings'][$key] ?? null;
        if ($values === null) {
            return $this->missing("setting:{$key}");
        }

        return $values[$this->locale($locale)] ?? $values['en'] ?? null;
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

    /** @return list<array<string, mixed>> */
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

    /** @return list<array<string, mixed>> */
    public function all(string $table, ?string $locale = null): array
    {
        $rows = [];
        foreach (array_keys($this->raw()[$table] ?? []) as $code) {
            $rows[] = $this->record($table, (string) $code, $locale);
        }
        usort($rows, fn ($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function record(string $table, string $code, ?string $locale = null): ?array
    {
        $locale = $this->locale($locale);
        $memo = "{$table}.{$code}.{$locale}";
        if (array_key_exists($memo, $this->resolved)) {
            return $this->resolved[$memo];
        }
        $row = $this->raw()[$table][$code] ?? null;
        if ($row === null) {
            return $this->resolved[$memo] = null;
        }
        $record = ['code' => $code] + $row['plain'] + ($row['meta'] ?? []);
        foreach ($row['tr'] as $column => $byLocale) {
            if ($column === 'data') {
                foreach (($byLocale[$locale] ?? []) as $field => $value) {
                    $record[$field] = $value;
                }
            } else {
                $record[$column] = $byLocale[$locale] ?? null;
            }
        }

        return $this->resolved[$memo] = $record;
    }

    // ------------------------------------------------------------------ loading

    /** @return array<string, mixed> */
    private function raw(): array
    {
        return $this->raw ??= Cache::rememberForever(self::CACHE_KEY, fn () => $this->load());
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        $out = [];
        foreach (self::TABLES as $table => [$translatable, $plain]) {
            foreach (DB::table($table)->get() as $row) {
                $tr = [];
                foreach ([...$translatable, 'data'] as $column) {
                    $tr[$column] = json_decode((string) ($row->{$column} ?? '{}'), true) ?: [];
                }
                $out[$table][$row->code] = [
                    'plain' => array_intersect_key((array) $row, array_flip($plain)),
                    'meta' => json_decode((string) ($row->meta ?? '[]'), true) ?: [],
                    'tr' => $tr,
                ];
            }
        }
        foreach (['ui_strings', 'settings'] as $table) {
            foreach (DB::table($table)->get() as $row) {
                $out[$table][$row->key] = json_decode((string) $row->value, true) ?: [];
            }
        }

        return $out;
    }

    private function missing(string $what): string
    {
        if (app()->isProduction()) {
            Log::warning("Missing content: {$what}");

            return '';
        }

        return '⟦'.$what.'⟧';
    }
}
