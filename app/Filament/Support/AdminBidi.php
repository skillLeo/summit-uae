<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

/**
 * Isolated runs inside the (possibly right-to-left) admin. Every short value keeps its own direction inside
 * an inline <bdi>, so the cell around it keeps the interface direction and the value starts where its label
 * starts (on the right in Arabic, on the left in English and Russian), whatever script it is written in.
 * Longer free text (a summary, a note) is a block with its own direction instead: `.slc-prose` in theme.css.
 */
final class AdminBidi
{
    /** Always left to right: phone numbers, e-mail addresses, references, codes. */
    public static function ltr(?string $value): HtmlString
    {
        return new HtmlString(filled($value) ? '<bdi dir="ltr">'.e($value).'</bdi>' : '');
    }

    /** A visitor's own short text (a name) in any script: the direction of its first strong character. */
    public static function auto(?string $value): HtmlString
    {
        return new HtmlString(filled($value) ? '<bdi dir="auto">'.e($value).'</bdi>' : '');
    }
}
