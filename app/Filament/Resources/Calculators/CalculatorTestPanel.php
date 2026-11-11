<?php

namespace App\Filament\Resources\Calculators;

use App\Content\Cms\Calculators\GratuityRules;
use App\Content\Cms\Calculators\RentRules;
use App\Content\Cms\Editing\Publisher;
use App\Models\Content\CalculatorRuleSet;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use NumberFormatter;

/**
 * The test panel of a calculator rule set (brief §8.2 "a test panel"): a sample case and its result computed with the
 * rules in the form (the draft) and with the published rules of the same version (or, for a version never published,
 * the version the site uses today), side by side, differences marked. The inputs live under cms_test and are never
 * saved. The formula is App\Content\Cms\Calculators\{Gratuity,Rent}Rules, the PHP twin of the public scripts.
 */
final class CalculatorTestPanel
{
    /** @return array<string, mixed> */
    public static function defaults(string $calculator): array
    {
        return $calculator === 'rent'
            ? ['currentAnnualRent' => '80000', 'indexAverageRent' => '100000', 'expiryDate' => '2027-03-31', 'noticeReceivedDate' => '2026-12-15']
            : ['scope' => 'mainland', 'contractType' => 'full', 'startDate' => '2019-01-01', 'lastWorkingDay' => '2026-06-30', 'unpaidAbsenceDays' => '0',
                'basicMonthly' => '10000', 'totalMonthlyWage' => '15000', 'hoursPerWeek' => null, 'fullTimeHoursPerWeek' => null,
                'unusedLeaveDays' => '10', 'noticeDaysInContract' => '30', 'noticeDaysServed' => '0', 'unpaidWages' => '0'];
    }

    /** @return list<Component> */
    public static function gratuityInputs(): array
    {
        $money = fn (string $name) => TextInput::make($name)->label((string) __('cms_site.calculators.test.'.$name))
            ->numeric()->live(onBlur: true)->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']);

        return [
            Group::make([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
                    Select::make('scope')->label((string) __('cms_site.calculators.test.scope'))
                        ->options(fn (Get $get) => CalculatorResource::codeOptions('scopes', (array) $get('../rules.scopes')))
                        ->selectablePlaceholder(false)->live()->dehydrated(false),
                    Select::make('contractType')->label((string) __('cms_site.calculators.test.contractType'))
                        ->options(fn (Get $get) => CalculatorResource::codeOptions('contracts', (array) $get('../rules.contracts')))
                        ->selectablePlaceholder(false)->live()->dehydrated(false),
                    DatePicker::make('startDate')->label((string) __('cms_site.calculators.test.startDate'))->native(false)->format('Y-m-d')->displayFormat('j M Y')->live()->dehydrated(false),
                    DatePicker::make('lastWorkingDay')->label((string) __('cms_site.calculators.test.lastWorkingDay'))->native(false)->format('Y-m-d')->displayFormat('j M Y')->live()->dehydrated(false),
                    $money('unpaidAbsenceDays'),
                    $money('basicMonthly'),
                    $money('totalMonthlyWage'),
                    $money('hoursPerWeek')->visible(fn (Get $get) => $get('contractType') === 'part'),
                    $money('fullTimeHoursPerWeek')->visible(fn (Get $get) => $get('contractType') === 'part'),
                    $money('unusedLeaveDays'),
                    $money('noticeDaysInContract'),
                    $money('noticeDaysServed'),
                    $money('unpaidWages'),
                ]),
            ])->statePath('cms_test'),
            self::results('gratuity'),
        ];
    }

    /** @return list<Component> */
    public static function rentInputs(): array
    {
        return [
            Group::make([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    TextInput::make('currentAnnualRent')->label((string) __('cms_site.calculators.test.currentAnnualRent'))->numeric()->live(onBlur: true)->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('indexAverageRent')->label((string) __('cms_site.calculators.test.indexAverageRent'))->numeric()->live(onBlur: true)->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']),
                    DatePicker::make('noticeReceivedDate')->label((string) __('cms_site.calculators.test.noticeReceivedDate'))->native(false)->format('Y-m-d')->displayFormat('j M Y')->live()->dehydrated(false),
                    DatePicker::make('expiryDate')->label((string) __('cms_site.calculators.test.expiryDate'))->native(false)->format('Y-m-d')->displayFormat('j M Y')->live()->dehydrated(false),
                ]),
            ])->statePath('cms_test'),
            self::results('rent'),
        ];
    }

    private static function results(string $calculator): Html
    {
        return Html::make(function (Get $get, ?Model $record) use ($calculator): HtmlString {
            $input = self::input((array) $get('cms_test'));
            $draft = CalculatorResource::rulesFromForm(
                $record !== null ? (array) (Publisher::workingCopy($record)['rules'] ?? []) : [],
                (array) $get('rules'),
            );
            $published = self::publishedRules($calculator, $record);

            return self::table($calculator, $input, $draft, $published);
        });
    }

    /** The panel's raw state as the formula reads it: dates as YYYY-MM-DD, the rest as typed. */
    public static function input(array $state): array
    {
        foreach (['startDate', 'lastWorkingDay', 'expiryDate', 'noticeReceivedDate'] as $field) {
            $value = $state[$field] ?? null;
            if ($value instanceof \DateTimeInterface) {
                $state[$field] = $value->format('Y-m-d');
            } elseif (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
                $state[$field] = substr($value, 0, 10);
            }
        }

        return $state;
    }

    /** The published rules of this version, else of the version in effect today, else rule set v1's constants. */
    public static function publishedRules(string $calculator, ?Model $record): array
    {
        if ($record instanceof CalculatorRuleSet && $record->status === 'published') {
            return (array) (Publisher::live($record)['rules'] ?? []);
        }
        $current = CalculatorRuleSet::query()->published()->where('calculator', $calculator)
            ->whereDate('effective_from', '<=', now()->toDateString())->orderByDesc('version')->first();

        return $current !== null ? (array) (Publisher::live($current)['rules'] ?? []) : [];
    }

    /** @return array{status: string, rows: array<string, string>, errors: array<string, string>} */
    public static function compute(string $calculator, array $input, array $rules): array
    {
        $estimate = $calculator === 'rent' ? (new RentRules($rules))->estimate($input) : (new GratuityRules($rules))->estimate($input);
        $rows = [];
        $r = $estimate['result'];
        if ($r !== null && $calculator === 'gratuity') {
            $rows = [
                'years' => self::number($r['years'], 3),
                'eligible' => $r['eligible'] ? (string) __('cms_site.calculators.test.yes') : (string) __('cms_site.calculators.test.no_'.$r['reason']),
                'part21' => self::money($r['part21']),
                'part30' => self::money($r['part30']),
                'cap' => self::money($r['cap']).($r['capApplied'] ? ' ('.(string) __('cms_site.calculators.test.cap_applied').')' : ''),
                'gratuity' => self::money($r['gratuity']),
                'leave' => self::money($r['leave']),
                'notice' => self::money($r['notice']),
                'unpaidWages' => self::money($r['unpaidWages']),
                'total' => self::money($r['total']),
            ];
        } elseif ($r !== null) {
            $rows = [
                'gap' => self::number($r['gapBasisPoints'] / 100, 2).' %',
                'increase' => self::number($r['increase'], 0).' %',
                'increaseAmount' => self::money($r['increaseAmount']),
                'newRent' => self::money($r['newRent']),
                'noticeCheck' => $r['notice'] === null ? '—' : (string) __($r['notice']['ok'] ? 'cms_site.calculators.test.notice_ok' : 'cms_site.calculators.test.notice_short', ['days' => $r['notice']['days']]),
            ];
        }

        return ['status' => $estimate['status'], 'rows' => $rows, 'errors' => $estimate['errors'] + array_fill_keys($estimate['missing'], 'missing')];
    }

    private static function table(string $calculator, array $input, array $draft, array $published): HtmlString
    {
        $a = self::compute($calculator, $input, $draft);
        $b = self::compute($calculator, $input, $published);
        $head = '<thead><tr><th scope="col">'.e((string) __('cms_site.calculators.test.result')).'</th><th scope="col">'.e((string) __('cms_site.calculators.test.draft')).'</th><th scope="col">'.e((string) __('cms_site.calculators.test.published')).'</th></tr></thead>';
        $body = '';
        $status = fn (array $x) => (string) __('cms_site.calculators.test.status.'.$x['status']);
        if ($a['status'] !== 'ok' || $b['status'] !== 'ok') {
            $body .= self::row((string) __('cms_site.calculators.test.status_label'), $status($a).self::errors($a), $status($b).self::errors($b));
        }
        foreach (array_unique([...array_keys($a['rows']), ...array_keys($b['rows'])]) as $key) {
            $body .= self::row((string) __('cms_site.calculators.test.rows.'.$key), $a['rows'][$key] ?? '—', $b['rows'][$key] ?? '—', in_array($key, ['total', 'newRent'], true));
        }

        return new HtmlString('<div class="cms-calc-results cms-calc-table-wrap"><table class="cms-calc-table">'.$head.'<tbody>'.$body.'</tbody></table></div>');
    }

    private static function row(string $label, string $draft, string $published, bool $strong = false): string
    {
        $diff = $draft !== $published ? ' class="cms-calc-diff"' : '';
        $cell = fn (string $v) => '<td dir="ltr">'.e($v).'</td>';

        return '<tr'.$diff.($strong ? ' data-total' : '').'><th scope="row">'.e($label).'</th>'.$cell($draft).$cell($published).'</tr>';
    }

    private static function errors(array $x): string
    {
        if ($x['errors'] === []) {
            return '';
        }
        $parts = [];
        foreach ($x['errors'] as $field => $code) {
            $parts[] = (string) __('cms_site.calculators.test.'.$field).': '.(string) __('cms_site.calculators.test.errors.'.$code);
        }

        return ' ('.implode('; ', $parts).')';
    }

    private static function money(float|int $value): string
    {
        return 'AED '.self::number($value, 2);
    }

    private static function number(float|int $value, int $decimals): string
    {
        $formatter = new NumberFormatter('en@numbers=latn', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return (string) $formatter->format($value);
    }
}
