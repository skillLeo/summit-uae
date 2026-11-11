<?php

use App\Content\ContentRepository;
use App\Support\SiteUrl;

if (! function_exists('content')) {
    function content(): ContentRepository
    {
        return app(ContentRepository::class);
    }
}

if (! function_exists('ui')) {
    /** Approved interface string imported from the DOCX files. */
    function ui(string $key, ?string $locale = null): string
    {
        return app(ContentRepository::class)->ui($key, $locale);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, ?string $locale = null): mixed
    {
        return app(ContentRepository::class)->setting($key, $locale);
    }
}

if (! function_exists('proposed')) {
    /**
     * New copy that is NOT in the approved DOCX files. Lives in lang/{locale}/proposed.php and is
     * listed in docs/proposed-copy.md for client approval.
     */
    function proposed(string $key, array $replace = [], ?string $locale = null): string
    {
        return __('proposed.'.$key, $replace, $locale);
    }
}

if (! function_exists('target_url')) {
    /** URL for a site-map target ID (P05, S04, G04, case-01, A01 …). */
    function target_url(string $target, ?string $locale = null): string
    {
        return SiteUrl::target($target, $locale);
    }
}

if (! function_exists('locale_dir')) {
    function locale_dir(?string $locale = null): string
    {
        return config('summit.locales.'.($locale ?? app()->getLocale()).'.dir', 'ltr');
    }
}
