<?php

namespace App\Content\Cms\Calculators;

/**
 * The Dubai rent increase check of the public calculator (resources/js/d2/tools/rent.js), line for line in PHP, with its
 * constants taken from a rule set (calculator_rule_sets.rules of "rent": notice_days, bands [{upTo, increase}, …], the
 * last band without upTo). Used by the admin's test panel with the draft and the published rules.
 *
 *   gap = (average − current) / average, decided in whole fils with integer cross-multiplication
 *   the first band whose upTo % the gap does not exceed gives the maximum increase; at or above the average: band 0
 *   notice check: days(notice received → expiry) ≥ notice_days
 */
final class RentRules
{
    public const DEFAULTS = [
        'notice_days' => 90,
        'bands' => [
            ['upTo' => 10, 'increase' => 0],
            ['upTo' => 20, 'increase' => 5],
            ['upTo' => 30, 'increase' => 10],
            ['upTo' => 40, 'increase' => 15],
            ['upTo' => null, 'increase' => 20],
        ],
    ];

    public const REQUIRED = ['currentAnnualRent', 'indexAverageRent'];

    /** @var array<string, mixed> */
    private array $r;

    /** @param array<string, mixed> $rules */
    public function __construct(array $rules = [])
    {
        $this->r = $rules + self::DEFAULTS;
        $this->r['bands'] = array_values((array) $this->r['bands']);
    }

    /** rent.js bandIndex(). */
    public function bandIndex(float|int $current, float|int $average): int
    {
        $c = Money::toFils($current);
        $a = Money::toFils($average);
        $bands = $this->r['bands'];
        if (! ($a > 0) || $c >= $a) {
            return 0;
        }
        $diff = $a - $c;
        for ($i = 0; $i < count($bands) - 1; $i++) {
            if ($diff * 100 <= $a * $bands[$i]['upTo']) {
                return $i;
            }
        }

        return count($bands) - 1;
    }

    /** rent.js gapBasisPoints(): the gap in 0.01 %, rounded up. */
    public function gapBasisPoints(float|int $current, float|int $average): int
    {
        $c = Money::toFils($current);
        $a = Money::toFils($average);
        if (! ($a > 0) || $c >= $a) {
            return 0;
        }
        $n = ($a - $c) * 10000;
        $q = intdiv($n, $a);

        return $q * $a < $n ? $q + 1 : $q;
    }

    /** rent.js noticeCheck(): {days, ok, after} or null. @return array{days: int, ok: bool, after: bool}|null */
    public function noticeCheck(mixed $noticeReceived, mixed $expiry): ?array
    {
        $days = Money::daysBetween($noticeReceived, $expiry);
        if ($days === null) {
            return null;
        }

        return ['days' => $days, 'ok' => $days >= $this->r['notice_days'], 'after' => $days < 0];
    }

    /** @return array<string, mixed> */
    public function normalise(array $input): array
    {
        $text = fn (mixed $v) => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return [
            'currentAnnualRent' => Money::readNumber($input['currentAnnualRent'] ?? null),
            'indexAverageRent' => Money::readNumber($input['indexAverageRent'] ?? null),
            'expiryDate' => $text($input['expiryDate'] ?? null),
            'noticeReceivedDate' => $text($input['noticeReceivedDate'] ?? null),
        ];
    }

    /** @return array{errors: array<string, string>, missing: list<string>} */
    public function validate(array $values): array
    {
        $errors = [];
        $missing = [];
        foreach (self::REQUIRED as $field) {
            if ($values[$field] === null) {
                $missing[] = $field;
            } else {
                $value = $values[$field];
                $error = match (true) {
                    is_nan((float) $value) => 'not_number',
                    $value < 0 => 'negative',
                    $value == 0 => 'positive',
                    $value > Money::MAX_AMOUNT => 'too_large',
                    default => null,
                };
                if ($error !== null) {
                    $errors[$field] = $error;
                }
            }
        }
        foreach (['expiryDate', 'noticeReceivedDate'] as $field) {
            if ($values[$field] !== null && ! Money::isDate($values[$field])) {
                $errors[$field] = 'date';
            }
        }

        return ['errors' => $errors, 'missing' => $missing];
    }

    /** rent.js calculate(). @return array<string, mixed> */
    public function calculate(array $values): array
    {
        $current = $values['currentAnnualRent'];
        $average = $values['indexAverageRent'];
        $band = $this->bandIndex($current, $average);
        $increase = $this->r['bands'][$band]['increase'];
        $currentFils = Money::toFils($current);
        $increaseFils = (int) Money::jsRound(($currentFils * $increase) / 100);

        return [
            'belowAverage' => Money::toFils($current) < Money::toFils($average),
            'gapBasisPoints' => $this->gapBasisPoints($current, $average),
            'band' => $band,
            'increase' => $increase,
            'increaseAmount' => $increaseFils / 100,
            'newRent' => ($currentFils + $increaseFils) / 100,
            'notice' => $this->noticeCheck($values['noticeReceivedDate'], $values['expiryDate']),
        ];
    }

    /** @return array{status: string, values: array<string, mixed>, errors: array<string, string>, missing: list<string>, result: array<string, mixed>|null} */
    public function estimate(array $input): array
    {
        $values = $this->normalise($input);
        ['errors' => $errors, 'missing' => $missing] = $this->validate($values);
        if ($errors !== []) {
            return ['status' => 'invalid', 'values' => $values, 'errors' => $errors, 'missing' => $missing, 'result' => null];
        }
        if ($missing !== []) {
            return ['status' => 'incomplete', 'values' => $values, 'errors' => [], 'missing' => $missing, 'result' => null];
        }

        return ['status' => 'ok', 'values' => $values, 'errors' => [], 'missing' => [], 'result' => $this->calculate($values)];
    }
}
