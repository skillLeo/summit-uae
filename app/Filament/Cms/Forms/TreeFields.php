<?php

namespace App\Filament\Cms\Forms;

use App\Content\Cms\Tree;
use App\Filament\Cms\Support\CmsLabels;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Form fields for content whose shape comes from the data (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.4).
 *
 * shared(): a tree shared by every language with {en, ar, ru} leaves (page blocks, document parts). The structure (keys,
 *   list items and their order) is the same for all languages, so adding or removing a list item does it in every
 *   language; every leaf is a Translatable field. Leaves that are structure (ids, link targets, types) are one plain
 *   field.
 * perLocale(): one language's value of a translatable column that holds a structure (a list of paragraphs, a heading
 *   and its items): each language has its own list, edited inside Translatable::make().
 *
 * Shapes are learnt from every sample of a path, not from the first one: a list whose items differ (an FAQ answer of
 * paragraphs and lists, {type: "p", text} and {type: "list", items}) gets the union of their fields, and a field that
 * belongs to some item types only is shown (and saved) for those types only, so an unchanged form saves exactly the
 * stored structure.
 *
 * Limits (brief §8.4): $limits maps a path with list positions as "*" ("hero.lead", "review.steps.*.title") to the hard
 * limit the importer computed; a text field gets a textarea when its limit or text is long.
 */
final class TreeFields
{
    /** Keys whose value is structure, not copy (shown as a short plain field; the first four are read-only). */
    private const LOCKED = ['id', 'type', 'key', 'ref'];

    /** Keys whose value is structure in any value. */
    private const STRUCTURAL = ['id', 'type', 'key', 'ref', 'target', 'href', 'url', 'anchor', 'marker', 'level', 'code', 'icon', 'service', 'domain'];

    /**
     * Fields for every key of a shared tree node.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, int>  $limits
     * @param  list<string>  $rich  keys whose leaves are rich text
     * @return list<Component>
     */
    public static function shared(array $node, string $path = '', array $limits = [], array $rich = []): array
    {
        return self::groupFields([$node], $path, $limits, $rich, shared: true, locale: null);
    }

    /**
     * One language's field for a structured translatable value: $samples are that column's values (any language, any
     * record) so the field knows every shape.
     *
     * @param  list<mixed>  $samples
     * @param  array<string, int>  $limits  paths below the column ("bio.*", "areas.items.*")
     */
    public static function perLocale(string $locale, array $samples, string $path, array $limits = []): Component
    {
        return self::field($locale, $samples, $path, $limits, [], shared: false, locale: $locale, label: null);
    }

    /**
     * @param  list<array<string, mixed>>  $samples
     * @param  array<string, int>  $limits
     * @param  list<string>  $rich
     * @return list<Component>
     */
    private static function groupFields(array $samples, string $path, array $limits, array $rich, bool $shared, ?string $locale): array
    {
        $keys = [];
        foreach ($samples as $sample) {
            foreach (array_keys($sample) as $key) {
                $keys[(string) $key] = true;
            }
        }
        $typed = $samples !== [] && count(array_filter($samples, fn ($s) => isset($s['type']) && is_string($s['type']))) === count($samples);
        $fields = [];
        foreach (array_keys($keys) as $key) {
            $children = [];
            $types = [];
            foreach ($samples as $sample) {
                if (array_key_exists($key, $sample)) {
                    $children[] = $sample[$key];
                    if ($typed) {
                        $types[$sample['type']] = true;
                    }
                }
            }
            $childPath = $path === '' ? $key : $path.'.'.$key;
            $field = self::field($key, $children, $childPath, $limits, $rich, $shared, $locale, CmsLabels::key($key));
            if ($typed && $key !== 'type') {
                $allTypes = array_unique(array_map(fn ($s) => $s['type'], $samples));
                $only = array_keys($types);
                if (count($only) < count($allTypes)) {
                    $field->visible(fn (Get $get) => in_array($get('type'), $only, true));
                }
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @param  list<mixed>  $samples  every value seen at this path
     * @param  array<string, int>  $limits
     * @param  list<string>  $rich
     */
    private static function field(string $key, array $samples, string $path, array $limits, array $rich, bool $shared, ?string $locale, ?string $label): Component
    {
        $label ??= CmsLabels::key($key);
        $arrays = array_values(array_filter($samples, 'is_array'));
        $limit = $limits[$path] ?? null;

        // a plain value: structure in a shared tree (identical in every language), text in one language's value
        if ($arrays === []) {
            if ($shared || in_array($key, self::STRUCTURAL, true)) {
                $field = self::structural($key, $label);
                $values = array_values(array_unique(array_filter($samples, 'is_scalar')));
                if ($key === 'type' && count($values) === 1) {
                    // one kind of item only (every FAQ paragraph is "p"): nothing to read, so the field is kept but not shown
                    $field->default($values[0])->extraFieldWrapperAttributes(['class' => 'cms-locale--off']);
                }

                return $field;
            }

            return self::textField($key, $limit, self::longest($samples), $locale)->label($label);
        }

        if ($shared && self::allLeaves($arrays)) {
            if (in_array($key, $rich, true)) {
                return Translatable::rich($key, $label);
            }
            $longest = max(array_map(fn ($leaf) => self::longest(array_values($leaf)), $arrays) ?: [0]);

            return Translatable::make($key, fn (string $l) => self::textField($l, $limit, $longest, $l), $label, $limit);
        }

        $lists = array_values(array_filter($arrays, 'array_is_list'));
        if ($lists !== [] && count($lists) === count($arrays)) {
            $items = array_merge(...$lists);
            $itemPath = $path.'.*';

            return self::listField($key, $items, $itemPath, $limits, $rich, $shared, $locale, $label);
        }

        $groups = array_values(array_filter($arrays, fn ($a) => ! array_is_list($a)));

        return Fieldset::make($label)
            ->schema(self::groupFields($groups, $path, $limits, $rich, $shared, $locale))
            ->statePath($key)
            ->columns(1);
    }

    /**
     * @param  list<mixed>  $items
     * @param  array<string, int>  $limits
     * @param  list<string>  $rich
     */
    private static function listField(string $key, array $items, string $itemPath, array $limits, array $rich, bool $shared, ?string $locale, string $label): Repeater
    {
        $repeater = Repeater::make($key)->label($label)->reorderable()->reorderableWithButtons()->collapsible()
            ->defaultItems(0)->addActionLabel((string) __('cms.blocks.item'))
            ->itemLabel(fn (array $state) => self::firstText($state));
        $arrays = array_values(array_filter($items, 'is_array'));
        $limit = $limits[$itemPath] ?? null;

        if ($arrays === []) {
            // a list of plain texts (one language's paragraphs)
            $item = self::textField('text', $limit, self::longest($items), $locale)->hiddenLabel();

            return $repeater->simple($item)->collapsible(false);
        }
        if ($shared && self::allLeaves($arrays)) {
            // a list of texts in every language: each item is {en, ar, ru}
            $longest = max(array_map(fn ($leaf) => self::longest(array_values($leaf)), $arrays) ?: [0]);
            $schema = [];
            foreach (Tree::LOCALES as $l) {
                $schema[] = Translatable::localise(self::textField($l, $limit, $longest, $l), $l, $label, $limit);
            }

            return $repeater->schema($schema);
        }

        return $repeater->schema(self::groupFields(array_values(array_filter($arrays, fn ($a) => ! array_is_list($a))), $itemPath, $limits, $rich, $shared, $locale));
    }

    private static function textField(string $name, ?int $limit, int $longest, ?string $locale): Field
    {
        $long = max($limit ?? 0, (int) ($longest * 1.15)) > 80;
        $field = $long ? Textarea::make($name)->rows(3)->autosize() : TextInput::make($name);
        if ($limit !== null) {
            $field->maxLength($limit);
        }
        if ($locale !== null) {
            $attributes = ['lang' => $locale, 'dir' => $locale === 'ar' ? 'rtl' : 'ltr'];
            if ($limit !== null) {
                $attributes['data-cms-limit'] = (string) $limit;
            }
            $field->extraInputAttributes($attributes, merge: true);
        }

        return $field;
    }

    private static function structural(string $key, string $label): Field
    {
        $field = TextInput::make($key)->label($label)->extraInputAttributes(['dir' => 'ltr'])->maxLength(255);
        if (in_array($key, self::LOCKED, true)) {
            $field->disabled()->dehydrated();
        }

        return $field;
    }

    /** @param list<array<mixed>> $arrays */
    private static function allLeaves(array $arrays): bool
    {
        foreach ($arrays as $node) {
            if (! Tree::isLeaf($node) && ! ($node !== [] && ! array_is_list($node) && array_diff(array_keys($node), Tree::LOCALES) === [])) {
                return false;
            }
        }

        return true;
    }

    /** The first text of an item in the admin's language (else English, else any), for its collapsed label. */
    public static function firstText(mixed $state): ?string
    {
        if (is_string($state)) {
            $text = trim(strip_tags($state));

            return $text === '' ? null : mb_strimwidth($text, 0, 80, '…');
        }
        if (! is_array($state)) {
            return null;
        }
        foreach ([app()->getLocale(), 'en', 'heading', 'title', 'question', 'label', 'text', ...array_keys($state)] as $key) {
            if (in_array($key, self::LOCKED, true)) {
                continue;
            }
            if (array_key_exists($key, $state) && ($text = self::firstText($state[$key])) !== null) {
                return $text;
            }
        }

        return null;
    }

    /** @param list<mixed> $values */
    private static function longest(array $values): int
    {
        $max = 0;
        foreach ($values as $value) {
            if (is_string($value)) {
                $max = max($max, mb_strlen($value));
            }
        }

        return $max;
    }
}
