<?php

namespace App\Filament\Cms\Forms;

use App\Content\Cms\Tree;
use App\Filament\Cms\Support\CmsLabels;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Component as Livewire;

/**
 * The one translatable-field wrapper of the admin (docs/briefs/design-2.1-and-admin-cms.md §8.3), used by every edit
 * screen, the page blocks included.
 *
 * A translatable value is {en, ar, ru}. The wrapper makes one field per language under the value's state path
 * (title.en, title.ar, title.ru) and shows only the language chosen in the screen's locale switcher ("Editing: English",
 * App\Filament\Cms\Concerns\HasEditingLocale). The others stay in the form, hidden, so saving never loses them.
 *   - a field missing in the chosen language shows the English text as a grey placeholder and a "Missing" marker;
 *   - Arabic fields are dir="rtl" lang="ar" (the theme gives [lang=ar] the Arabic face);
 *   - a limit (brief §8.4) is a hard maxlength with a visible counter (data-cms-limit, resources/js/filament/cms.js).
 */
final class Translatable
{
    /**
     * One field per language, built by $factory(locale): the factory returns the field for that language (named after
     * the locale), the wrapper adds the language behaviour.
     *
     * @param  Closure(string): Field  $factory
     */
    public static function make(string $name, Closure $factory, string|Closure|null $label = null, ?int $limit = null): Group
    {
        $fields = [];
        foreach (Tree::LOCALES as $locale) {
            $field = $factory($locale);
            $fields[] = self::localise($field, $locale, $label, $limit);
        }

        return Group::make($fields)->statePath($name)->extraAttributes(['class' => 'cms-translatable']);
    }

    public static function text(string $name, string|Closure|null $label = null, ?int $limit = null, bool $required = false): Group
    {
        return self::make($name, fn (string $locale) => TextInput::make($locale)->required($required && $locale === 'en'), $label, $limit);
    }

    public static function textarea(string $name, string|Closure|null $label = null, ?int $limit = null, int $rows = 3, bool $required = false): Group
    {
        return self::make($name, fn (string $locale) => Textarea::make($locale)->rows($rows)->autosize()->required($required && $locale === 'en'), $label, $limit);
    }

    /** Rich text: the brief's allow-list only (p, h2, h3, lists, links, bold, italic, quote), cleaned again on save. */
    public static function rich(string $name, string|Closure|null $label = null): Group
    {
        return self::make($name, fn (string $locale) => self::richEditor($locale), $label);
    }

    public static function richEditor(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3', 'blockquote'], ['bulletList', 'orderedList'], ['undo', 'redo']])
            ->fileAttachments(false);
    }

    /** Add the language behaviour to a field made for one language. */
    public static function localise(Component $field, string $locale, string|Closure|null $label = null, ?int $limit = null): Component
    {
        $isText = $field instanceof TextInput || $field instanceof Textarea;
        if ($label !== null && method_exists($field, 'label')) {
            $field->label($label);
        }
        if ($field instanceof Field) {
            $field->hint(function (Get $get, mixed $state) use ($locale): ?string {
                return self::isMissing($locale, $state, fn (string $other) => $get($other)) ? (string) __('cms.locale.missing') : null;
            })->hintColor('warning')->hintIcon(fn (Get $get, mixed $state) => self::isMissing($locale, $state, fn (string $other) => $get($other)) ? 'heroicon-m-language' : null, (string) __('cms.locale.missing_hint'));
        }
        if ($isText && $locale !== 'en') {
            $field->placeholder(fn (Get $get): ?string => is_string($get('en')) ? $get('en') : null);
        }
        $input = ['lang' => $locale, 'dir' => $locale === 'ar' ? 'rtl' : 'ltr'];
        if ($limit !== null && $isText) {
            $field->maxLength($limit);
            $input['data-cms-limit'] = (string) $limit;
        }
        if ($isText) {
            $field->extraInputAttributes($input, merge: true);
        }
        if (method_exists($field, 'extraFieldWrapperAttributes')) {
            $field->extraFieldWrapperAttributes(fn (Livewire $livewire): array => self::wrapper($livewire, $locale), merge: true);
        } else {
            $field->extraAttributes(fn (Livewire $livewire): array => self::wrapper($livewire, $locale), merge: true);
        }

        return $field;
    }

    /** @return array<string, string> */
    public static function wrapper(Livewire $livewire, string $locale): array
    {
        $current = property_exists($livewire, 'editingLocale') ? (string) $livewire->editingLocale : 'en';

        return [
            'class' => 'cms-locale cms-locale-'.$locale.($current === $locale ? '' : ' cms-locale--off'),
            'data-locale' => $locale,
            'lang' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'aria-label' => CmsLabels::locale($locale),
        ];
    }

    /** A language's value is missing when another language has one and this one does not. */
    public static function isMissing(string $locale, mixed $state, Closure $other): bool
    {
        if (! self::blank($state)) {
            return false;
        }
        foreach (Tree::LOCALES as $l) {
            if ($l !== $locale && ! self::blank($other($l))) {
                return true;
            }
        }

        return false;
    }

    public static function blank(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if (! self::blank($v)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || (is_string($value) && trim(strip_tags($value)) === '');
    }
}
