<?php

namespace App\Content\Cms\Editing;

use App\Content\Cms\Sources;
use App\Content\Cms\Tree;
use Illuminate\Support\Facades\Cache;

/**
 * Character limits of the collection fields (docs/briefs/design-2.1-and-admin-cms.md §8.4): for every text field, 115 %
 * of the longest approved text of that field in any record and any language, rounded up, so no approved text is ever
 * blocked. Computed from the import's sources (App\Content\Cms\Sources: the DOCX import and the lang files), exactly
 * like the block limits the importer writes into each block (App\Content\Cms\Tree::limits()).
 *
 * Paths: the column, then the keys inside a structured value, list positions as "*": "title", "bio.*", "areas.items.*".
 * Interface strings have one limit per string ("ui_strings" => "group.key").
 */
final class Limits
{
    private const CACHE = 'summit.cms.limits.v1';

    /** @var array<string, array<string, int>>|null */
    private static ?array $memo = null;

    /** @return array<string, int> path => limit for a dataset (pages, services … situations, document_samples, ui_strings) */
    public static function for(string $dataset): array
    {
        return self::all()[$dataset] ?? [];
    }

    public static function field(string $dataset, string $path): ?int
    {
        return self::for($dataset)[$path] ?? null;
    }

    public static function forget(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE);
    }

    /** @return array<string, array<string, int>> */
    private static function all(): array
    {
        return self::$memo ??= Cache::remember(self::CACHE, now()->addDay(), fn () => self::build());
    }

    /** @return array<string, array<string, int>> */
    private static function build(): array
    {
        $build = (new Sources)->build();
        $longest = [];
        foreach ($build['records'] as $dataset => $rows) {
            foreach ($rows as $row) {
                self::row($dataset, $row, $longest);
            }
        }
        foreach (['situations', 'document_samples', 'calculator_rule_sets'] as $dataset) {
            foreach ($build[$dataset] as $row) {
                self::row($dataset, $row, $longest);
            }
        }
        foreach ($build['ui_strings'] as $row) {
            foreach ((array) $row['text'] as $text) {
                if (is_string($text)) {
                    $key = $row['group'].'.'.$row['key'];
                    $longest['ui_strings'][$key] = max($longest['ui_strings'][$key] ?? 0, mb_strlen($text));
                }
            }
        }
        $limits = [];
        foreach ($longest as $dataset => $paths) {
            foreach ($paths as $path => $n) {
                if ($n > 0) {
                    $limits[$dataset][$path] = (int) ceil($n * 1.15);
                }
            }
        }

        return $limits;
    }

    /** @param array<string, array<string, int>> $longest */
    private static function row(string $dataset, array $row, array &$longest): void
    {
        foreach ($row as $column => $value) {
            if (! is_array($value) || in_array($column, ['blocks', 'draft'], true)) {
                continue;
            }
            $keys = array_keys($value);
            if ($keys !== [] && array_diff($keys, Tree::LOCALES) === []) {
                foreach ($value as $text) {
                    self::walk($text, $column, $dataset, $longest);
                }
            } else {
                self::walk($value, $column, $dataset, $longest); // a shared tree (document parts)
            }
        }
    }

    /** @param array<string, array<string, int>> $longest */
    private static function walk(mixed $node, string $path, string $dataset, array &$longest): void
    {
        if (is_string($node)) {
            $longest[$dataset][$path] = max($longest[$dataset][$path] ?? 0, mb_strlen($node));

            return;
        }
        if (! is_array($node)) {
            return;
        }
        if (Tree::isLeaf($node)) {
            foreach ($node as $text) {
                self::walk($text, $path, $dataset, $longest);
            }

            return;
        }
        $list = array_is_list($node);
        foreach ($node as $key => $child) {
            self::walk($child, $path.'.'.($list ? '*' : $key), $dataset, $longest);
        }
    }
}
