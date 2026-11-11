<?php

namespace App\Content\Cms;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * php artisan summit:content-import (docs/briefs/design-2.1-and-admin-cms.md §8.8 step 2): today's sources
 * (App\Content\Cms\Sources) into the CMS tables, idempotently.
 *
 * Default run: a missing row is created; a row the admin has not touched (updated_by is null, not client approved, not
 * in the Trash) is updated when its source changed; everything else is kept, and so are rows that no longer have a
 * source. Settings are created when missing and otherwise kept (they belong to the settings screens). Running it twice
 * changes nothing the second time.
 *
 * --fresh rebuilds: every CMS content table and the settings are written from the sources again (users, enquiries and
 * revisions are never touched). Every created or changed record gets a revision ("imported").
 *
 * Writes are batched (one statement per table and chunk) inside one transaction; afterwards the published-content cache
 * moves to a new version.
 */
final class ContentImporter
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    private const CHUNK = 200;

    /** @var array<string, array{created: int, updated: int, unchanged: int, kept: int, removed: int}> */
    private array $counts = [];

    /** @var list<array<string, mixed>> */
    private array $revisions = [];

    private string $now;

    public function __construct(private readonly Sources $sources = new Sources) {}

    /**
     * @return array{counts: array<string, array{created: int, updated: int, unchanged: int, kept: int, removed: int}>, notes: list<string>}
     */
    public function run(bool $fresh = false): array
    {
        $build = $this->sources->build();
        $this->counts = [];
        $this->revisions = [];
        $this->now = now()->toDateTimeString();

        DB::transaction(function () use ($build, $fresh) {
            foreach (Schema::DATASETS as $dataset => $schema) {
                $this->syncKeyed(Schema::table($dataset), ['code'], array_values($build['records'][$dataset] ?? []), $fresh, $this->jsonColumns($dataset), $schema['model']);
            }
            $this->syncKeyed('situations', ['code'], $build['situations'], $fresh, ['title', 'documents', 'first_steps', 'mistakes'], \App\Models\Content\Situation::class);
            $this->syncKeyed('document_samples', ['code'], $build['document_samples'], $fresh, ['title', 'parts'], \App\Models\Content\DocumentSample::class);
            $this->syncKeyed('ui_strings', ['group', 'key'], $build['ui_strings'], $fresh, ['text'], null);
            $this->syncKeyed('calculator_rule_sets', ['calculator', 'version'], $build['calculator_rule_sets'], $fresh, ['rules', 'legal_basis'], \App\Models\Content\CalculatorRuleSet::class);
            $this->syncMenus($build['menus'], $fresh);
            $this->syncSettings($build['settings'], $fresh);
            foreach (array_chunk($this->revisions, self::CHUNK) as $chunk) {
                DB::table('revisions')->insert($chunk);
            }
        });

        ContentCache::bump();
        // The translation loader's own cache (App\Models\Content\UiString reads through the content cache instead).
        foreach (Sources::LANG_GROUPS as $group) {
            foreach (Tree::LOCALES as $locale) {
                Cache::forget("spatie.translation-loader.{$group}.{$locale}");
            }
        }

        return ['counts' => $this->counts, 'notes' => $build['notes']];
    }

    /** @return list<string> */
    private function jsonColumns(string $dataset): array
    {
        return Schema::jsonColumns($dataset);
    }

    /**
     * Sync rows identified by $keys.
     *
     * @param  list<string>  $keys
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $json  columns stored as JSON text
     * @param  class-string|null  $model  for the revisions (null: no revisions)
     */
    private function syncKeyed(string $table, array $keys, array $rows, bool $fresh, array $json, ?string $model): void
    {
        $count = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'kept' => 0, 'removed' => 0];
        $existing = [];
        foreach (DB::table($table)->get() as $row) {
            $existing[$this->id((array) $row, $keys)] = (array) $row;
        }
        if ($fresh) {
            $count['removed'] = count(array_diff_key($existing, array_flip(array_map(fn ($r) => $this->id($r, $keys), $rows))));
            DB::table($table)->delete();
        }

        $inserts = [];
        foreach ($rows as $row) {
            $id = $this->id($row, $keys);
            $old = $existing[$id] ?? null;
            $encoded = $this->encode($row, $json);
            $changed = $old === null || ! $this->same($old, $row, $json);
            if ($fresh || $old === null) {
                $inserts[] = $encoded + [
                    'created_at' => $old['created_at'] ?? $this->now,
                    'updated_at' => $changed ? $this->now : ($old['updated_at'] ?? $this->now),
                    'published_at' => $old['published_at'] ?? $this->now,
                ];
                $old === null ? $count['created']++ : ($changed ? $count['updated']++ : $count['unchanged']++);
                if ($changed && $model !== null) {
                    $this->revision($model, $id, $row, $old === null ? 'created' : 'updated');
                }

                continue;
            }
            if (! $changed) {
                $count['unchanged']++;

                continue;
            }
            if ($this->edited($old)) {
                $count['kept']++;

                continue;
            }
            $query = DB::table($table);
            foreach ($keys as $key) {
                $query->where($key, $row[$key]);
            }
            $query->update($encoded + ['updated_at' => $this->now]);
            $count['updated']++;
            if ($model !== null) {
                $this->revision($model, $id, $row, 'updated');
            }
        }
        foreach (array_chunk($inserts, self::CHUNK) as $chunk) {
            DB::table($table)->insert($this->uniform($chunk));
        }
        if (! $fresh) {
            $count['kept'] += count(array_diff_key($existing, array_flip(array_map(fn ($r) => $this->id($r, $keys), $rows))));
        }
        $this->counts[$table] = $count;
    }

    /**
     * Menus and their items: a menu nobody edited is replaced when its items changed; an edited one is kept.
     *
     * @param  list<array<string, mixed>>  $menus
     */
    private function syncMenus(array $menus, bool $fresh): void
    {
        $count = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'kept' => 0, 'removed' => 0];
        $existing = DB::table('navigation_menus')->get()->keyBy('key');
        if ($fresh) {
            $count['removed'] = $existing->keys()->diff(array_column($menus, 'key'))->count();
            DB::table('navigation_items')->delete();
            DB::table('navigation_menus')->delete();
            $existing = collect();
        }
        $itemJson = ['label', 'registry_label', 'options'];
        foreach ($menus as $menu) {
            $items = $menu['items'];
            unset($menu['items'], $menu['position']);
            $old = $existing->get($menu['key']);
            if ($old !== null) {
                $oldItems = DB::table('navigation_items')->where('menu_id', $old->id)->orderBy('position')->get()->map(fn ($r) => (array) $r)->all();
                $edited = $this->edited((array) $old) || collect($oldItems)->contains(fn ($r) => $this->edited($r));
                $same = count($oldItems) === count($items) && collect($items)->every(fn ($item, $i) => $this->same($oldItems[$i], $item, $itemJson));
                if ($same) {
                    $count['unchanged']++;

                    continue;
                }
                if ($edited) {
                    $count['kept']++;

                    continue;
                }
                DB::table('navigation_items')->where('menu_id', $old->id)->delete();
                $menuId = $old->id;
                $count['updated']++;
            } else {
                $menuId = DB::table('navigation_menus')->insertGetId($menu + ['published_at' => $this->now, 'created_at' => $this->now, 'updated_at' => $this->now]);
                $count['created']++;
            }
            $rows = array_map(fn ($item) => $this->encode($item, $itemJson) + [
                'menu_id' => $menuId,
                'published_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ], $items);
            DB::table('navigation_items')->insert($this->uniform($rows));
        }
        if (! $fresh) {
            $count['kept'] += $existing->keys()->diff(array_column($menus, 'key'))->count();
        }
        $this->counts['navigation_menus'] = $count;
    }

    /**
     * Settings (spatie/laravel-settings rows: group, name, payload): created when missing, overwritten only by --fresh.
     *
     * @param  array<string, array<string, mixed>>  $settings
     */
    private function syncSettings(array $settings, bool $fresh): void
    {
        $count = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'kept' => 0, 'removed' => 0];
        $existing = [];
        foreach (DB::table('settings')->get() as $row) {
            $existing[$row->group.'.'.$row->name] = $row;
        }
        foreach ($settings as $group => $properties) {
            foreach ($properties as $name => $value) {
                $payload = json_encode($value, self::JSON);
                $old = $existing[$group.'.'.$name] ?? null;
                if ($old === null) {
                    DB::table('settings')->insert(['group' => $group, 'name' => $name, 'locked' => false, 'payload' => $payload, 'created_at' => $this->now, 'updated_at' => $this->now]);
                    $count['created']++;
                } elseif (json_decode((string) $old->payload, true) === $value) {
                    $count['unchanged']++;
                } elseif ($fresh) {
                    DB::table('settings')->where('id', $old->id)->update(['payload' => $payload, 'updated_at' => $this->now]);
                    $count['updated']++;
                } else {
                    $count['kept']++;
                }
            }
        }
        $this->counts['settings'] = $count;
    }

    // ------------------------------------------------------------------------------------------- helpers

    /** @param list<string> $keys */
    private function id(array $row, array $keys): string
    {
        return implode("\u{1F}", array_map(fn ($k) => (string) $row[$k], $keys));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $json
     * @return array<string, mixed>
     */
    private function encode(array $row, array $json): array
    {
        foreach ($json as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $row[$column] === null ? null : json_encode($row[$column], self::JSON);
            }
        }

        return $row;
    }

    /**
     * Whether the stored row already holds the source row (JSON compared decoded, scalars loosely by type).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $row
     * @param  list<string>  $json
     */
    private function same(array $old, array $row, array $json): bool
    {
        foreach ($row as $column => $value) {
            $stored = $old[$column] ?? null;
            if (in_array($column, $json, true)) {
                if ((is_string($stored) ? json_decode($stored, true) : $stored) !== $value) {
                    return false;
                }
            } elseif (is_bool($value)) {
                if ((bool) $stored !== $value) {
                    return false;
                }
            } elseif ($value === null ? $stored !== null : (string) $stored !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /** Touched in the admin: saved by a user, approved by the client or moved to the Trash. */
    private function edited(array $row): bool
    {
        return ($row['updated_by'] ?? null) !== null
            || ($row['copy_source'] ?? null) === CopySource::CLIENT_APPROVED
            || ($row['deleted_at'] ?? null) !== null;
    }

    /** @param list<array<string, mixed>> $rows every row with the same columns (one INSERT) */
    private function uniform(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            $columns += array_fill_keys(array_keys($row), null);
        }

        return array_map(fn ($row) => array_merge($columns, $row), $rows);
    }

    /** @param class-string $model */
    private function revision(string $model, string $id, array $row, string $what): void
    {
        $this->revisions[] = [
            'revisionable_type' => array_search($model, Relation::morphMap(), true) ?: $model,
            'revisionable_id' => str_replace("\u{1F}", ':', $id),
            'snapshot' => json_encode($row, self::JSON),
            'note' => "summit:content-import ({$what})",
            'user_id' => null,
            'created_at' => $this->now,
        ];
    }
}
