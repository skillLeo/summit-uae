<?php

namespace Tests\Support;

use App\Content\Import\ContentSchema;

/**
 * The content as the site read it before Phase 3, rebuilt from database/content/*.json without a database: the
 * ContentSeeder of commit d3e1481 (registry defaults, plain / meta / translatable / data split, nulls dropped) and
 * ContentRepository::record() of the same commit (code, plain columns, meta, translatable core columns with null when
 * missing, then the per-language data fields). The parity test compares every CMS record with it.
 */
final class LegacyContent
{
    /** dataset => [translatable core columns, plain core columns] (the models and ContentRepository::TABLES of d3e1481) */
    public const TABLES = [
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

    /** @var array<string, mixed> */
    private array $raw = [];

    public function __construct(?string $dir = null)
    {
        $dir ??= database_path('content');
        $read = fn (string $name) => is_file("{$dir}/{$name}.json") ? json_decode((string) file_get_contents("{$dir}/{$name}.json"), true, 512, JSON_THROW_ON_ERROR) : [];
        foreach (self::TABLES as $dataset => [$translatable, $plain]) {
            foreach ($read($dataset) as $code => $fields) {
                $this->raw[$dataset][(string) $code] = $this->seed($dataset, (string) $code, (array) $fields, $translatable, $plain);
            }
        }
        foreach (['ui_strings', 'settings'] as $flat) {
            foreach ($read($flat) as $key => $values) {
                $this->raw[$flat][$key] = array_filter((array) $values, fn ($v) => $v !== null);
            }
        }
    }

    /** @return list<string> */
    public function codes(string $dataset): array
    {
        return array_map('strval', array_keys($this->raw[$dataset] ?? []));
    }

    /** @return array<string, mixed>|null */
    public function record(string $dataset, string $code, string $locale): ?array
    {
        $row = $this->raw[$dataset][$code] ?? null;
        if ($row === null) {
            return null;
        }
        $record = ['code' => $code] + $row['plain'] + $row['meta'];
        foreach ($row['tr'] as $column => $byLocale) {
            if ($column === 'data') {
                foreach (($byLocale[$locale] ?? []) as $field => $value) {
                    $record[$field] = $value;
                }
            } else {
                $record[$column] = $byLocale[$locale] ?? null;
            }
        }

        return $record;
    }

    /** @return list<array<string, mixed>> ordered by position (stable, then in table order: the code) */
    public function all(string $dataset, string $locale): array
    {
        $codes = $this->codes($dataset);
        sort($codes, SORT_STRING);
        $rows = array_map(fn ($code) => $this->record($dataset, $code, $locale), $codes);
        usort($rows, fn ($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return $rows;
    }

    /** @return list<string> */
    public function uiKeys(): array
    {
        return array_keys($this->raw['ui_strings'] ?? []);
    }

    public function ui(string $key, string $locale): ?string
    {
        $value = $this->raw['ui_strings'][$key][$locale] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setting(string $key, string $locale): mixed
    {
        $values = $this->raw['settings'][$key] ?? null;

        return $values === null ? null : ($values[$locale] ?? $values['en'] ?? null);
    }

    /**
     * ContentSeeder::insert() of d3e1481, without the database round trip (JSON text decodes to the same arrays).
     *
     * @param  list<string>  $translatable
     * @param  list<string>  $plain
     * @return array{plain: array<string, mixed>, meta: array<string, mixed>, tr: array<string, array<string, mixed>>}
     */
    private function seed(string $dataset, string $code, array $fields, array $translatable, array $plain): array
    {
        $fields += $this->registryDefaults($dataset, $code);
        $row = ['plain' => array_fill_keys($plain, null), 'meta' => [], 'tr' => array_fill_keys([...$translatable, 'data'], [])];
        foreach ($fields as $field => $value) {
            if (in_array($field, $plain, true)) {
                $row['plain'][$field] = $value;
            } elseif (in_array($field, $translatable, true)) {
                $row['tr'][$field] = array_filter((array) $value, fn ($v) => $v !== null);
            } elseif (ContentSchema::isPlain($dataset, $field)) {
                $row['meta'][$field] = $value;
            } else {
                foreach ((array) $value as $locale => $v) {
                    if ($v !== null) {
                        $row['tr']['data'][$locale][$field] = $v;
                    }
                }
            }
        }

        return $row;
    }

    /** ContentSeeder::registryDefaults() of d3e1481. */
    private function registryDefaults(string $dataset, string $code): array
    {
        return match ($dataset) {
            'pages' => [
                'slug' => config("summit.pages.{$code}.path"),
                'position' => array_search($code, array_keys(config('summit.pages')), true) ?: 0,
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
}
