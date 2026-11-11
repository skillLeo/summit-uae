<?php

namespace App\Content\Cms;

/**
 * Rows of the CMS tables → the records the views read (content()->page('P01'), ->service('S06') …), in one language.
 *
 * A record has the shape the content tables gave before Phase 3, so Design 1 and Design 2 read the same arrays as
 * before: 'code', the plain columns, the translatable columns in the language (the "always" ones null when missing, the
 * others left out), and the blocks field (pages: content, practice areas: sections, guides and case studies: body)
 * rebuilt from the blocks in block order. Practice areas also carry 'situations' (the situations table).
 */
final class Records
{
    /**
     * @param  array<string, mixed>  $row  a decoded row (JSON columns as arrays)
     * @param  array<string, list<array<string, mixed>>>  $situations  practice area => its situation rows, in order
     * @return array<string, mixed>
     */
    public static function record(string $dataset, array $row, string $locale, array $situations = []): array
    {
        $schema = Schema::DATASETS[$dataset];
        $record = ['code' => (string) $row['code']];
        foreach ([...$schema['plain'], ...$schema['json']] as $column) {
            $record[$column] = $row[$column] ?? null;
        }
        foreach ($schema['translatable'] as $column) {
            $value = $row[$column] ?? null;
            if (is_array($value) && array_key_exists($locale, $value) && $value[$locale] !== null) {
                $record[$column] = $value[$locale];
            } elseif (in_array($column, $schema['always'], true)) {
                $record[$column] = null;
            }
        }
        if ($schema['blocks'] !== null) {
            $field = null;
            foreach (self::blockData((array) ($row['blocks'] ?? []), $locale) as [$into, $key, $value]) {
                if ($into === 'page') {
                    $record[$key] = $value;
                } elseif ($into === 'self') {
                    $field = $value;
                } else {
                    $field ??= [];
                    $field[$key] = $value;
                }
            }
            if ($field !== null) {
                $record[$schema['blocks']] = $field;
            }
        }
        if ($dataset === 'services' && isset($situations[$record['code']])) {
            $titles = [];
            foreach ($situations[$record['code']] as $situation) {
                $title = $situation['title'][$locale] ?? null;
                if ($title !== null) {
                    $titles[] = $title;
                }
            }
            $record['situations'] = $titles;
        }

        return $record;
    }

    /**
     * The blocks of a page in one language, for rendering: their settings and their data resolved.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array{id: string, type: string, enabled: bool, anchor: string|null, background: string, copy_source: string, data: array<string, mixed>}>
     */
    public static function blocks(array $blocks, string $locale): array
    {
        $out = [];
        foreach ($blocks as $block) {
            $data = [];
            foreach (self::blockData([$block], $locale) as [, $key, $value]) {
                $data[$key] = $value;
            }
            $out[] = [
                'id' => (string) ($block['id'] ?? ''),
                'type' => (string) ($block['type'] ?? 'section'),
                'enabled' => (bool) ($block['enabled'] ?? true),
                'anchor' => isset($block['anchor']) && $block['anchor'] !== '' ? (string) $block['anchor'] : null,
                'background' => in_array($block['background'] ?? null, ['white', 'band', 'dark'], true) ? $block['background'] : 'white',
                'copy_source' => in_array($block['copy_source'] ?? null, CopySource::ALL, true) ? $block['copy_source'] : CopySource::DOCX,
                'data' => $data,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return \Generator<int, array{0: string, 1: string, 2: mixed}> [into, key, value]
     */
    private static function blockData(array $blocks, string $locale): \Generator
    {
        $absent = Tree::absent();
        foreach ($blocks as $block) {
            $locales = (array) ($block['locales'] ?? []);
            foreach ((array) ($block['data'] ?? []) as $key => $node) {
                if (isset($locales[$key]) && ! in_array($locale, (array) $locales[$key], true)) {
                    continue;
                }
                $value = Tree::resolve($node, $locale);
                if ($value !== $absent) {
                    yield [(string) ($block['into'] ?? 'field'), (string) $key, $value];
                }
            }
        }
    }
}
