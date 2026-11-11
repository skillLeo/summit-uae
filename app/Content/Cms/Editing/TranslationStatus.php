<?php

namespace App\Content\Cms\Editing;

use App\Content\Cms\Tree;
use Illuminate\Database\Eloquent\Model;

/**
 * Translation status of a record (docs/briefs/design-2.1-and-admin-cms.md §8.3: every list has an EN / AR / RU status
 * column and a "Missing translations" filter). A text is missing in a language when another language has it and this
 * one does not:
 *   translatable columns  {en, ar, ru}: the languages with a non-empty value
 *   trees                 every {en, ar, ru} leaf of the blocks or parts, except the keys a block shows only in some
 *                         languages (its `locales` list: such a key is expected only there)
 * Registry::NOT_TRANSLATED (the site map's registry labels: the Arabic site map has none) is never counted. The working
 * copy is checked, so a draft that adds the Arabic text clears the marker at once.
 */
final class TranslationStatus
{
    /**
     * @param  array<string, mixed>|null  $values  the values to check (default: the record's working copy)
     * @return array<string, list<string>> locale => paths missing there
     */
    public static function missing(Model $record, ?array $values = null): array
    {
        $values ??= Publisher::workingCopy($record);
        $missing = array_fill_keys(Tree::LOCALES, []);
        foreach (Registry::fields($record) as $column => $kind) {
            if (in_array($column, Registry::NOT_TRANSLATED, true) || ! array_key_exists($column, $values)) {
                continue;
            }
            $value = $values[$column];
            if ($kind === Registry::TRANSLATABLE) {
                self::leaf($column, is_array($value) ? $value : [], Tree::LOCALES, $missing);
            } elseif ($kind === Registry::TREE && is_array($value)) {
                foreach (array_values($value) as $i => $node) {
                    if ($column === 'blocks' && is_array($node) && isset($node['data'])) {
                        $locales = (array) ($node['locales'] ?? []);
                        foreach ((array) $node['data'] as $key => $child) {
                            $expected = isset($locales[$key]) ? array_values(array_intersect(Tree::LOCALES, (array) $locales[$key])) : Tree::LOCALES;
                            self::walk($child, 'blocks.'.($node['id'] ?? $i).'.'.$key, $expected, $missing);
                        }
                    } else {
                        self::walk($node, $column.'.'.$i, Tree::LOCALES, $missing);
                    }
                }
            } elseif ($kind === Registry::ITEMS) {
                foreach ((array) $value as $i => $item) {
                    if (is_array($item['label'] ?? null)) {
                        self::leaf('items.'.($item['link_key'] ?? $i).'.label', $item['label'], Tree::LOCALES, $missing);
                    }
                }
            }
        }

        return $missing;
    }

    /** @return list<string> the locales with at least one missing text */
    public static function missingLocales(Model $record): array
    {
        return array_keys(array_filter(self::missing($record)));
    }

    /** @param array<string, list<string>> $missing */
    private static function walk(mixed $node, string $path, array $expected, array &$missing): void
    {
        if (! is_array($node)) {
            return;
        }
        if (Tree::isLeaf($node)) {
            self::leaf($path, $node, $expected, $missing);

            return;
        }
        foreach ($node as $key => $child) {
            self::walk($child, $path.'.'.$key, $expected, $missing);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<string>  $expected
     * @param  array<string, list<string>>  $missing
     */
    private static function leaf(string $path, array $value, array $expected, array &$missing): void
    {
        $present = [];
        foreach ($expected as $locale) {
            $text = $value[$locale] ?? null;
            if ($text !== null && $text !== '' && $text !== []) {
                $present[] = $locale;
            }
        }
        if ($present === [] || count($present) === count($expected)) {
            return;
        }
        foreach (array_diff($expected, $present) as $locale) {
            $missing[$locale][] = $path;
        }
    }
}
