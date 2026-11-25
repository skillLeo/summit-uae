<?php

namespace App\Design\D2;

use App\Support\SiteUrl;

/** Shared data for the Design 2 shell (header pill, menu sheet, footer). */
final class Shell
{
    /**
     * A navigation menu of the CMS (content()->menu(): primary, actions, tabbar, footer_resources, footer_useful,
     * footer_contact …) as the shell renders it: key (the navCurrent key of options.nav), label, href, the sheet it opens
     * on phones (options.sheet), whether it opens the mega menu (options.mega), new_tab, link_key, the channel
     * (options.channel: TEL, WA01, EMAIL) and, for a record target, the record.
     *
     * @return list<array{key: string, label: string, href: string, sheet: string|null, mega: bool, new_tab: bool, link_key: string|null, channel: string|null, record: array<string, mixed>|null}>
     */
    public static function nav(string $menu): array
    {
        return array_map(fn (array $item) => [
            'key' => (string) ($item['options']['nav'] ?? $item['link_key'] ?? ''),
            'label' => (string) $item['label'],
            'href' => $item['href'],
            'sheet' => isset($item['options']['sheet']) ? (string) $item['options']['sheet'] : null,
            'mega' => (bool) ($item['options']['mega'] ?? false),
            'new_tab' => $item['new_tab'],
            'link_key' => $item['link_key'],
            'channel' => isset($item['options']['channel']) ? (string) $item['options']['channel'] : null,
            'record' => $item['record'],
        ], content()->menu($menu));
    }

    /**
     * The practice-area records a menu lists (mega: the mega menu and the services sheet; footer_practice), in the
     * menu's order.
     *
     * @return list<array<string, mixed>>
     */
    public static function practiceAreas(string $menu): array
    {
        $out = [];
        foreach (content()->menu($menu) as $item) {
            if ($item['record'] !== null && preg_match('/^S\d{2}$/', (string) $item['target']) === 1) {
                $out[] = $item['record'];
            }
        }

        return $out;
    }

    /**
     * The same page in each language (query kept; the #hash is added in the browser by
     * resources/js/d2/modules/lang-switch.js, which also writes the summit_lang cookie like Design 1).
     * On error pages every language links to its home page (Design 1 review C1/C7: never echo a
     * missing URL or its query string).
     *
     * @return list<array{code: string, href: string, label: string, current: bool, dir: string}>
     */
    public static function languageLinks(?string $pageKey = null): array
    {
        $request = request();
        $current = app()->getLocale();
        $isError = $pageKey === 'error' || app()->bound('summit.error_page');
        $path = $request->getPathInfo();
        $query = $request->getQueryString();

        $out = [];
        foreach (config('summit.locales') as $code => $meta) {
            $href = $isError
                ? SiteUrl::home($code)
                : SiteUrl::abs(SiteUrl::swapLocale($path, $code)).($query ? '?'.$query : '');
            $out[] = [
                'code' => $code,
                'href' => $href,
                'label' => ui('lang.'.$code, $code),
                'current' => $code === $current,
                'dir' => $meta['dir'],
            ];
        }

        return $out;
    }
}
