<?php

namespace App\Content\Cms\Calculators;

/**
 * The money and date arithmetic of the public estimators, in PHP (resources/js/d2/tools/money.js and dates.js), for the
 * admin's test panel: amounts in AED rounded to fils half-up on the decimal value shown (1.005 → 1.01), legal bands
 * decided in whole fils, ISO dates counted as whole UTC days between 1900 and 2100.
 */
final class Money
{
    /** money.js MAX_AMOUNT: the largest amount the tools accept (AED). */
    public const MAX_AMOUNT = 1e9;

    public const MIN_YEAR = 1900;

    public const MAX_YEAR = 2100;

    /** money.js round2(): half-up to 2 decimals on the shortest decimal form of the number; -0 becomes 0. */
    public static function round2(float|int $value): float
    {
        $value = (float) $value;
        if (! is_finite($value) || $value == 0.0) {
            return 0.0;
        }
        $abs = abs($value);
        $text = self::jsString($abs);
        $rounded = str_contains($text, 'e')
            ? self::jsRound($abs * 100) / 100
            : self::jsRound((float) ($text.'e2')) / 100;

        return $rounded == 0.0 ? 0.0 : ($value < 0 ? -$rounded : $rounded);
    }

    /** money.js toFils(): whole fils of an amount in AED. */
    public static function toFils(float|int $aed): int
    {
        return (int) self::jsRound((float) (self::jsString(self::round2($aed)).'e2'));
    }

    /** money.js sum(): the sum of the rounded parts, rounded to fils. */
    public static function sum(float|int ...$values): float
    {
        $fils = 0;
        foreach ($values as $value) {
            $fils += self::toFils($value);
        }

        return self::round2($fils / 100);
    }

    /**
     * money.js readNumber(): null for an empty field, NAN for text that is not a number.
     */
    public static function readNumber(mixed $value): float|int|null
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? $value : NAN;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 ? $text + 0 : NAN;
    }

    /** dates.js dayNumber(): days since 1970-01-01 of a real date (YYYY-MM-DD), or null. */
    public static function dayNumber(mixed $iso): ?int
    {
        if (! is_string($iso) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($iso), $m) !== 1) {
            return null;
        }
        [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR || $month < 1 || $month > 12 || $day < 1 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return intdiv(gmmktime(0, 0, 0, $month, $day, $year), 86400);
    }

    public static function isDate(mixed $iso): bool
    {
        return self::dayNumber($iso) !== null;
    }

    /** dates.js daysInclusive(): both the first and the last day count. */
    public static function daysInclusive(mixed $start, mixed $end): ?int
    {
        $a = self::dayNumber($start);
        $b = self::dayNumber($end);

        return $a === null || $b === null ? null : $b - $a + 1;
    }

    /** dates.js daysBetween(). */
    public static function daysBetween(mixed $from, mixed $to): ?int
    {
        $a = self::dayNumber($from);
        $b = self::dayNumber($to);

        return $a === null || $b === null ? null : $b - $a;
    }

    /** JavaScript's Math.round(): the nearest integer, halves towards +∞ (x − floor(x) is exact for these doubles). */
    public static function jsRound(float $value): float
    {
        $floor = floor($value);

        return $value - $floor >= 0.5 ? $floor + 1 : $floor;
    }

    /** JavaScript's String(number) for a finite positive number: the shortest decimal form that reads back the same. */
    public static function jsString(float $value): string
    {
        if ($value == floor($value) && abs($value) < 1e21) {
            return number_format($value, 0, '.', '');
        }
        $text = (string) json_encode($value);
        if (str_contains($text, 'e') || str_contains($text, 'E')) {
            return strtolower($text); // only far outside the amounts the tools accept: both paths round the same
        }

        return $text;
    }
}
