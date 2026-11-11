<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Languages (brief §12.3, §3.7): the digits of Arabic pages, "western" (0-9) or "eastern" (٠-٩); a client question. */
class LanguageSettings extends Settings
{
    public string $arabic_digits;

    public static function group(): string
    {
        return 'languages';
    }
}
