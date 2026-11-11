<?php

namespace App\Design\D2;

use Illuminate\Support\HtmlString;

/**
 * Bidi isolation for approved copy (docs/d2/engineering.md §0 rule 4; Design 1 review C4 / RTL-02).
 *
 * On Arabic pages every Latin run inside the text (USDT, TxID, VARA, DIFC, ADGM, PDF, product and brand
 * names) is wrapped in <bdi dir="ltr">, so it keeps its own direction without turning the sentence
 * around. Markup only: the approved text is printed unchanged (escaped). Other locales get the escaped
 * text. Returns an HtmlString, so print it with {{ Bidi::isolate($text) }}.
 *
 * Phone numbers, e-mail addresses and URLs are not run through this: give them their own
 * <bdi dir="ltr"> element.
 */
final class Bidi
{
    /** A Latin word (letters first, then letters, digits, . / -), optionally followed by more Latin words. */
    private const LATIN_RUN = '/([A-Za-z][A-Za-z0-9.\/-]*(?:[ \t]+[A-Za-z0-9][A-Za-z0-9.\/-]*)*)/u';

    public static function isolate(?string $text, ?string $locale = null): HtmlString
    {
        $text ??= '';
        if ($text === '' || ($locale ?? app()->getLocale()) !== 'ar') {
            return new HtmlString(e($text));
        }

        $parts = preg_split(self::LATIN_RUN, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $html = '';
        foreach ($parts as $i => $part) {
            $html .= $i % 2 === 1 ? '<bdi dir="ltr">'.e($part).'</bdi>' : e($part);
        }

        return new HtmlString($html);
    }
}
