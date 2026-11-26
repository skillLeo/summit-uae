<?php

namespace App\Content\Cms;

use App\Content\Import\ImportException;

/**
 * The client-approved content layer (docs/briefs/client-final-changes-2026-11-25.md, "Rules for this step"): texts and
 * structure the client approved outside the DOCX files, kept in committed PHP files database/content/client/*.php and
 * applied by App\Content\Cms\Sources on top of the DOCX import (database/content/*.json) on every build. So:
 *   - `php artisan summit:import` (DOCX → JSON) writes only the JSON files: it can never overwrite a client change;
 *   - `php artisan db:seed` on every deploy (App\Content\Cms\ContentImporter) writes the changed records into the CMS
 *     tables like any other source change (a row edited in the admin is kept, as always), marked copy_source
 *     client_approved: the record, the home block or section block that holds the changed text, the interface string
 *     and the navigation items that show it.
 * The files apply in name order (one per approval: 2026-11-25.php, …): a later file wins. A future client change is a
 * new entry (or a new file) here, never an edit of the JSON.
 *
 * A file returns ['source' => what the client approved, 'changes' => [...]]; each change names the client's item:
 *
 *   ['item' => 4, 'set' => 'pages.P01.h1', 'value' => ['ru' => '…']]
 *        a field of a record (dataset.code.field) or a value inside it (dataset.code.field.key.key …): a text field
 *        takes the languages given (the others stay); a plain field (Schema plain / json: slug, position …) takes the
 *        value itself
 *   ['item' => 9, 'remove' => 'guides.G08.body.related.items', 'where' => ['target' => 'S10']]
 *        the entries of a list whose keys match, in every language
 *   ['item' => 9, 'status' => 'services.S10', 'value' => 'draft']
 *        the record's publishing status (Status::DRAFT keeps it in the admin and off the site)
 *   ['item' => 9, 'create' => 'services.S12', 'value' => [field => value, …], 'status' => 'draft']
 *        a new record, its fields in the JSON's shape ({en, ar, ru} for text)
 *   ['item' => 8, 'ui' => 'nav.case_studies', 'value' => ['en' => '…', 'ar' => '…', 'ru' => '…']]
 *        an approved interface string (group "ui"): the languages given; with 'new' => true a string the DOCX files do
 *        not have (only the languages given; the others have no text)
 *   ['item' => 5, 'replace' => '+971 52 466 6191', 'with' => '+971524666191']
 *        a text replaced wherever it stands: every record (every field, every language), every interface string and
 *        every settings default; a later re-import that brings the old text back is corrected again
 *   ['item' => 10, 'replace' => 'https://u.ae/ar/', 'with' => 'https://u.ae/en/', 'locales' => ['en', 'ru']]
 *        the same, only in the texts of the languages given (records' and interface strings' per-language texts;
 *        language-neutral fields and settings are left alone)
 *   ['item' => 5, 'setting' => 'contact.phone_display', 'value' => '+971524666191']
 *        a settings default (a new install, --fresh): the server's stored value changes through the settings migration
 *        that the same item adds (database/settings), since db:seed keeps stored settings
 *
 * A change whose target does not exist fails the build (ImportException): a re-import that moved or dropped a text
 * must never drop a client change silently.
 */
final class ClientLayer
{
    public const SOURCE = 'database/content/client';

    /** @var list<array<string, mixed>>|null */
    private ?array $changes = null;

    public function __construct(private readonly ?string $dir = null) {}

    public function dir(): string
    {
        return $this->dir ?? database_path('content/client');
    }

    /** @return list<string> the layer files, in the order they apply */
    public function files(): array
    {
        $files = glob($this->dir().'/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Every change of every file, in order, each with its file name ('file').
     *
     * @return list<array<string, mixed>>
     */
    public function changes(): array
    {
        if ($this->changes !== null) {
            return $this->changes;
        }
        $changes = [];
        foreach ($this->files() as $file) {
            $layer = (static fn (string $f) => require $f)($file);
            if (! is_array($layer) || ! is_array($layer['changes'] ?? null)) {
                throw new ImportException('Client layer '.basename($file).": returns no ['changes' => [...]]");
            }
            foreach ($layer['changes'] as $i => $change) {
                if (! is_array($change)) {
                    throw new ImportException('Client layer '.basename($file)." change {$i} is not a list of keys");
                }
                $changes[] = ['file' => basename($file)] + $change;
            }
        }

        return $this->changes = $changes;
    }

    /**
     * Apply every change to the decoded sources, in place.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $data  dataset => code => fields (the JSON files)
     * @param  array<string, array<string, string|null>>  $ui  key => {en, ar, ru} (ui_strings.json)
     * @param  array<string, mixed>  $settings  the settings groups (Sources::settings())
     * @return array{records: array<string, array<string, array<string, array<string, true>|true>>>, status: array<string, array<string, string>>, ui: array<string, true>, notes: list<string>}
     *         records: dataset => code => field => true (the whole field), or field => [first key inside it => true]
     */
    public function apply(array &$data, array &$ui, array &$settings): array
    {
        $marks = ['records' => [], 'status' => [], 'ui' => [], 'notes' => []];
        $count = 0;
        foreach ($this->changes() as $change) {
            $where = "Client layer {$change['file']} (PDF item ".($change['item'] ?? '?').')';
            $op = $this->operation($change, $where);
            $target = (string) $change[$op];
            match ($op) {
                'set' => $this->set($data, $target, $change['value'] ?? null, $marks, $where),
                'remove' => $this->remove($data, $target, (array) ($change['where'] ?? []), $marks, $where),
                'status' => $this->status($data, $target, (string) ($change['value'] ?? ''), $marks, $where),
                'create' => $this->create($data, $target, $change, $marks, $where),
                'ui' => $this->ui($ui, $target, $change['value'] ?? null, $marks, $where, (bool) ($change['new'] ?? false)),
                'replace' => $this->replace($data, $ui, $settings, $target, $change['with'] ?? null, $marks, $where, $this->locales($change, $where)),
                'setting' => $this->setting($settings, $target, $change['value'] ?? null, $where),
            };
            $count++;
        }
        if ($count > 0) {
            $marks['notes'][] = "Client layer: {$count} client-approved change(s) applied from ".self::SOURCE;
        }

        return $marks;
    }

    private function operation(array $change, string $where): string
    {
        // create takes its record's status as an option: ['create' => …, 'value' => …, 'status' => 'draft']
        $ops = array_values(array_intersect(['set', 'remove', 'status', 'create', 'ui', 'replace', 'setting'], array_keys($change)));
        if (in_array('create', $ops, true)) {
            $ops = array_values(array_diff($ops, ['status']));
        }
        if (count($ops) !== 1 || ! is_string($change[$ops[0]]) || $change[$ops[0]] === '') {
            throw new ImportException("{$where}: one of set, remove, status, create, ui, replace or setting, naming its target");
        }

        return $ops[0];
    }

    /** @return array{0: string, 1: string, 2: string, 3: list<string>} dataset, code, field, path inside the field */
    private function address(array $data, string $target, string $where, bool $mustExist = true): array
    {
        $parts = explode('.', $target);
        if (count($parts) < 2 || ! isset(Schema::DATASETS[$parts[0]])) {
            throw new ImportException("{$where}: '{$target}' does not name a dataset (".implode(', ', array_keys(Schema::DATASETS)).')');
        }
        [$dataset, $code] = $parts;
        if ($mustExist && ! isset($data[$dataset][$code])) {
            throw new ImportException("{$where}: {$dataset}.{$code} does not exist in database/content/{$dataset}.json");
        }

        return [$dataset, $code, $parts[2] ?? '', array_slice($parts, 3)];
    }

    private function plain(string $dataset, string $field): bool
    {
        $schema = Schema::DATASETS[$dataset];

        return in_array($field, $schema['plain'], true) || in_array($field, $schema['json'], true);
    }

    private function set(array &$data, string $target, mixed $value, array &$marks, string $where): void
    {
        [$dataset, $code, $field, $path] = $this->address($data, $target, $where);
        if ($field === '') {
            throw new ImportException("{$where}: '{$target}' names no field");
        }
        $record = &$data[$dataset][$code];
        if ($this->plain($dataset, $field)) {
            if ($path !== []) {
                throw new ImportException("{$where}: {$dataset}.{$field} is a plain field; it has no keys inside");
            }
            $record[$field] = $value;
            $marks['records'][$dataset][$code][$field] = true;

            return;
        }
        if (! is_array($value) || $value === [] || array_diff(array_keys($value), Tree::LOCALES) !== []) {
            throw new ImportException("{$where}: the value of {$target} must be texts by language (".implode(', ', Tree::LOCALES).')');
        }
        foreach ($value as $locale => $text) {
            if ($path === []) {
                $record[$field][$locale] = $text;

                continue;
            }
            if (! isset($record[$field][$locale]) || ! is_array($record[$field][$locale])) {
                throw new ImportException("{$where}: {$dataset}.{$code}.{$field} has no {$locale} content for ".implode('.', $path));
            }
            $node = &$record[$field][$locale];
            foreach ($path as $key) {
                if (! is_array($node) || ! array_key_exists($key, $node)) {
                    throw new ImportException("{$where}: {$target} does not exist in {$locale}");
                }
                $node = &$node[$key];
            }
            $node = $text;
            unset($node);
        }
        $this->mark($marks, $dataset, $code, $field, $path);
    }

    private function remove(array &$data, string $target, array $match, array &$marks, string $where): void
    {
        [$dataset, $code, $field, $path] = $this->address($data, $target, $where);
        if ($match === []) {
            throw new ImportException("{$where}: remove {$target} needs 'where' => [key => value]");
        }
        $record = &$data[$dataset][$code];
        $removed = 0;
        foreach ($this->plain($dataset, $field) ? [null] : Tree::LOCALES as $locale) {
            if ($locale === null) {
                $node = &$record[$field];
            } else {
                if (! isset($record[$field][$locale])) {
                    continue;
                }
                $node = &$record[$field][$locale];
            }
            foreach ($path as $key) {
                if (! is_array($node) || ! array_key_exists($key, $node)) {
                    throw new ImportException("{$where}: {$target} does not exist".($locale ? " in {$locale}" : ''));
                }
                $node = &$node[$key];
            }
            if (! is_array($node) || ! array_is_list($node)) {
                throw new ImportException("{$where}: {$target} is not a list");
            }
            $kept = array_values(array_filter($node, function ($entry) use ($match) {
                foreach ($match as $key => $value) {
                    if (! is_array($entry) || ($entry[$key] ?? null) !== $value) {
                        return true;
                    }
                }

                return false;
            }));
            $removed += count($node) - count($kept);
            $node = $kept;
            unset($node);
        }
        if ($removed === 0) {
            throw new ImportException("{$where}: nothing in {$target} matches ".json_encode($match));
        }
        $this->mark($marks, $dataset, $code, $field, $path);
    }

    private function status(array &$data, string $target, string $status, array &$marks, string $where): void
    {
        [$dataset, $code] = $this->address($data, $target, $where);
        if (! in_array($status, Status::ALL, true)) {
            throw new ImportException("{$where}: status of {$target} must be ".implode(' or ', Status::ALL));
        }
        $marks['status'][$dataset][$code] = $status;
        $marks['records'][$dataset][$code] ??= [];
    }

    private function create(array &$data, string $target, array $change, array &$marks, string $where): void
    {
        [$dataset, $code] = $this->address($data, $target, $where, mustExist: false);
        if (isset($data[$dataset][$code])) {
            throw new ImportException("{$where}: {$dataset}.{$code} exists already (set its fields instead)");
        }
        if (! is_array($change['value'] ?? null)) {
            throw new ImportException("{$where}: create {$target} needs 'value' => [field => value]");
        }
        $data[$dataset][$code] = $change['value'];
        $marks['records'][$dataset][$code] = array_fill_keys(array_keys($change['value']), true);
        if (isset($change['status'])) {
            $this->status($data, $target, (string) $change['status'], $marks, $where);
        }
    }

    private function ui(array &$ui, string $key, mixed $value, array &$marks, string $where, bool $new = false): void
    {
        if ($new && isset($ui[$key])) {
            throw new ImportException("{$where}: the interface string ui:{$key} exists already (drop 'new')");
        }
        if (! $new && ! isset($ui[$key])) {
            throw new ImportException("{$where}: the interface string ui:{$key} does not exist in database/content/ui_strings.json (a new one needs 'new' => true)");
        }
        $ui[$key] ??= [];
        if (! is_array($value) || $value === [] || array_diff(array_keys($value), Tree::LOCALES) !== []) {
            throw new ImportException("{$where}: the value of ui:{$key} must be texts by language (".implode(', ', Tree::LOCALES).')');
        }
        $ui[$key] = array_merge((array) $ui[$key], $value);
        $marks['ui'][$key] = true;
    }

    /**
     * A replace's 'locales' (a subset of Tree::LOCALES), or null for every language.
     *
     * @param  array<string, mixed>  $change
     * @return list<string>|null
     */
    private function locales(array $change, string $where): ?array
    {
        if (! array_key_exists('locales', $change)) {
            return null;
        }
        $locales = $change['locales'];
        if (! is_array($locales) || $locales === [] || array_diff($locales, Tree::LOCALES) !== []) {
            throw new ImportException("{$where}: 'locales' needs a list of ".implode(', ', Tree::LOCALES));
        }

        return array_values($locales);
    }

    /** @param  list<string>|null  $locales  only the texts of these languages (null: every text) */
    private function replace(array &$data, array &$ui, array &$settings, string $search, mixed $with, array &$marks, string $where, ?array $locales = null): void
    {
        if (! is_string($with)) {
            throw new ImportException("{$where}: replace '{$search}' needs 'with' => a text");
        }
        $count = 0;
        foreach ($data as $dataset => &$records) {
            foreach ($records as $code => &$fields) {
                foreach ($fields as $field => &$value) {
                    $byLocale = is_array($value) && ! $this->plain($dataset, (string) $field) && array_intersect(array_keys($value), Tree::LOCALES) !== [];
                    if (! $byLocale) {
                        if ($locales !== null) {
                            continue; // a language-neutral field: not this replace's
                        }
                        if (($n = self::replaceIn($value, $search, $with)) > 0) {
                            $count += $n;
                            $this->mark($marks, $dataset, (string) $code, (string) $field, []);
                        }

                        continue;
                    }
                    foreach ($value as $locale => &$perLocale) {
                        if ($locales !== null && ! in_array((string) $locale, $locales, true)) {
                            continue;
                        }
                        if (! is_array($perLocale)) {
                            if (($n = self::replaceIn($perLocale, $search, $with)) > 0) {
                                $count += $n;
                                $this->mark($marks, $dataset, (string) $code, (string) $field, []);
                            }

                            continue;
                        }
                        foreach ($perLocale as $key => &$node) {
                            if (($n = self::replaceIn($node, $search, $with)) > 0) {
                                $count += $n;
                                $this->mark($marks, $dataset, (string) $code, (string) $field, [(string) $key]);
                            }
                        }
                        unset($node);
                    }
                    unset($perLocale);
                }
                unset($value);
            }
            unset($fields);
        }
        unset($records);
        foreach ($ui as $key => &$texts) {
            if ($locales !== null && is_array($texts)) {
                foreach ($locales as $locale) {
                    if (array_key_exists($locale, $texts) && ($n = self::replaceIn($texts[$locale], $search, $with)) > 0) {
                        $count += $n;
                        $marks['ui'][(string) $key] = true;
                    }
                }

                continue;
            }
            if (($n = self::replaceIn($texts, $search, $with)) > 0) {
                $count += $n;
                $marks['ui'][(string) $key] = true;
            }
        }
        unset($texts);
        if ($locales === null) {
            $count += self::replaceIn($settings, $search, $with);
        }
        $marks['notes'][] = "Client layer: '{$search}' replaced {$count} time(s)";
    }

    /** Replace in every text of a value (a text or a nested structure), in place; the number of texts changed. */
    private static function replaceIn(mixed &$value, string $search, string $with): int
    {
        if (is_string($value)) {
            if (! str_contains($value, $search)) {
                return 0;
            }
            $value = str_replace($search, $with, $value);

            return 1;
        }
        if (! is_array($value)) {
            return 0;
        }
        $count = 0;
        foreach ($value as &$item) {
            $count += self::replaceIn($item, $search, $with);
        }
        unset($item);

        return $count;
    }

    private function setting(array &$settings, string $target, mixed $value, string $where): void
    {
        $parts = explode('.', $target, 2);
        if (count($parts) !== 2 || ! array_key_exists($parts[1], $settings[$parts[0]] ?? [])) {
            throw new ImportException("{$where}: the setting {$target} does not exist");
        }
        $settings[$parts[0]][$parts[1]] = $value;
    }

    /** @param list<string> $path */
    private function mark(array &$marks, string $dataset, string $code, string $field, array $path): void
    {
        $current = $marks['records'][$dataset][$code][$field] ?? null;
        if ($path === [] || $current === true) {
            $marks['records'][$dataset][$code][$field] = true;

            return;
        }
        $marks['records'][$dataset][$code][$field] = (is_array($current) ? $current : []) + [(string) $path[0] => true];
    }
}
