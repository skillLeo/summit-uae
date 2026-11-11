<?php

namespace App\Content\Cms\Editing;

/**
 * Field-by-field difference of two versions of a record (docs/briefs/design-2.1-and-admin-cms.md §8.5: the revision list
 * shows it before Restore). Values are flattened to paths ("title.ar", "blocks.P01.hero.data.hero.lead.en"); list items
 * that carry an id (blocks, menu items) are keyed by it, so moving a block reads as an order change, not as every
 * block changing. The order itself is its own row ("blocks (order)").
 */
final class Diff
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<array{field: string, path: string, before: string|null, after: string|null}>
     */
    public static function rows(array $before, array $after): array
    {
        $a = self::flatten($before);
        $b = self::flatten($after);
        $rows = [];
        foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $path) {
            $old = $a[$path] ?? null;
            $new = $b[$path] ?? null;
            if ($old === $new) {
                continue;
            }
            $rows[] = ['field' => explode('.', $path, 2)[0], 'path' => $path, 'before' => $old, 'after' => $new];
        }

        return $rows;
    }

    /** @return array<string, string> path => printable value */
    public static function flatten(mixed $value, string $path = ''): array
    {
        if (! is_array($value)) {
            if ($path === '' || $value === null || $value === '') {
                return [];
            }

            return [$path => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value];
        }
        $out = [];
        if (array_is_list($value) && $value !== [] && self::identified($value)) {
            $order = [];
            foreach ($value as $item) {
                $id = (string) $item['id'];
                $order[] = $id;
                $out += self::flatten(array_diff_key($item, ['id' => 1]), $path.'.'.$id);
            }
            $out[$path.' (order)'] = implode(', ', $order);

            return $out;
        }
        foreach ($value as $key => $child) {
            $out += self::flatten($child, $path === '' ? (string) $key : $path.'.'.$key);
        }

        return $out;
    }

    /** @param list<mixed> $items */
    private static function identified(array $items): bool
    {
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['id']) || ! is_scalar($item['id'])) {
                return false;
            }
        }

        return count(array_unique(array_map(fn ($i) => (string) $i['id'], $items))) === count($items);
    }
}
