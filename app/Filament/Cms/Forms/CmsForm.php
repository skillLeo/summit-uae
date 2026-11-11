<?php

namespace App\Filament\Cms\Forms;

use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\TranslationStatus;
use App\Content\Cms\Tree;
use App\Filament\Cms\Actions\CmsActions;
use App\Filament\Cms\Support\CmsLabels;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component as Livewire;

/**
 * The layout of every CMS edit screen (docs/briefs/design-2.1-and-admin-cms.md §9.3, §8.3): fields on the start side
 * under one locale switcher ("Editing: English"), and a sticky Publishing panel on the end side with the status, the
 * schedule, who edited it last and when, the translation and approval status, the revision count, Preview and Publish.
 */
final class CmsForm
{
    /**
     * @param  list<Component>  $fields
     */
    public static function layout(Schema $schema, array $fields): Schema
    {
        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 3])
                ->extraAttributes(['class' => 'cms-edit'])
                ->schema([
                    Group::make([self::localeSwitcher(), ...$fields])->columnSpan(['default' => 1, 'lg' => 2]),
                    self::panel()->columnSpan(['default' => 1, 'lg' => 1]),
                ]),
        ]);
    }

    public static function localeSwitcher(): ToggleButtons
    {
        return ToggleButtons::make('cms_editing_locale')
            ->label(fn (Livewire $livewire) => (string) __('cms.locale.editing', ['language' => CmsLabels::locale(self::current($livewire))]))
            ->options(fn (?Model $record) => self::localeOptions($record))
            ->inline()
            ->grouped()
            ->live()
            ->dehydrated(false)
            ->afterStateUpdated(function (?string $state, Livewire $livewire) {
                if ($state !== null && method_exists($livewire, 'setEditingLocale')) {
                    $livewire->setEditingLocale($state);
                }
            })
            ->extraAttributes(['class' => 'cms-locale-switcher']);
    }

    /** @return array<string, string> */
    private static function localeOptions(?Model $record): array
    {
        $missing = $record?->exists ? TranslationStatus::missing($record) : [];
        $out = [];
        foreach (Tree::LOCALES as $locale) {
            $count = count($missing[$locale] ?? []);
            $out[$locale] = CmsLabels::locale($locale).($count > 0 ? ' ('.trans_choice('cms.locale.missing_count', $count, ['count' => $count]).')' : '');
        }

        return $out;
    }

    private static function current(Livewire $livewire): string
    {
        return property_exists($livewire, 'editingLocale') ? (string) $livewire->editingLocale : 'en';
    }

    public static function panel(): Section
    {
        return Section::make((string) __('cms.panel.heading'))
            ->extraAttributes(['class' => 'cms-publish-panel'])
            ->schema([
                Text::make(fn (?Model $record) => $record?->exists ? CmsLabels::stateLabel(CmsLabels::state($record)) : CmsLabels::stateLabel('draft'))
                    ->badge()
                    ->color(fn (?Model $record) => $record?->exists ? CmsLabels::stateColor(CmsLabels::state($record)) : 'gray')
                    ->icon(fn (?Model $record) => CmsLabels::stateIcon($record?->exists ? CmsLabels::state($record) : 'draft')),
                Text::make(fn (?Model $record) => $record?->exists && $record->getAttribute('publish_at') !== null
                    ? (string) __('cms.panel.scheduled', ['date' => CmsLabels::dubai($record->getAttribute('publish_at'))])
                    : null)
                    ->visible(fn (?Model $record) => $record?->exists && $record->getAttribute('publish_at') !== null),
                Text::make(fn (?Model $record) => $record?->exists && $record->getAttribute('status') === 'published' ? (string) __('cms.panel.draft_hint') : (string) __('cms.panel.new_hint'))
                    ->color('gray')->size('sm'),
                Text::make(fn (?Model $record) => self::lastEdited($record))->size('sm')->visible(fn (?Model $record) => (bool) $record?->exists),
                Text::make(fn (?Model $record) => self::translationLine($record))->size('sm')->visible(fn (?Model $record) => (bool) $record?->exists),
                Text::make(fn (?Model $record) => (string) __('cms.panel.approval').': '.CmsLabels::copy($record?->getAttribute('copy_source')))
                    ->badge()
                    ->color(fn (?Model $record) => CmsLabels::copyColor($record?->getAttribute('copy_source')))
                    ->icon(fn (?Model $record) => CmsLabels::copyIcon($record?->getAttribute('copy_source')))
                    ->visible(fn (?Model $record) => (bool) $record?->exists),
                Text::make(fn (?Model $record) => $record?->exists && method_exists($record, 'revisions')
                    ? trans_choice('cms.panel.revisions', $n = $record->revisions()->count(), ['count' => $n])
                    : null)->size('sm')->color('gray')->visible(fn (?Model $record) => (bool) $record?->exists),
                Actions::make([
                    CmsActions::preview(),
                    CmsActions::publish(),
                ])->visible(fn (?Model $record) => (bool) $record?->exists)->fullWidth(),
            ]);
    }

    private static function lastEdited(?Model $record): string
    {
        if ($record === null) {
            return '';
        }
        $name = $record->getAttribute('updated_by') !== null ? optional($record->updatedBy)->name : null;

        return (string) __('cms.panel.last_edited').': '.(CmsLabels::dubai($record->getAttribute('updated_at')) ?? (string) __('cms.field.never'))
            .($name !== null ? ' '.(string) __('cms.panel.by', ['name' => $name]) : '');
    }

    private static function translationLine(?Model $record): string
    {
        if ($record === null) {
            return '';
        }
        $missing = array_keys(array_filter(TranslationStatus::missing($record)));

        return (string) __('cms.panel.translations').': '.($missing === []
            ? (string) __('cms.translation.complete')
            : (string) __('cms.translation.missing_in', ['languages' => implode(', ', array_map(CmsLabels::locale(...), $missing))]));
    }

    // ------------------------------------------------------------------ common fields

    /**
     * A translatable column whose value is a structure in each language (paragraphs, a heading with its items, links):
     * the field learns every shape from all records of the model in all languages (TreeFields::perLocale()).
     *
     * @param  class-string<Model>  $model
     * @param  array<string, int>  $limits
     */
    public static function structured(string $model, string $column, string $label, array $limits = []): Group
    {
        $samples = self::samples($model, $column);

        return Translatable::make($column, fn (string $locale) => TreeFields::perLocale($locale, $samples, $column, $limits), $label);
    }

    /**
     * Every language's value of a translatable column across the model's records (live and drafts).
     *
     * @param  class-string<Model>  $model
     * @return list<mixed>
     */
    public static function samples(string $model, string $column): array
    {
        static $memo = [];
        if (isset($memo[$model][$column])) {
            return $memo[$model][$column];
        }
        $out = [];
        foreach ($model::query()->withTrashed()->get() as $record) {
            foreach ([Publisher::live($record)[$column] ?? null, (Publisher::draft($record) ?? [])[$column] ?? null] as $value) {
                if (is_array($value)) {
                    foreach (Tree::LOCALES as $locale) {
                        if (array_key_exists($locale, $value) && $value[$locale] !== null) {
                            $out[] = $value[$locale];
                        }
                    }
                }
            }
        }

        return $memo[$model][$column] = $out;
    }

    /**
     * The SEO fields of a record (brief §8.2 "SEO: per record"): title, description and social image. The global defaults
     * are on the SEO screen.
     *
     * @param  array<string, int>  $limits
     */
    public static function seo(array $limits = [], bool $image = true): Section
    {
        return Section::make((string) __('cms.field.seo_title'))
            ->heading('SEO')
            ->collapsible()
            ->collapsed()
            ->schema(array_values(array_filter([
                Translatable::text('seo_title', (string) __('cms.field.seo_title'), $limits['seo_title'] ?? 70),
                Translatable::textarea('meta_description', (string) __('cms.field.meta_description'), $limits['meta_description'] ?? 200, rows: 2),
                $image ? MediaPicker::make('og_image')->label((string) __('cms.field.og_image')) : null,
            ])));
    }

    public static function slug(): TextInput
    {
        return TextInput::make('slug')
            ->label((string) __('cms.field.slug'))
            ->helperText((string) __('cms.field.slug_hint'))
            ->extraInputAttributes(['dir' => 'ltr'])
            ->maxLength(120);
    }

    public static function anchor(): TextInput
    {
        return TextInput::make('anchor')
            ->label((string) __('cms.field.anchor'))
            ->helperText((string) __('cms.field.anchor_hint'))
            ->regex('/^[a-z][a-z0-9-]*$/')
            ->maxLength(64)
            ->extraInputAttributes(['dir' => 'ltr']);
    }

    public static function position(): TextInput
    {
        return TextInput::make('position')->label((string) __('cms.field.position'))->numeric()->minValue(0)->maxValue(65535)->integer()->required();
    }

    /** The draft is what the form shows; this keeps a reference to it for closures that need the record's values. */
    public static function working(?Model $record, string $field): mixed
    {
        return $record?->exists ? (Publisher::workingCopy($record)[$field] ?? null) : null;
    }

    /** $get of a sibling, for closures. */
    public static function get(Get $get, string $path): mixed
    {
        return $get($path);
    }
}
