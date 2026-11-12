<?php

namespace App\Filament\Resources\Calculators;

use App\Content\Cms\Calculators\GratuityRules;
use App\Content\Cms\Calculators\RentRules;
use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Editing\Publisher;
use App\Filament\Cms\CmsResource;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Cms\Tables\CmsTable;
use App\Filament\Resources\Calculators\Pages\EditCalculatorRuleSet;
use App\Filament\Resources\Calculators\Pages\ListCalculatorRuleSets;
use App\Models\Content\CalculatorRuleSet;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Calculators (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.11, §9.4; super admin only): the versioned rule sets of
 * the gratuity estimator and the rent increase check. Each version has an effective date; the site uses the published
 * version with the highest number whose date has come (App\Content\ContentRepository::ruleSet()).
 *
 *   - "New version" copies the latest version of a calculator into a new draft (version + 1, an effective date you
 *     choose); nothing changes on the site until it is published.
 *   - The rules are edited as the fields of their JSON (the numbers of the formula, the scopes the Labour Law estimate
 *     covers, the rent bands); the codes the estimator's interface knows (scopes, contract types) are read-only.
 *   - Publishing asks for a confirmation of the legal review (legal impact, §9.4) and writes a revision.
 *   - The test panel computes a sample case with the draft rules and with the published rules, with the same formula as
 *     the public scripts (App\Content\Cms\Calculators\GratuityRules, RentRules; tested equal to them).
 *
 * The public estimators still compute with their own constants (equal to version 1); passing the published rule set
 * to them is a separate step (Phase 3 report §6).
 */
class CalculatorResource extends CmsResource
{
    protected static string $screen = 'calculators';

    protected static ?string $model = CalculatorRuleSet::class;

    protected static ?string $slug = 'calculators';

    protected static string|UnitEnum|null $navigationGroup = 'site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 50;

    /** The numbers of the gratuity rules, in the order of the rule set. */
    public const GRATUITY_NUMBERS = ['year_days', 'month_days', 'first_years', 'days_first_years', 'days_later_years', 'cap_months', 'notice_min', 'notice_max'];

    protected static function labelKey(): string
    {
        return 'cms_site.calculators';
    }

    // ------------------------------------------------------------------ access: super admin only (the Phase 7 hook)




    public static function canCreate(): bool
    {
        return false; // a new version is made by "New version" (a copy of the latest one)
    }




    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof CalculatorRuleSet
            ? (string) __('cms_site.calculators.record_title', ['calculator' => self::calculatorLabel($record->calculator), 'version' => $record->version])
            : static::getModelLabel();
    }

    public static function calculatorLabel(?string $calculator): string
    {
        return in_array($calculator, CalculatorRuleSet::CALCULATORS, true) ? (string) __('cms_site.calculators.names.'.$calculator) : (string) $calculator;
    }

    // ------------------------------------------------------------------ form

    public static function form(Schema $schema): Schema
    {
        /** @var CalculatorRuleSet|null $record */
        $record = $schema->getRecord();
        $calculator = $record?->calculator;
        $limits = Limits::for('calculator_rule_sets');

        return self::confirmLegalReview(CmsForm::layout($schema, [
            Callout::make((string) __('cms_site.calculators.legal_heading'))
                ->description((string) __('cms_site.calculators.legal_body'))
                ->warning(),
            Section::make((string) __('cms_site.calculators.section_version'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    TextInput::make('calculator_label')->label((string) __('cms_site.calculators.calculator'))
                        ->formatStateUsing(fn () => self::calculatorLabel($calculator))
                        ->disabled()->dehydrated(false),
                    TextInput::make('version_label')->label((string) __('cms_site.calculators.version'))
                        ->formatStateUsing(fn (?Model $record) => $record?->getAttribute('version'))
                        ->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']),
                    DatePicker::make('effective_from')->label((string) __('cms_site.calculators.effective_from'))
                        ->helperText((string) __('cms_site.calculators.effective_from_hint'))
                        ->native(false)->displayFormat('j M Y')->format('Y-m-d')->required(),
                ]),
                Translatable::textarea('legal_basis', (string) __('cms_site.calculators.legal_basis'), $limits['legal_basis'] ?? null, rows: 2),
            ]),
            Section::make((string) __('cms_site.calculators.section_rules'))
                ->description((string) __('cms_site.calculators.rules_hint'))
                ->schema($calculator === 'rent' ? self::rentFields() : ($calculator === 'gratuity' ? self::gratuityFields() : [])),
            Section::make((string) __('cms_site.calculators.test_heading'))
                ->description((string) __('cms_site.calculators.test_hint'))
                ->collapsible()
                ->schema($calculator === 'rent' ? CalculatorTestPanel::rentInputs() : ($calculator === 'gratuity' ? CalculatorTestPanel::gratuityInputs() : []))
                ->extraAttributes(['class' => 'cms-calc-test']),
        ]));
    }

    /** @return list<Component> */
    private static function gratuityFields(): array
    {
        $numbers = [];
        foreach (self::GRATUITY_NUMBERS as $name) {
            $numbers[] = self::number('rules.'.$name, (string) __('cms_site.calculators.rules.'.$name), (string) __('cms_site.calculators.rules.'.$name.'_hint'))
                ->minValue(1)->maxValue(1000)->integer();
        }

        return [
            Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema($numbers),
            CheckboxList::make('rules.in_scope')
                ->label((string) __('cms_site.calculators.rules.in_scope'))
                ->helperText((string) __('cms_site.calculators.rules.in_scope_hint'))
                ->options(fn (Get $get) => self::codeOptions('scopes', (array) $get('rules.scopes')))
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->required(),
            Text::make(fn (Get $get) => (string) __('cms_site.calculators.rules.codes', [
                'scopes' => implode(', ', array_values(self::codeOptions('scopes', (array) $get('rules.scopes')))),
                'contracts' => implode(', ', array_values(self::codeOptions('contracts', (array) $get('rules.contracts')))),
            ]))->color('gray')->size('sm'),
        ];
    }

    /** @return list<Component> */
    private static function rentFields(): array
    {
        return [
            self::number('rules.notice_days', (string) __('cms_site.calculators.rules.notice_days'), (string) __('cms_site.calculators.rules.notice_days_hint'))
                ->minValue(1)->maxValue(1000)->integer(),
            Repeater::make('rules.bands')
                ->label((string) __('cms_site.calculators.rules.bands'))
                ->helperText((string) __('cms_site.calculators.rules.bands_hint'))
                ->reorderable(false)
                ->cloneable(false)
                ->minItems(1)
                ->maxItems(10)
                ->addActionLabel((string) __('cms_site.calculators.rules.add_band'))
                ->itemLabel(fn (array $state) => filled($state['upTo'] ?? null)
                    ? (string) __('cms_site.calculators.rules.band_up_to', ['percent' => $state['upTo'], 'increase' => $state['increase'] ?? ''])
                    : (string) __('cms_site.calculators.rules.band_above', ['increase' => $state['increase'] ?? '']))
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        TextInput::make('upTo')->label((string) __('cms_site.calculators.rules.up_to'))
                            ->helperText((string) __('cms_site.calculators.rules.up_to_hint'))
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('increase')->label((string) __('cms_site.calculators.rules.increase'))
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required()->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                ])
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $error = self::bandsError(array_values((array) $value));
                    if ($error !== null) {
                        $fail((string) __('cms_site.calculators.errors.'.$error));
                    }
                }),
        ];
    }

    private static function number(string $name, string $label, ?string $hint = null): TextInput
    {
        return TextInput::make($name)->label($label)->helperText($hint)->numeric()->required()->extraInputAttributes(['dir' => 'ltr']);
    }

    /** @return array<string, string> code => label (the admin's label of a scope or a contract type, else the code) */
    public static function codeOptions(string $kind, array $codes): array
    {
        $out = [];
        foreach ($codes as $code) {
            $key = 'cms_site.calculators.'.$kind.'.'.$code;
            $label = __($key);
            $out[(string) $code] = is_string($label) && $label !== $key ? $label : (string) $code;
        }

        return $out;
    }

    /** Bands: ascending upTo, the last one open (no upTo), every other one with an upTo. */
    public static function bandsError(array $bands): ?string
    {
        if ($bands === []) {
            return 'bands_empty';
        }
        $previous = -1.0;
        foreach ($bands as $i => $band) {
            $upTo = $band['upTo'] ?? null;
            $last = $i === count($bands) - 1;
            if ($last) {
                if ($upTo !== null && $upTo !== '') {
                    return 'bands_last_open';
                }

                continue;
            }
            if ($upTo === null || $upTo === '' || ! is_numeric($upTo)) {
                return 'bands_up_to';
            }
            if ((float) $upTo <= $previous) {
                return 'bands_order';
            }
            $previous = (float) $upTo;
        }

        return null;
    }

    /**
     * The publish action of the Publishing panel asks for the legal review (brief §9.4: settings with legal impact need
     * a confirmation): its modal names the impact and has a required checkbox. Found in the layout's components (the
     * panel is App\Filament\Cms\Forms\CmsForm::panel()).
     */
    private static function confirmLegalReview(Schema $schema): Schema
    {
        $walk = function (mixed $components) use (&$walk): void {
            foreach (is_array($components) ? $components : [$components] as $component) {
                if ($component instanceof Action && $component->getName() === 'cmsPublish') {
                    $component
                        ->modalHeading((string) __('cms_site.calculators.publish_heading'))
                        ->modalDescription((string) __('cms_site.calculators.publish_body'))
                        ->modalIconColor('warning')
                        ->schema([
                            Checkbox::make('legal_review')->label((string) __('cms_site.calculators.publish_confirm'))->accepted()->required(),
                            TextInput::make('note')->label((string) __('cms.field.note'))->helperText((string) __('cms_site.calculators.publish_note_hint'))->maxLength(190)->required(),
                        ]);
                } elseif ($component instanceof ActionGroup) {
                    $walk($component->getActions());
                } elseif ($component instanceof Component) {
                    foreach ((fn () => $this->childComponents)->call($component) as $children) {
                        if (is_array($children) || $children instanceof Component || $children instanceof Action || $children instanceof ActionGroup) {
                            $walk($children);
                        }
                    }
                }
            }
        };
        $walk((fn () => $this->components)->call($schema));

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $working
     * @return array<string, mixed>
     */
    public static function toFormState(Model $record, array $working): array
    {
        $rules = (array) ($working['rules'] ?? []);
        if (isset($rules['bands']) && is_array($rules['bands'])) {
            $rules['bands'] = array_map(fn ($band) => (array) $band, array_values($rules['bands']));
        }
        $working['rules'] = $rules;
        $working['legal_basis'] = array_merge(['en' => null, 'ar' => null, 'ru' => null], (array) ($working['legal_basis'] ?? []));
        $working['cms_test'] = CalculatorTestPanel::defaults((string) $record->getAttribute('calculator'));

        return $working;
    }

    /**
     * The form's rules back into the rule set's JSON, in the stored key order (so an unchanged form saves the same JSON):
     * numbers as integers (or decimals), the covered scopes in the order of the scopes, the bands as {upTo, increase}.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromFormState(Model $record, array $data): array
    {
        unset($data['cms_test']);
        if (! array_key_exists('rules', $data)) {
            return $data;
        }
        $data['rules'] = self::rulesFromForm((array) (Publisher::workingCopy($record)['rules'] ?? []), (array) $data['rules']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function rulesFromForm(array $stored, array $form): array
    {
        $out = [];
        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, $form) || in_array($key, ['scopes', 'contracts'], true)) {
                $out[$key] = $value; // read-only codes, and anything the form does not show
            } elseif ($key === 'in_scope') {
                $chosen = array_map('strval', (array) $form[$key]);
                $out[$key] = array_values(array_filter((array) ($stored['scopes'] ?? $chosen), fn ($scope) => in_array((string) $scope, $chosen, true)));
            } elseif ($key === 'bands') {
                $out[$key] = array_map(fn ($band) => [
                    'upTo' => ($band['upTo'] ?? null) === null || $band['upTo'] === '' ? null : self::numeric($band['upTo']),
                    'increase' => self::numeric($band['increase'] ?? 0),
                ], array_values((array) $form[$key]));
            } elseif (is_int($value) || is_float($value)) {
                $out[$key] = self::numeric($form[$key]);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private static function numeric(mixed $value): int|float
    {
        if (is_int($value)) {
            return $value;
        }
        $number = is_numeric($value) ? $value + 0 : 0;

        return is_float($number) && floor($number) === $number && abs($number) < PHP_INT_MAX ? (int) $number : $number;
    }

    // ------------------------------------------------------------------ list

    public static function table(Table $table): Table
    {
        return CmsTable::configure($table, [
            TextColumn::make('calculator')->label((string) __('cms_site.calculators.calculator'))
                ->formatStateUsing(fn (?string $state) => self::calculatorLabel($state))
                ->sortable()
                ->searchable(query: fn ($query, string $search) => $query->where(fn ($q) => $q->where('calculator', 'like', "%{$search}%")
                    ->orWhere('legal_basis', 'like', "%{$search}%")->orWhere('version', $search))),
            TextColumn::make('version')->label((string) __('cms_site.calculators.version'))
                ->formatStateUsing(fn ($state) => 'v'.$state)->sortable()->extraAttributes(['dir' => 'ltr']),
            TextColumn::make('effective_from')->label((string) __('cms_site.calculators.effective_from'))
                ->date('j M Y')->sortable(),
            TextColumn::make('cms_in_effect')->label((string) __('cms_site.calculators.in_effect'))
                ->state(fn (CalculatorRuleSet $record) => self::inEffect($record) ? (string) __('cms_site.calculators.in_effect_yes') : null)
                ->badge()->color('success')->icon(Heroicon::OutlinedCheckCircle),
        ], [
            SelectFilter::make('calculator')->label((string) __('cms_site.calculators.calculator'))
                ->options(array_combine(CalculatorRuleSet::CALCULATORS, array_map(fn ($c) => self::calculatorLabel($c), CalculatorRuleSet::CALCULATORS))),
        ], exportName: 'calculator-rules')
            ->defaultSort(fn ($query) => $query->orderBy('calculator')->orderByDesc('version'))
            ->recordActions([EditAction::make()])
            ->toolbarActions([]);
    }

    /** The version the site uses today: published, effective, the highest number (ContentRepository::ruleSet()). */
    public static function inEffect(CalculatorRuleSet $record): bool
    {
        if ($record->status !== 'published') {
            return false;
        }
        $current = CalculatorRuleSet::query()->published()->where('calculator', $record->calculator)
            ->whereDate('effective_from', '<=', now()->toDateString())->orderByDesc('version')->first();

        return $current?->getKey() === $record->getKey();
    }

    /** The latest version of a calculator (any status, not in the Trash). */
    public static function latest(string $calculator): ?CalculatorRuleSet
    {
        return CalculatorRuleSet::query()->where('calculator', $calculator)->orderByDesc('version')->first();
    }

    /**
     * A new draft version copied from the latest version of the calculator: version + 1, the effective date given, the
     * latest version's working copy (its draft, else its published rules and legal basis). Pending client approval.
     */
    public static function newVersion(string $calculator, string $effectiveFrom, ?int $userId = null): CalculatorRuleSet
    {
        $latest = self::latest($calculator);
        $working = $latest !== null ? Publisher::workingCopy($latest) : [];
        $version = (int) CalculatorRuleSet::withTrashed()->where('calculator', $calculator)->max('version') + 1;
        $record = new CalculatorRuleSet;
        $record->setRawAttributes([
            'calculator' => $calculator,
            'version' => $version,
            'status' => 'draft',
            'copy_source' => 'pending',
            'created_by' => $userId ?? auth()->id(),
        ]);
        Publisher::saveDraft($record, [
            'calculator' => $calculator,
            'version' => $version,
            'effective_from' => $effectiveFrom,
            'rules' => $working['rules'] ?? ($calculator === 'rent' ? RentRules::DEFAULTS : GratuityRules::DEFAULTS),
            'legal_basis' => $working['legal_basis'] ?? null,
        ], $userId);

        return $record;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalculatorRuleSets::route('/'),
            'edit' => EditCalculatorRuleSet::route('/{record}/edit'),
        ];
    }
}
