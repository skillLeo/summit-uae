<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Client PDF item 6 (docs/briefs/client-final-changes-2026-11-25.md): one standard pre-filled WhatsApp message, always in
 * English, on every page in every language. db:seed keeps stored settings, so the server's stored message changes here
 * (the same text for en, ar and ru; App\Content\ContentRepository reads the English one for every language). down()
 * cannot know an earlier per-language text and leaves the message as it is.
 */
return new class extends SettingsMigration
{
    private const MESSAGE = 'Hello Summit Legal Consultancy, I would like to request a legal consultation. Please contact me.';

    public function up(): void
    {
        if ($this->migrator->exists('whatsapp.draft')) {
            $this->migrator->update('whatsapp.draft', fn () => ['en' => self::MESSAGE, 'ar' => self::MESSAGE, 'ru' => self::MESSAGE]);
        }
        \App\Content\Cms\ContentCache::bump(); // the published-content cache holds the settings too
    }

    public function down(): void {}
};
