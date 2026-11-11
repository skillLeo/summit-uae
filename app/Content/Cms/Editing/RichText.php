<?php

namespace App\Content\Cms\Editing;

use Stevebauman\Purify\Facades\Purify;

/**
 * Rich text of the CMS (docs/briefs/design-2.1-and-admin-cms.md §8.10, §12.2): sanitised with HTMLPurifier
 * (stevebauman/purify, config/purify.php) on save and again on render. Allowed: p, h2, h3, ul, ol, li,
 * a[href|target|rel], strong, em, br, blockquote. A link that opens a new tab always gets rel="noopener noreferrer".
 */
final class RichText
{
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        $clean = (string) Purify::config('default')->clean($html);

        // target="_blank" without rel would let the opened page reach window.opener
        return (string) preg_replace_callback('#<a\s([^>]*)>#i', function (array $m): string {
            $attributes = $m[1];
            if (stripos($attributes, 'target="_blank"') === false) {
                return '<a '.$attributes.'>';
            }
            $attributes = preg_replace('#\s*rel="[^"]*"#i', '', $attributes) ?? $attributes;

            return '<a '.trim($attributes).' rel="noopener noreferrer">';
        }, $clean);
    }

    /** Clean every HTML leaf of a value ({en, ar, ru}, or a tree). */
    public static function cleanAll(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::clean($value);
        }
        if (is_array($value)) {
            return array_map(self::cleanAll(...), $value);
        }

        return $value;
    }
}
