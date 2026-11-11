<?php

namespace App\Content\Cms\Calculators;

/**
 * The end-of-service gratuity estimate of the public calculator (resources/js/d2/tools/gratuity.js), line for line in
 * PHP, with its constants taken from a rule set (calculator_rule_sets.rules of "gratuity": year_days, month_days,
 * first_years, days_first_years, days_later_years, cap_months, notice_min, notice_max, scopes, in_scope, contracts).
 * The admin's test panel runs it with the draft rules and with the published rules; the public estimator keeps its own
 * script (rule set v1 equals its constants: tests/Feature/Cms/Admin/CalculatorRulesTest.php).
 *
 *   serviceDays = daysInclusive(start, last working day) − unpaid absence
 *   years       = serviceDays / year_days; eligible = not temporary and years ≥ 1
 *   daily       = basic / month_days
 *   part21      = min(years, first_years) × days_first_years × daily × ratio
 *   part30      = max(years − first_years, 0) × days_later_years × daily × ratio
 *   gratuity    = min(part21 + part30, cap_months × (total wage or basic))
 *   leave       = unused leave days × daily; notice = unserved notice × wage / month_days
 *   total       = gratuity + leave + notice + unpaid wages (each rounded to fils)
 */
final class GratuityRules
{
    public const MAX_DAYS = 36500;

    public const MAX_HOURS = 168;

    /** Rule set v1 (the script's constants), used when a rule is missing. */
    public const DEFAULTS = [
        'year_days' => 365, 'month_days' => 30, 'first_years' => 5, 'days_first_years' => 21, 'days_later_years' => 30,
        'cap_months' => 24, 'notice_min' => 30, 'notice_max' => 90,
        'scopes' => ['mainland', 'freezone', 'difc', 'adgm', 'government', 'domestic'],
        'in_scope' => ['mainland', 'freezone'],
        'contracts' => ['full', 'part', 'temporary'],
    ];

    /** @var array<string, mixed> */
    private array $r;

    /** @param array<string, mixed> $rules */
    public function __construct(array $rules = [])
    {
        $this->r = $rules + self::DEFAULTS;
    }

    /**
     * gratuity.js estimate(): {status, values, errors, missing, result}; status out_of_scope | invalid | incomplete | ok.
     *
     * @param  array<string, mixed>  $input
     * @return array{status: string, values: array<string, mixed>, errors: array<string, string>, missing: list<string>, result: array<string, mixed>|null}
     */
    public function estimate(array $input): array
    {
        $values = $this->normalise($input);
        if (! in_array($values['scope'], (array) $this->r['in_scope'], true)) {
            return ['status' => 'out_of_scope', 'values' => $values, 'errors' => [], 'missing' => [], 'result' => null];
        }
        ['errors' => $errors, 'missing' => $missing] = $this->validate($values);
        if ($errors !== []) {
            return ['status' => 'invalid', 'values' => $values, 'errors' => $errors, 'missing' => $missing, 'result' => null];
        }
        if ($missing !== []) {
            return ['status' => 'incomplete', 'values' => $values, 'errors' => [], 'missing' => $missing, 'result' => null];
        }

        return ['status' => 'ok', 'values' => $values, 'errors' => [], 'missing' => [], 'result' => $this->calculate($values)];
    }

    /** @return array<string, mixed> */
    public function normalise(array $input): array
    {
        $text = fn (mixed $v) => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $scope = $input['scope'] ?? null;
        $contract = $input['contractType'] ?? null;

        return [
            'scope' => is_string($scope) && in_array($scope, (array) $this->r['scopes'], true) ? $scope : 'mainland',
            'startDate' => $text($input['startDate'] ?? null),
            'lastWorkingDay' => $text($input['lastWorkingDay'] ?? null),
            'unpaidAbsenceDays' => Money::readNumber($input['unpaidAbsenceDays'] ?? null),
            'basicMonthly' => Money::readNumber($input['basicMonthly'] ?? null),
            'totalMonthlyWage' => Money::readNumber($input['totalMonthlyWage'] ?? null),
            'contractType' => is_string($contract) && in_array($contract, (array) $this->r['contracts'], true) ? $contract : 'full',
            'hoursPerWeek' => Money::readNumber($input['hoursPerWeek'] ?? null),
            'fullTimeHoursPerWeek' => Money::readNumber($input['fullTimeHoursPerWeek'] ?? null),
            'unusedLeaveDays' => Money::readNumber($input['unusedLeaveDays'] ?? null),
            'noticeDaysInContract' => Money::readNumber($input['noticeDaysInContract'] ?? null),
            'noticeDaysServed' => Money::readNumber($input['noticeDaysServed'] ?? null),
            'unpaidWages' => Money::readNumber($input['unpaidWages'] ?? null),
        ];
    }

    /** @return array{errors: array<string, string>, missing: list<string>} */
    public function validate(array $v): array
    {
        $errors = [];
        $missing = [];
        foreach (['startDate', 'lastWorkingDay'] as $field) {
            if ($v[$field] === null) {
                $missing[] = $field;
            } elseif (! Money::isDate($v[$field])) {
                $errors[$field] = 'date';
            }
        }
        $calendarDays = isset($errors['startDate']) || isset($errors['lastWorkingDay']) || ! $v['startDate'] || ! $v['lastWorkingDay']
            ? null
            : Money::daysInclusive($v['startDate'], $v['lastWorkingDay']);
        if ($calendarDays !== null && $calendarDays < 1) {
            $errors['lastWorkingDay'] = 'end_before_start';
        }

        $absence = self::daysError($v['unpaidAbsenceDays']);
        if ($absence !== null) {
            $errors['unpaidAbsenceDays'] = $absence;
        } elseif ($v['unpaidAbsenceDays'] !== null && floor((float) $v['unpaidAbsenceDays']) != $v['unpaidAbsenceDays']) {
            $errors['unpaidAbsenceDays'] = 'whole_days';
        } elseif ($calendarDays !== null && $calendarDays >= 1 && ($v['unpaidAbsenceDays'] ?? 0) >= $calendarDays) {
            $errors['unpaidAbsenceDays'] = 'absence_exceeds_service';
        }

        if ($v['basicMonthly'] === null) {
            $missing[] = 'basicMonthly';
        } else {
            $basic = self::amountError($v['basicMonthly'], positive: true);
            if ($basic !== null) {
                $errors['basicMonthly'] = $basic;
            }
        }
        $total = self::amountError($v['totalMonthlyWage']);
        if ($total !== null) {
            $errors['totalMonthlyWage'] = $total;
        } elseif ($v['totalMonthlyWage'] !== null && $v['basicMonthly'] !== null && ! isset($errors['basicMonthly']) && $v['totalMonthlyWage'] < $v['basicMonthly']) {
            $errors['totalMonthlyWage'] = 'total_below_basic';
        }

        if ($v['contractType'] === 'part') {
            foreach (['hoursPerWeek', 'fullTimeHoursPerWeek'] as $field) {
                if ($v[$field] === null) {
                    $missing[] = $field;
                } else {
                    $hours = self::hoursError($v[$field]);
                    if ($hours !== null) {
                        $errors[$field] = $hours;
                    }
                }
            }
            if (! isset($errors['hoursPerWeek']) && ! isset($errors['fullTimeHoursPerWeek']) && $v['hoursPerWeek'] !== null && $v['fullTimeHoursPerWeek'] !== null
                && $v['hoursPerWeek'] > $v['fullTimeHoursPerWeek']) {
                $errors['hoursPerWeek'] = 'hours_exceed';
            }
        }

        $leave = self::daysError($v['unusedLeaveDays']);
        if ($leave !== null) {
            $errors['unusedLeaveDays'] = $leave;
        }
        if ($v['noticeDaysInContract'] !== null) {
            if (is_nan((float) $v['noticeDaysInContract'])) {
                $errors['noticeDaysInContract'] = 'not_number';
            } elseif ($v['noticeDaysInContract'] < $this->r['notice_min'] || $v['noticeDaysInContract'] > $this->r['notice_max']) {
                $errors['noticeDaysInContract'] = 'notice_range';
            }
        }
        $served = self::daysError($v['noticeDaysServed']);
        if ($served !== null) {
            $errors['noticeDaysServed'] = $served;
        }
        $unpaid = self::amountError($v['unpaidWages']);
        if ($unpaid !== null) {
            $errors['unpaidWages'] = $unpaid;
        }

        return ['errors' => $errors, 'missing' => $missing];
    }

    /** gratuity.js calculate() for valid, complete values. @return array<string, mixed> */
    public function calculate(array $v): array
    {
        $yearDays = (float) $this->r['year_days'];
        $monthDays = (float) $this->r['month_days'];
        $firstYears = (float) $this->r['first_years'];
        $calendarDays = (int) Money::daysInclusive($v['startDate'], $v['lastWorkingDay']);
        $unpaidAbsenceDays = $v['unpaidAbsenceDays'] ?? 0;
        $serviceDays = $calendarDays - $unpaidAbsenceDays;
        $years = $serviceDays / $yearDays;
        $contract = $v['contractType'];
        $eligible = $contract !== 'temporary' && $years >= 1;
        $daily = $v['basicMonthly'] / $monthDays;
        $ratio = $contract === 'part' ? $v['hoursPerWeek'] / $v['fullTimeHoursPerWeek'] : 1;

        $g21 = min($years, $firstYears) * $this->r['days_first_years'] * $daily;
        $g30 = max($years - $firstYears, 0) * $this->r['days_later_years'] * $daily;
        $part21 = $eligible ? Money::round2($g21 * $ratio) : 0;
        $part30 = $eligible ? Money::round2($g30 * $ratio) : 0;
        $raw = Money::sum($part21, $part30);

        $capBasis = $v['totalMonthlyWage'] ? 'total' : 'basic';
        $wage = $v['totalMonthlyWage'] ?: $v['basicMonthly'];
        $cap = Money::round2($this->r['cap_months'] * $wage);
        $capApplied = $eligible && $raw > $cap;
        $gratuity = $eligible ? min($raw, $cap) : 0;

        $leave = Money::round2(($v['unusedLeaveDays'] ?? 0) * $daily);
        $noticeDaysOwed = $v['noticeDaysInContract'] === null ? 0 : max($v['noticeDaysInContract'] - ($v['noticeDaysServed'] ?? 0), 0);
        $notice = Money::round2(($noticeDaysOwed * $wage) / $monthDays);
        $unpaidWages = Money::round2($v['unpaidWages'] ?? 0);
        $total = Money::sum($gratuity, $leave, $notice, $unpaidWages);

        return [
            'calendarDays' => $calendarDays,
            'calendarYears' => $calendarDays / $yearDays,
            'unpaidAbsenceDays' => $unpaidAbsenceDays,
            'serviceDays' => $serviceDays,
            'years' => $years,
            'contract' => $contract,
            'ratio' => $ratio,
            'eligible' => $eligible,
            'reason' => $eligible ? null : ($contract === 'temporary' ? 'temporary' : 'under_one_year'),
            'daily' => $daily,
            'part21' => $part21,
            'part30' => $part30,
            'raw' => $raw,
            'capBasis' => $capBasis,
            'cap' => $cap,
            'capApplied' => $capApplied,
            'gratuity' => $gratuity,
            'leave' => $leave,
            'noticeDaysOwed' => $noticeDaysOwed,
            'notice' => $notice,
            'unpaidWages' => $unpaidWages,
            'total' => $total,
        ];
    }

    private static function amountError(float|int|null $value, bool $positive = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_nan((float) $value)) {
            return 'not_number';
        }
        if ($value < 0) {
            return 'negative';
        }
        if ($positive && $value == 0) {
            return 'positive';
        }

        return $value > Money::MAX_AMOUNT ? 'too_large' : null;
    }

    private static function daysError(float|int|null $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_nan((float) $value)) {
            return 'not_number';
        }
        if ($value < 0) {
            return 'negative';
        }

        return $value > self::MAX_DAYS ? 'too_large' : null;
    }

    private static function hoursError(float|int|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_nan((float) $value) || $value <= 0 || $value > self::MAX_HOURS ? 'hours' : null;
    }
}
