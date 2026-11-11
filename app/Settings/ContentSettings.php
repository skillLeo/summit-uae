<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * "Show pending copy on the public site" (brief §8.7): true, false, or null = on everywhere except production (the
 * default). The environment flag SUMMIT_SHOW_PENDING, when set, decides for that environment.
 */
class ContentSettings extends Settings
{
    public ?bool $show_pending;

    public static function group(): string
    {
        return 'content';
    }
}
