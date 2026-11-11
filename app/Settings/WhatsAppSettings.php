<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * WhatsApp (brief §12.3): the WA01 draft per language, the one draft every WhatsApp control opens (never sent). The
 * link is https://wa.me/{contact.whatsapp_number}?text={the draft, URL-encoded}.
 */
class WhatsAppSettings extends Settings
{
    /** @var array<string, string> en, ar, ru */
    public array $draft;

    public static function group(): string
    {
        return 'whatsapp';
    }
}
