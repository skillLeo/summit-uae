<?php

namespace App\Content\Cms\Editing;

use App\Content\Cms\CopySource;
use App\Content\Cms\Tree;
use App\Models\Content\UiString;
use Illuminate\Database\Eloquent\Model;

/**
 * The Approval report (docs/briefs/design-2.1-and-admin-cms.md §8.7): every item whose copy is pending client approval
 * (copy_source = pending): whole records (document samples, rule sets, new records, interface strings written by the
 * team) and page or body blocks marked pending inside a record whose own copy is approved. One row per item with its
 * texts in English, Arabic and Russian, so the client can approve per language.
 */
final class ApprovalReport
{
    /**
     * @return list<array{id: string, kind: string, type: string, key: string, label: string, where: string, en: string, ar: string, ru: string, model: Model}>
     */
    public static function rows(): array
    {
        $rows = [];
        foreach (Registry::models() as $type => $class) {
            foreach ($class::query()->get() as $record) {
                $working = Publisher::workingCopy($record);
                if ($record->getAttribute('copy_source') === CopySource::PENDING) {
                    $texts = self::texts($record, $working);
                    $rows[] = [
                        'id' => $type.':'.$record->getKey(),
                        'kind' => $record instanceof UiString ? 'ui' : 'record',
                        'type' => $type,
                        'key' => (string) ($record instanceof UiString ? $record->group.'.'.$record->key : $record->getKey()),
                        'label' => Registry::label($record),
                        'where' => self::where($record),
                        ...$texts,
                        'model' => $record,
                    ];

                    continue;
                }
                foreach ((array) ($working['blocks'] ?? []) as $block) {
                    if (($block['copy_source'] ?? null) !== CopySource::PENDING) {
                        continue;
                    }
                    $texts = ['en' => [], 'ar' => [], 'ru' => []];
                    foreach (Diff::flatten((array) ($block['data'] ?? [])) as $path => $value) {
                        $locale = substr($path, -2);
                        if (in_array($locale, Tree::LOCALES, true) && str_ends_with($path, '.'.$locale)) {
                            $texts[$locale][] = strip_tags($value);
                        }
                    }
                    $rows[] = [
                        'id' => $type.':'.$record->getKey().':'.$block['id'],
                        'kind' => 'block',
                        'type' => $type,
                        'key' => (string) $block['id'],
                        'label' => Registry::label($record).': '.self::blockName((string) ($block['type'] ?? 'section')),
                        'where' => self::where($record, $block['anchor'] ?? null),
                        'en' => implode("\n", $texts['en']),
                        'ar' => implode("\n", $texts['ar']),
                        'ru' => implode("\n", $texts['ru']),
                        'model' => $record,
                    ];
                }
            }
        }

        return $rows;
    }

    /** @return array{en: string, ar: string, ru: string} the record's translatable texts per language */
    private static function texts(Model $record, array $working): array
    {
        $out = ['en' => [], 'ar' => [], 'ru' => []];
        foreach (Registry::fields($record) as $column => $kind) {
            if (! in_array($kind, [Registry::TRANSLATABLE, Registry::TREE], true) || in_array($column, Registry::NOT_TRANSLATED, true)) {
                continue;
            }
            $value = $working[$column] ?? null;
            if (! is_array($value)) {
                continue;
            }
            if ($kind === Registry::TRANSLATABLE) {
                foreach (Tree::LOCALES as $locale) {
                    if (isset($value[$locale])) {
                        $out[$locale][] = is_array($value[$locale]) ? implode("\n", Diff::flatten($value[$locale])) : strip_tags((string) $value[$locale]);
                    }
                }
            } else {
                foreach (Diff::flatten($value) as $path => $text) {
                    $locale = substr($path, -2);
                    if (in_array($locale, Tree::LOCALES, true) && str_ends_with($path, '.'.$locale)) {
                        $out[$locale][] = strip_tags($text);
                    }
                }
            }
        }

        return array_map(fn (array $lines) => implode("\n", array_filter($lines, fn ($l) => trim($l) !== '')), $out);
    }

    private static function where(Model $record, ?string $anchor = null): string
    {
        [$path, $fragment] = Preview::target($record, 'en');
        $fragment = $anchor ?? $fragment;

        return $path.($fragment ? '#'.$fragment : '');
    }

    private static function blockName(string $type): string
    {
        $label = __('cms.blocks.types.'.$type);

        return is_string($label) && $label !== 'cms.blocks.types.'.$type ? $label : $type;
    }
}
