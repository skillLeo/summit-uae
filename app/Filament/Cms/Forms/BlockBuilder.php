<?php

namespace App\Filament\Cms\Forms;

use App\Content\Cms\CopySource;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\RichText;
use App\Content\Cms\HomeBlocks;
use App\Filament\Cms\Support\CmsLabels;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The block builder of pages and record bodies (docs/briefs/design-2.1-and-admin-cms.md §8.4).
 *
 * The blocks column is one list shared by every language: [{id, type, enabled, anchor, background, copy_source, into,
 * locales, limits, data}], with the texts inside data as {en, ar, ru} leaves (§8.3). The builder shows each block with
 *   - its settings: on/off, anchor id, background (white, band or dark only), approval;
 *   - its texts: fields made from the block's own data (TreeFields::shared), with the importer's limits (block.limits);
 *   - drag to re-order, collapse, duplicate and delete. A deleted block can be brought back from a revision.
 *
 * Every existing block is its own builder "variant" (named after its id), because blocks of one type carry different
 * fields (a page's sections). The library adds the generic blocks (rich text, two columns, call-to-action band, image
 * and text) on pages that render their blocks in order (the home, P01). Pages with a fixed template keep their order:
 * their texts are editable, their sections are not moved, added or removed.
 *
 * toState() / fromState() convert between the column and the builder's {type, data} items.
 */
final class BlockBuilder
{
    /** Pages whose public template renders the blocks in their order (resources/views/designs/d2/pages/home.blade.php). */
    public const FLEXIBLE = ['P01'];

    public const BACKGROUNDS = ['white', 'band', 'dark'];

    /** The generic library blocks: their fields, rich-text keys and limits. */
    public const LIBRARY = [
        'rich_text' => ['data' => ['heading' => null, 'body' => 'rich'], 'limits' => ['heading' => 120]],
        'two_columns' => ['data' => ['heading' => null, 'start' => 'rich', 'end' => 'rich'], 'limits' => ['heading' => 120]],
        'cta_band' => ['data' => ['heading' => null, 'body' => null, 'button_label' => null, 'button_target' => 'plain'], 'limits' => ['heading' => 120, 'body' => 320, 'button_label' => 48]],
        'image_text' => ['data' => ['heading' => null, 'body' => 'rich', 'image' => 'plain', 'image_alt' => null, 'image_side' => 'plain'], 'limits' => ['heading' => 120, 'image_alt' => 160]],
    ];

    /**
     * @param  Model|null  $record  null on a create page (only $templates are offered)
     * @param  list<array<string, mixed>>  $templates  body sections the record may add (record bodies: guides, case
     *                                                 studies, practice areas): one per data key, texts empty
     */
    public static function make(?Model $record, string $name = 'blocks', array $templates = []): Builder
    {
        $flexible = $record !== null && in_array((string) $record->getKey(), self::FLEXIBLE, true);
        $blocks = [];
        $have = [];
        foreach ($record !== null ? self::known($record) : [] as $block) {
            $blocks[] = self::variant($block);
            $have[(string) array_key_first((array) ($block['data'] ?? []))] = true;
        }
        if ($flexible) {
            foreach (array_keys(self::LIBRARY) as $type) {
                $blocks[] = self::libraryVariant($type);
            }
        }
        foreach ($templates as $template) {
            $blocks[] = self::variant($template, 'tpl-'.Str::slug((string) array_key_first((array) $template['data'])));
        }
        if ($templates !== []) {
            return Builder::make($name)
                ->label((string) __('cms.blocks.label'))
                ->helperText((string) __('cms.blocks.fixed_order'))
                ->blocks($blocks)
                ->blockNumbers(false)->truncateBlockLabel(false)
                ->collapsible()
                ->collapsed()
                ->addActionLabel((string) __('cms.blocks.add'))
                ->reorderable(false)->cloneable(false)->deletable()->addable()
                ->extraAttributes(['class' => 'cms-blocks']);
        }

        $builder = Builder::make($name)
            ->label((string) __('cms.blocks.label'))
            ->helperText((string) __($flexible ? 'cms.blocks.hint' : 'cms.blocks.fixed_order'))
            ->blocks($blocks)
            ->blockNumbers(false)->truncateBlockLabel(false)
            ->collapsible()
            ->collapsed()
            ->addActionLabel((string) __('cms.blocks.add'))
            ->extraAttributes(['class' => 'cms-blocks']);
        if ($flexible) {
            return $builder->reorderable()->reorderableWithButtons()->cloneable()->deletable()->addable()->blockPickerColumns(2);
        }

        return $builder->reorderable(false)->cloneable(false)->deletable(false)->addable(false);
    }

    /**
     * Every block the record has, live or in its draft (each needs its variant), in order, unique by id.
     *
     * @return list<array<string, mixed>>
     */
    public static function known(Model $record): array
    {
        $seen = [];
        $out = [];
        foreach ([Publisher::workingCopy($record)['blocks'] ?? [], Publisher::live($record)['blocks'] ?? []] as $list) {
            foreach ((array) $list as $block) {
                $id = (string) ($block['id'] ?? '');
                if ($id === '' || isset($seen[$id]) || isset(self::LIBRARY[$block['type'] ?? ''])) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = $block;
            }
        }

        return $out;
    }

    public static function variantName(string $id): string
    {
        return 'b-'.Str::slug(str_replace('.', '-', $id));
    }

    /**
     * Body sections a record of $class may add: one per data key used by any record, with empty texts (the shape of the
     * fullest example), so a new guide gets the same sections as the approved ones.
     *
     * @param  class-string<Model>  $class
     * @return list<array<string, mixed>>
     */
    public static function templates(string $class): array
    {
        $byKey = [];
        foreach ($class::query()->get() as $record) {
            foreach ((array) (Publisher::live($record)['blocks'] ?? []) as $block) {
                $key = (string) array_key_first((array) ($block['data'] ?? []));
                if ($key === '') {
                    continue;
                }
                $size = strlen((string) json_encode($block['data']));
                if (! isset($byKey[$key]) || $size > $byKey[$key][0]) {
                    $byKey[$key] = [$size, $block];
                }
            }
        }
        $out = [];
        foreach ($byKey as $key => [, $block]) {
            $out[] = [
                'id' => 'tpl.'.$key,
                'type' => (string) ($block['type'] ?? 'section'),
                'enabled' => true,
                'anchor' => null,
                'background' => 'white',
                'copy_source' => CopySource::PENDING,
                'into' => (string) ($block['into'] ?? 'field'),
                'locales' => [],
                'limits' => (array) ($block['limits'] ?? []),
                'data' => self::emptied((array) $block['data']),
            ];
        }

        return $out;
    }

    /** The same structure with every text empty (lists keep one item). */
    private static function emptied(array $node): array
    {
        if (\App\Content\Cms\Tree::isLeaf($node)) {
            return ['en' => '', 'ar' => '', 'ru' => ''];
        }
        if (array_is_list($node)) {
            return $node === [] ? [] : [is_array($node[0]) ? self::emptied($node[0]) : ''];
        }
        $out = [];
        foreach ($node as $key => $value) {
            $out[$key] = is_array($value) ? self::emptied($value) : (in_array($key, ['type'], true) ? $value : '');
        }

        return $out;
    }

    /** @param array<string, mixed> $block */
    private static function variant(array $block, ?string $name = null): Block
    {
        $type = (string) ($block['type'] ?? 'section');
        $data = (array) ($block['data'] ?? []);
        $content = $data === []
            ? [Text::make((string) __('cms.blocks.no_texts'))->color('gray')]
            : TreeFields::shared($data, '', (array) ($block['limits'] ?? []));

        return Block::make($name ?? self::variantName((string) $block['id']))
            ->label(fn (?array $state) => self::title($type, $block, $state))
            ->schema(self::schema($content));
    }

    private static function libraryVariant(string $type): Block
    {
        $content = [];
        foreach (self::LIBRARY[$type]['data'] as $key => $kind) {
            $label = (string) __('cms.blocks.fields.'.$key);
            $limit = self::LIBRARY[$type]['limits'][$key] ?? null;
            $content[] = match (true) {
                $kind === 'rich' => Translatable::rich($key, $label),
                $key === 'image_side' => Select::make($key)->label($label)->options(['start' => (string) __('cms.blocks.fields.image_start'), 'end' => (string) __('cms.blocks.fields.image_end')])->default('start')->selectablePlaceholder(false),
                $key === 'image' => MediaPicker::make($key)->label($label),
                $kind === 'plain' => TextInput::make($key)->label($label)->helperText($key === 'button_target' ? (string) __('cms.blocks.fields.button_target_hint') : null)->extraInputAttributes(['dir' => 'ltr'])->maxLength(255),
                $key === 'body' => Translatable::textarea($key, $label, $limit),
                default => Translatable::text($key, $label, $limit),
            };
        }

        return Block::make('lib-'.$type)
            ->label(fn (?array $state) => self::title($type, ['type' => $type], $state))
            ->schema(self::schema($content));
    }

    /** @param list<\Filament\Schemas\Components\Component> $content */
    private static function schema(array $content): array
    {
        return [
            Hidden::make('id'),
            Section::make((string) __('cms.blocks.settings'))
                ->compact()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(['default' => 1, 'md' => 4])->schema([
                        Toggle::make('enabled')->label((string) __('cms.blocks.enabled'))->default(true)->inline(false),
                        TextInput::make('anchor')->label((string) __('cms.blocks.anchor'))->maxLength(64)
                            ->regex('/^[a-z][a-z0-9-]*$/')->extraInputAttributes(['dir' => 'ltr']),
                        Select::make('background')->label((string) __('cms.blocks.background'))
                            ->options(array_combine(self::BACKGROUNDS, array_map(fn ($b) => (string) __('cms.blocks.background_'.$b), self::BACKGROUNDS)))
                            ->default('white')->selectablePlaceholder(false),
                        Select::make('copy_source')->label((string) __('cms.blocks.copy_source'))->options(CmsLabels::copyOptions())
                            ->default(CopySource::PENDING)->selectablePlaceholder(false),
                    ]),
                ]),
            Group::make($content)->statePath('content'),
        ];
    }

    /** @param array<string, mixed> $block */
    private static function title(string $type, array $block, ?array $state): string
    {
        $label = __('cms.blocks.types.'.$type);
        $label = is_string($label) && $label !== 'cms.blocks.types.'.$type ? $label : Str::headline($type);
        if ($type === 'section') {
            $key = array_key_first((array) ($block['data'] ?? []));
            $first = $key !== null ? TreeFields::firstText($block['data'][$key]['heading'] ?? $block['data'][$key] ?? null) : null;
            $label .= $first !== null ? ': '.$first : ($key !== null ? ': '.CmsLabels::key((string) $key) : '');
        }
        $anchor = $state['anchor'] ?? $block['anchor'] ?? null;
        if (is_string($anchor) && $anchor !== '') {
            $label .= ' #'.$anchor;
        }
        if (($state['enabled'] ?? $block['enabled'] ?? true) === false) {
            $label .= ' ('.__('cms.blocks.hidden').')';
        }

        return $label;
    }

    // ------------------------------------------------------------------ column <-> builder state

    /**
     * @param  list<array<string, mixed>>|null  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function toState(?array $blocks): array
    {
        $items = [];
        foreach ((array) $blocks as $block) {
            $type = (string) ($block['type'] ?? 'section');
            $items[] = [
                'type' => isset(self::LIBRARY[$type]) && str_starts_with((string) ($block['id'] ?? ''), 'lib-') ? 'lib-'.$type : self::variantName((string) $block['id']),
                'data' => [
                    'id' => (string) $block['id'],
                    'enabled' => (bool) ($block['enabled'] ?? true),
                    'anchor' => $block['anchor'] ?? null,
                    'background' => in_array($block['background'] ?? null, self::BACKGROUNDS, true) ? $block['background'] : 'white',
                    'copy_source' => in_array($block['copy_source'] ?? null, CopySource::ALL, true) ? $block['copy_source'] : CopySource::DOCX,
                    'content' => (array) ($block['data'] ?? []),
                ],
            ];
        }

        return $items;
    }

    /**
     * The builder's items back into the blocks column. Settings the builder does not show (into, locales, limits) come
     * from the block the item started as; a duplicated block gets a new id; library blocks are sanitised rich text.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function fromState(Model $record, array $items, array $templates = []): array
    {
        $byId = [];
        foreach ($record->exists ? self::known($record) : [] as $block) {
            $byId[(string) $block['id']] = $block;
        }
        $byTemplate = [];
        foreach ($templates as $template) {
            $byTemplate['tpl-'.Str::slug((string) array_key_first((array) $template['data']))] = $template;
        }
        $code = (string) $record->getKey();
        $used = [];
        $out = [];
        foreach (array_values($items) as $item) {
            $data = (array) ($item['data'] ?? []);
            $variant = (string) ($item['type'] ?? '');
            $library = str_starts_with($variant, 'lib-') ? substr($variant, 4) : null;
            $id = (string) ($data['id'] ?? '');
            $origin = $byId[$id] ?? null;
            if ($origin === null && isset($byTemplate[$variant])) {
                $origin = $byTemplate[$variant];
                $id = $code.'.'.array_key_first((array) $origin['data']);
            }
            if ($origin === null && $library === null) {
                foreach ($byId as $block) {
                    if (self::variantName((string) $block['id']) === $variant) {
                        $origin = $block;
                        $id = (string) $block['id'];
                    }
                }
            }
            if ($id === '' || isset($used[$id])) {
                $base = $id !== '' ? $id : ($library !== null ? 'lib-'.$library : $code.'.block');
                $n = 2;
                while (isset($used[$base.'-'.$n]) || isset($byId[$base.'-'.$n])) {
                    $n++;
                }
                $id = $base.'-'.$n;
            }
            if ($library !== null && ! str_starts_with($id, 'lib-')) {
                $id = 'lib-'.$library.'-'.Str::lower(Str::random(6));
            }
            $used[$id] = true;
            $content = (array) ($data['content'] ?? []);
            if ($library !== null) {
                foreach (self::LIBRARY[$library]['data'] as $key => $kind) {
                    if ($kind === 'rich' && isset($content[$key])) {
                        $content[$key] = RichText::cleanAll($content[$key]);
                    }
                }
            }
            $anchor = is_string($data['anchor'] ?? null) && $data['anchor'] !== '' ? $data['anchor'] : null;
            $out[] = [
                'id' => $id,
                'type' => $library ?? (string) ($origin['type'] ?? 'section'),
                'enabled' => (bool) ($data['enabled'] ?? true),
                'anchor' => $anchor,
                'background' => in_array($data['background'] ?? null, self::BACKGROUNDS, true) ? $data['background'] : 'white',
                'copy_source' => in_array($data['copy_source'] ?? null, CopySource::ALL, true) ? $data['copy_source'] : CopySource::PENDING,
                'into' => (string) ($origin['into'] ?? 'field'),
                'data' => self::mergeContent((array) ($origin['data'] ?? []), $content),
                'locales' => $origin['locales'] ?? [],
                'limits' => $origin['limits'] ?? (self::LIBRARY[$library]['limits'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * The edited texts over the block's own data, keeping keys the form never showed in their place and order (the
     * approved structure of the DOCX, §8.3).
     *
     * @param  array<string, mixed>  $origin
     * @param  array<string, mixed>  $edited
     * @return array<string, mixed>
     */
    private static function mergeContent(array $origin, array $edited): array
    {
        if ($origin === []) {
            return $edited;
        }
        $out = [];
        foreach ($origin as $key => $value) {
            $out[$key] = array_key_exists($key, $edited) ? $edited[$key] : $value;
        }
        foreach ($edited as $key => $value) {
            if (! array_key_exists($key, $out)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
