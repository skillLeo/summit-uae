<?php

namespace App\Content\Cms;

use App\Content\Import\ImportException;

/**
 * One structure for every language, with the texts inside (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.4).
 *
 * The DOCX import produces one tree per language ({en: tree, ar: tree, ru: tree}). Blocks keep ONE tree whose
 * leaves hold the language texts:
 *
 *     {en: {title: "A", items: ["x", "y"]}, ar: {title: "ب", items: ["س", "ص"]}}
 *       ⇄ {title: {en: "A", ar: "ب"}, items: [{en: "x", ar: "س"}, {en: "y", ar: "ص"}]}
 *
 * transpose() requires the same structure in every language (the same keys in the same order, lists of the same
 * length); a difference is an import error, never guessed. Leaves that are structure rather than text (ids, link
 * targets, types, URLs) and are identical in every language are stored once, unwrapped. resolve() gives back exactly
 * the tree of one language (key order included): leaves without that language are left out.
 */
final class Tree
{
    public const LOCALES = ['en', 'ar', 'ru'];

    /** Leaf keys that hold structure, not copy: stored once when identical in every language. */
    private const STRUCTURAL = ['id', 'type', 'ref', 'key', 'target', 'anchor', 'href', 'url', 'domain', 'marker', 'level', 'code', 'icon', 'service'];

    private static ?object $absent = null;

    /** The value resolve() returns for a leaf that has no text in the language. */
    public static function absent(): object
    {
        return self::$absent ??= new \stdClass;
    }

    /**
     * @param  array<string, mixed>  $byLocale  locale => that language's value (only the languages that have one)
     */
    public static function transpose(array $byLocale, string $path = '', ?string $key = null, bool $keepLeaves = false): mixed
    {
        if ($byLocale === []) {
            throw new ImportException("Tree: no language at {$path}");
        }
        $values = array_values($byLocale);
        $arrays = array_filter($values, 'is_array');
        if ($arrays !== [] && count($arrays) !== count($values)) {
            throw new ImportException("Tree: {$path} is a list or a group in one language and a text in another");
        }

        if ($arrays !== []) {
            $first = $values[0];
            $isList = array_is_list($first);
            foreach ($byLocale as $locale => $value) {
                if (array_is_list($value) !== $isList && $value !== [] && $first !== []) {
                    throw new ImportException("Tree: {$path} is a list in one language and a group in another ({$locale})");
                }
                if ($isList ? count($value) !== count($first) : array_keys($value) !== array_keys($first)) {
                    throw new ImportException(sprintf(
                        'Tree: %s has a different structure in %s (%s) than in %s (%s)',
                        $path === '' ? '(root)' : $path,
                        $locale,
                        $isList ? count($value).' items' : implode(', ', array_keys($value)),
                        array_key_first($byLocale),
                        $isList ? count($first).' items' : implode(', ', array_keys($first)),
                    ));
                }
            }
            // A group keyed by language codes (P01 form language_options: {ar, en, ru}) keeps every leaf wrapped, so it
            // can never be read back as a leaf itself.
            $localeKeyed = ! $isList && $first !== [] && array_diff(array_keys($first), self::LOCALES) === [];
            $node = [];
            foreach (array_keys($first) as $k) {
                $node[$k] = self::transpose(
                    array_map(fn (array $v) => $v[$k], $byLocale),
                    $path === '' ? (string) $k : $path.'.'.$k,
                    $isList ? $key : (string) $k,
                    $localeKeyed,
                );
            }

            return $node;
        }

        $first = $values[0];
        $complete = count($byLocale) === count(self::LOCALES);
        $same = count(array_unique(array_map(fn ($v) => json_encode($v), $values))) === 1;
        $structural = ! is_string($first) || in_array($key, self::STRUCTURAL, true) || ($key !== null && str_ends_with($key, '_target'));
        if (! $keepLeaves && $complete && $same && $structural) {
            return $first;
        }

        return $byLocale;
    }

    /** The tree of one language; self::absent() when the node has no text in it. */
    public static function resolve(mixed $node, string $locale): mixed
    {
        if (! is_array($node)) {
            return $node;
        }
        if (self::isLeaf($node)) {
            return array_key_exists($locale, $node) ? $node[$locale] : self::absent();
        }
        $absent = self::absent();
        $out = [];
        if (array_is_list($node)) {
            foreach ($node as $child) {
                $value = self::resolve($child, $locale);
                if ($value !== $absent) {
                    $out[] = $value;
                }
            }

            return $out;
        }
        foreach ($node as $k => $child) {
            $value = self::resolve($child, $locale);
            if ($value !== $absent) {
                $out[$k] = $value;
            }
        }

        return $out;
    }

    /** A language map {en, ar, ru} of scalars (a text leaf), as opposed to a group of the tree. */
    public static function isLeaf(array $node): bool
    {
        if ($node === [] || array_is_list($node) || array_diff_key($node, array_flip(self::LOCALES)) !== []) {
            return false;
        }
        foreach ($node as $value) {
            if (is_array($value) || is_object($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Character limits that protect the layout (brief §8.4): for every text field (list positions as "*"), 115 % of the
     * longest current text in any language, rounded up, so no approved text is ever blocked.
     *
     * @return array<string, int>
     */
    public static function limits(mixed $node, string $path = ''): array
    {
        $longest = [];
        self::walkLengths($node, $path, $longest);
        ksort($longest);

        return array_map(fn (int $n) => (int) ceil($n * 1.15), array_filter($longest, fn (int $n) => $n > 0));
    }

    /** @param array<string, int> $longest */
    private static function walkLengths(mixed $node, string $path, array &$longest): void
    {
        if (! is_array($node)) {
            return;
        }
        if (self::isLeaf($node)) {
            $max = 0;
            foreach ($node as $value) {
                if (is_string($value)) {
                    $max = max($max, mb_strlen($value));
                }
            }
            $longest[$path] = max($longest[$path] ?? 0, $max);

            return;
        }
        $list = array_is_list($node);
        foreach ($node as $k => $child) {
            self::walkLengths($child, $path === '' ? ($list ? '*' : (string) $k) : $path.'.'.($list ? '*' : $k), $longest);
        }
    }
}
