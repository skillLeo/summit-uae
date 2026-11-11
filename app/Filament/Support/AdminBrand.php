<?php

namespace App\Filament\Support;

use App\Design\D2\FontFaces;
use App\Design\D2\Fonts;
use App\Models\User;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Markup the admin panel adds around Filament's own views (no Blade files: everything is built here
 * and escaped): the brand lockup, the Design 2 fonts and the sign-in page language links.
 */
final class AdminBrand
{
    /** Brand logo tile: package/brand/logo-03-gold-on-black.png, cropped to the emblem (public/images/admin). */
    public const TILE = '/images/admin/logo-tile.png';

    public const FAVICON = '/images/admin/favicon.png';

    /** Approved brand name ("Summit Legal Consultancy" / «سَمِت للاستشارات القانونية»), in the admin language. */
    public static function name(): string
    {
        try {
            $repository = content();
            if ($repository->hasUi('brand.name')) {
                return $repository->ui('brand.name');
            }
        } catch (Throwable) {
            // content store unavailable: fall through
        }

        return (string) config('app.name');
    }

    /** Sidebar / sign-in lockup: the gold-on-black tile and the brand name, with "Admin" under it. */
    public static function logo(): HtmlString
    {
        return new HtmlString(
            '<span class="slc-brand">'
            .'<img class="slc-brand__tile" src="'.e(self::TILE).'" alt="" width="40" height="40" decoding="async">'
            .'<span class="slc-brand__text">'
            .'<span class="slc-brand__name">'.e(self::name()).'</span>'
            .'<span class="slc-brand__panel">'.e(__('admin.brand.panel')).'</span>'
            .'</span></span>'
        );
    }

    /**
     * The Design 2 @font-face rules (resources/css/d2/base/fonts.css via App\Design\D2\FontFaces) inlined
     * with the CSP nonce, and the preloads of the active language: self-hosted, no Inter, no font CDN.
     */
    public static function fonts(): HtmlString
    {
        $html = '';
        foreach (Fonts::preload(app()->getLocale()) as $url) {
            $html .= '<link rel="preload" href="'.e($url).'" as="font" type="font/woff2" crossorigin>';
        }
        $css = FontFaces::css();
        if ($css !== '') {
            $nonce = Vite::cspNonce();
            $html .= '<style'.($nonce ? ' nonce="'.e($nonce).'"' : '').' data-admin-fonts>'.$css.'</style>';
        }

        return new HtmlString($html);
    }

    /** Language links under the sign-in form (the signed-in choice lives in the user menu and profile). */
    public static function loginLanguages(): HtmlString
    {
        $current = app()->getLocale();
        $links = '';
        foreach (User::ADMIN_LOCALES as $locale) {
            $name = e(AdminLocale::nativeName($locale));
            $attributes = ' lang="'.$locale.'" dir="'.(AdminLocale::isRtl($locale) ? 'rtl' : 'ltr').'"';
            $links .= $locale === $current
                ? '<span class="slc-langs__item" aria-current="true"'.$attributes.'>'.$name.'</span>'
                : '<a class="slc-langs__item" href="?'.AdminLocale::QUERY.'='.$locale.'" hreflang="'.$locale.'"'.$attributes.'>'.$name.'</a>';
        }

        return new HtmlString(
            '<nav class="slc-langs" aria-label="'.e(__('admin.login.languages')).'">'.$links.'</nav>'
        );
    }
}
