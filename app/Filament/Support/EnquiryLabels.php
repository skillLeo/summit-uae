<?php

namespace App\Filament\Support;

use App\Models\User;
use Throwable;

/**
 * Display names for enquiry codes, in the admin's interface language. Service titles, language names
 * and the "other" / "unsure" matter options are approved copy from the DOCX import (content()), never
 * re-worded here; only "Not chosen" is an admin label (lang/{locale}/admin.php).
 */
final class EnquiryLabels
{
    /** Matter types the public form accepts besides S01–S11 (StoreEnquiryRequest). */
    public const EXTRA_MATTERS = ['other', 'unsure'];

    /** @return list<string> S01 … S11 in the approved order */
    public static function serviceCodes(): array
    {
        $codes = array_keys((array) config('summit.services'));

        return $codes !== [] ? $codes : array_map(fn (int $n) => sprintf('S%02d', $n), range(1, 11));
    }

    /** Approved service title ("Debt Recovery and Cheque Cases"), else the code. */
    public static function serviceTitle(string $code, ?string $locale = null): string
    {
        try {
            $title = content()->service($code, $locale ?? app()->getLocale())['title'] ?? null;
        } catch (Throwable) {
            $title = null;
        }

        return filled($title) ? (string) $title : $code;
    }

    /** S01–S11 → approved service title; other / unsure → the approved form option; null → "Not chosen". */
    public static function matter(?string $code, ?string $locale = null): string
    {
        if (blank($code)) {
            return (string) __('admin.value.not_chosen');
        }
        if (in_array($code, self::EXTRA_MATTERS, true)) {
            return self::ui("form.matter_type.option.{$code}", $locale) ?? $code;
        }

        return self::serviceTitle($code, $locale);
    }

    /** @return array<string, string> code => label, for filters: S01 … S11, other, unsure */
    public static function matterOptions(): array
    {
        $options = [];
        foreach ([...self::serviceCodes(), ...self::EXTRA_MATTERS] as $code) {
            $options[$code] = self::matter($code);
        }

        return $options;
    }

    /** en / ar / ru → the approved language option ("English", "الإنجليزية"); null → "Not chosen". */
    public static function language(?string $code, ?string $locale = null): string
    {
        if (blank($code)) {
            return (string) __('admin.value.not_chosen');
        }

        return self::ui("form.preferred_language.option.{$code}", $locale) ?? strtoupper($code);
    }

    /** @return array<string, string> */
    public static function languageOptions(): array
    {
        $options = [];
        foreach (User::ADMIN_LOCALES as $code) {
            $options[$code] = self::language($code);
        }

        return $options;
    }

    private static function ui(string $key, ?string $locale): ?string
    {
        try {
            $repository = content();
            $locale ??= app()->getLocale();

            return $repository->hasUi($key, $locale) ? $repository->ui($key, $locale) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
