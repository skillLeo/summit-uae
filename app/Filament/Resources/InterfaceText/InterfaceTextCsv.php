<?php

namespace App\Filament\Resources\InterfaceText;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Tree;
use App\Models\Content\UiString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * CSV export and import of the interface text (docs/briefs/design-2.1-and-admin-cms.md §8.2 "Interface Text": CSV
 * export and import), for translators working in a spreadsheet.
 *
 * Export: group, key, en, ar, ru, copy_source, note; UTF-8 with a BOM (Excel opens Arabic and Russian correctly); the
 * texts are the working copy (the draft when there is one), so an export after editing is what the editor sees.
 *
 * Import: the header row is required (group and key, then any of en, ar, ru, note; other columns such as copy_source
 * are ignored). Rows are matched on group + key; only existing strings are changed (the views define the keys, so an
 * unknown key is rejected, never created). An empty cell leaves that language as it is. Every change is saved as a
 * DRAFT through Publisher::saveDraft(), so the editor reviews and publishes; with $publish the changed strings (and the
 * rows that equal a waiting draft) are published at once. A row is rejected, and nothing of it is written, when it is malformed (wrong number of cells, no
 * group or key), its key is unknown, a text is longer than the string's limit (App\Content\Cms\Editing\Limits), or a
 * text drops a placeholder (:count …) that the string has in that language.
 */
final class InterfaceTextCsv
{
    public const COLUMNS = ['group', 'key', 'en', 'ar', 'ru', 'copy_source', 'note'];

    /** The CSV text of the strings of a query (all of them by default), in group and key order. */
    public static function export(?Builder $query = null): string
    {
        $query ??= UiString::query();
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::COLUMNS, escape: '');
        $query->reorder()->orderBy('group')->orderBy('key')->chunk(500, function ($rows) use ($out) {
            foreach ($rows as $row) {
                fputcsv($out, self::row($row), escape: '');
            }
        });
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** @return list<string> */
    public static function row(UiString $line): array
    {
        $working = Publisher::workingCopy($line);
        $text = (array) ($working['text'] ?? []);
        $cells = [(string) $line->group, (string) $line->key];
        foreach (Tree::LOCALES as $locale) {
            $cells[] = is_string($text[$locale] ?? null) ? $text[$locale] : '';
        }
        $cells[] = (string) $line->copy_source;
        $cells[] = (string) ($working['note'] ?? '');

        return $cells;
    }

    /**
     * Import a CSV text.
     *
     * @return array{updated: int, published: int, unchanged: int, rejected: list<array{line: int, key: string, reason: string}>, error: string|null}
     */
    public static function import(string $csv, bool $publish = false, ?int $userId = null): array
    {
        $report = ['updated' => 0, 'published' => 0, 'unchanged' => 0, 'rejected' => [], 'error' => null];
        $csv = str_starts_with($csv, "\xEF\xBB\xBF") ? substr($csv, 3) : $csv;
        if (! mb_check_encoding($csv, 'UTF-8')) {
            $report['error'] = 'encoding';

            return $report;
        }
        $rows = self::parse($csv);
        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), $rows[0] ?? []);
        if (! in_array('group', $header, true) || ! in_array('key', $header, true)
            || array_intersect(['en', 'ar', 'ru', 'note'], $header) === [] || count($header) !== count(array_unique($header))) {
            $report['error'] = 'header';

            return $report;
        }
        $index = array_flip($header);
        $limits = Limits::for('ui_strings');
        $lines = UiString::query()->get()->keyBy(fn (UiString $line) => $line->group."\0".$line->key);
        $changes = [];
        foreach (array_slice($rows, 1, null, true) as $n => $cells) {
            $lineNo = $n + 1;
            if ($cells === [null] || $cells === [] || (count($cells) === 1 && trim((string) $cells[0]) === '')) {
                continue; // a blank line
            }
            $group = trim((string) ($cells[$index['group']] ?? ''));
            $key = trim((string) ($cells[$index['key']] ?? ''));
            $name = $group.'.'.$key;
            if (count($cells) !== count($header) || $group === '' || $key === '') {
                $report['rejected'][] = ['line' => $lineNo, 'key' => $name, 'reason' => 'malformed'];

                continue;
            }
            $line = $lines[$group."\0".$key] ?? null;
            if ($line === null) {
                $report['rejected'][] = ['line' => $lineNo, 'key' => $name, 'reason' => 'unknown'];

                continue;
            }
            $working = Publisher::workingCopy($line);
            $live = Publisher::live($line);
            $text = (array) ($working['text'] ?? []);
            $reason = null;
            foreach (Tree::LOCALES as $locale) {
                if (! isset($index[$locale])) {
                    continue;
                }
                $value = str_replace("\r\n", "\n", (string) ($cells[$index[$locale]] ?? ''));
                if (trim($value) === '') {
                    continue; // an empty cell keeps the language as it is
                }
                $limit = $limits[$name] ?? null;
                if ($limit !== null && mb_strlen($value) > $limit) {
                    $reason = 'too_long';
                    break;
                }
                $before = is_string(($live['text'] ?? [])[$locale] ?? null) ? $live['text'][$locale] : (is_string($text[$locale] ?? null) ? $text[$locale] : '');
                if (self::placeholders($before) !== [] && array_diff(self::placeholders($before), self::placeholders($value)) !== []) {
                    $reason = 'placeholders';
                    break;
                }
                $text[$locale] = $value;
            }
            if ($reason !== null) {
                $report['rejected'][] = ['line' => $lineNo, 'key' => $name, 'reason' => $reason];

                continue;
            }
            $data = ['text' => $text, 'note' => $working['note'] ?? null];
            if (isset($index['note'])) {
                $note = trim((string) ($cells[$index['note']] ?? ''));
                $data['note'] = $note === '' ? ($working['note'] ?? null) : mb_substr($note, 0, 2000);
            }
            if (Publisher::same(Publisher::normalize($line, $data), Publisher::normalize($line, ['text' => $working['text'] ?? null, 'note' => $working['note'] ?? null]))) {
                if ($publish && Publisher::hasDraft($line)) {
                    $changes[] = [$line, null]; // the file equals the draft: "import and publish" publishes it
                } else {
                    $report['unchanged']++;
                }

                continue;
            }
            $changes[] = [$line, $data];
        }

        DB::transaction(function () use ($changes, $publish, $userId, &$report) {
            foreach ($changes as [$line, $data]) {
                if ($data !== null) {
                    Publisher::saveDraft($line, $data, $userId);
                    $report['updated']++;
                }
                if ($publish && ($data === null || Publisher::hasDraft($line->refresh()) || $line->status !== 'published')) {
                    Publisher::publish($line->refresh(), 'csv import', $userId);
                    $report['published']++;
                }
            }
        });

        return $report;
    }

    /** @return list<string> the :placeholders of a text, sorted */
    public static function placeholders(string $text): array
    {
        preg_match_all('/(?<![\w:]):([a-z_][a-z0-9_]*)/i', $text, $m);
        $names = array_values(array_unique($m[1]));
        sort($names);

        return $names;
    }

    /** @return array<int, list<string|null>> rows of the CSV (quoted cells may span lines) */
    private static function parse(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];
        while (($cells = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = $cells;
        }
        fclose($stream);

        return $rows;
    }
}
