<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

/**
 * Dates, file sizes and multi-line text as the panel prints them, in the interface language.
 *
 * Dates: the formats live in lang/{en,ar,ru}/admin.php (`format.*`, PHP date characters read through
 * Carbon's translatedFormat), so each language keeps its own punctuation: "10 November 2026, 08:48",
 * "10 نوفمبر 2026، 08:48" (Arabic comma), "10 ноября 2026, 08:48". Times are Dubai time (EnquiryStats).
 *
 * File sizes: decimal units, exactly like the public form ("up to 10 MB" = 10,000,000 bytes) and its upload
 * chip (resources/js/d2/components/upload-checks.js formatSize): under 1 MB in whole KB (at least 1), from
 * 1 MB in MB with one decimal, the number in the interface language with Western digits.
 */
final class AdminFormat
{
    /** Day, month name and year with the time: the request page, the consent record, the edit sidebar. */
    public static function dateTime(): string
    {
        return (string) __('admin.format.datetime');
    }

    /** Day, month name and year (a deadline). */
    public static function date(): string
    {
        return (string) __('admin.format.date');
    }

    /** Day, short month and year: table cells, with the time on its own line below. */
    public static function shortDate(): string
    {
        return (string) __('admin.format.date_short');
    }

    public static function time(): string
    {
        return (string) __('admin.format.time');
    }

    /**
     * A visitor's own text (summary, deadline details, consent wording) or the internal note with its line
     * breaks kept: escaped, each newline a <br>. (Not `white-space: pre-line`, which would also keep the
     * newlines of Filament's own template around the value.)
     */
    public static function multiline(?string $value): HtmlString
    {
        return new HtmlString(nl2br(e(trim((string) $value)), false));
    }

    public static function fileSize(int $bytes, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $bytes = max(0, $bytes);
        $inMegabytes = $bytes >= 1_000_000;
        $value = $inMegabytes ? round($bytes / 100_000) / 10 : max(1, round($bytes / 1000));
        $number = Number::format($value, maxPrecision: 1, locale: $locale.'@numbers=latn');

        return (string) __($inMegabytes ? 'admin.size.mb' : 'admin.size.kb', ['size' => $number === false ? (string) $value : $number], $locale);
    }
}
