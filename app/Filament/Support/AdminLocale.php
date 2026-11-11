<?php

namespace App\Filament\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The admin interface language: the signed-in admin's saved choice (users.admin_locale), else the
 * choice made on the sign-in page (session), else the browser's preferred language, else English.
 * Arabic switches the whole panel to RTL (Filament reads the direction from its own translations).
 */
final class AdminLocale
{
    public const SESSION_KEY = 'admin.locale';

    /** Query parameter of the language links on the sign-in page. */
    public const QUERY = 'lang';

    public static function resolve(Request $request): string
    {
        $user = $request->user();
        if ($user instanceof User && self::valid($user->admin_locale)) {
            return $user->admin_locale;
        }

        $session = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        if (self::valid($session)) {
            return $session;
        }

        return $request->getPreferredLanguage(User::ADMIN_LOCALES) ?? 'en';
    }

    /** Untrusted input → a supported locale or null (plain strings only; arrays are ignored). */
    public static function valid(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, User::ADMIN_LOCALES, true);
    }

    public static function isRtl(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === 'ar';
    }

    /** The language's own name (English / العربية / Русский), the same in every interface language. */
    public static function nativeName(string $locale): string
    {
        return (string) __("admin.locale.{$locale}", [], $locale);
    }
}
