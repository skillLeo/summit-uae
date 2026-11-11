<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * General and Contact (docs/briefs/design-2.1-and-admin-cms.md §12.3): the approved phone and e-mail of the DOCX
 * import, in display and link form, and the WhatsApp number every WA01 link uses. Phase 7 builds the screen.
 */
class ContactSettings extends Settings
{
    public string $phone_display;

    public string $phone_href;

    public string $email;

    public string $email_href;

    public string $whatsapp_number;

    public static function group(): string
    {
        return 'contact';
    }
}
