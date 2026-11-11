<?php

/*
| Design 2 view helpers (docs/d2/engineering.md). Loaded by App\Providers\DesignServiceProvider.
*/

if (! function_exists('d2p')) {
    /**
     * Design 2 PROPOSED copy (not in the approved DOCX files): lang/{locale}/proposed-d2/{area}.php,
     * read as proposed.d2.{area}.{key} and listed in docs/proposed-copy.md for client approval.
     * Mark the element that shows it with @proposed (data-copy-status="proposed" outside production).
     */
    function d2p(string $key, array $replace = [], ?string $locale = null): string
    {
        return (string) __('proposed.d2.'.$key, $replace, $locale);
    }
}

if (! function_exists('d2_vite')) {
    /** Vite tags for Design 2 (public/build-d2, or the dev server behind public/hot-d2). */
    function d2_vite(array $entries): Illuminate\Support\HtmlString
    {
        return Illuminate\Support\Facades\Vite::useHotFile(public_path((string) config('design.d2.hot_file')))
            ->useBuildDirectory((string) config('design.d2.build_directory'))
            ->__invoke($entries);
    }
}
