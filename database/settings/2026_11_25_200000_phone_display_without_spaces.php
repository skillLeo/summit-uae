<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Client PDF item 5 and owner decision 4 (docs/briefs/client-final-changes-2026-11-25.md): the phone number is shown
 * exactly as +971524666191 everywhere, without spaces, dashes or brackets; the tel: link stays tel:+971524666191.
 * db:seed keeps stored settings, so the server's stored values change here: the displayed number (General and Contact)
 * and the site-map link labels that show it. The defaults come from the client layer (database/content/client).
 */
return new class extends SettingsMigration
{
    private const OLD = '+971 52 466 6191';

    private const NEW = '+971524666191';

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    private function swap(string $from, string $to): void
    {
        if ($this->migrator->exists('contact.phone_display')) {
            $this->migrator->update('contact.phone_display', fn (?string $value) => $value === $from ? $to : $value);
        }
        if ($this->migrator->exists('site_map.link_registry')) {
            // the stored registry arrives decoded as objects: swap the text in its JSON and decode it the same way
            $this->migrator->update('site_map.link_registry', fn (mixed $registry) => json_decode(str_replace(json_encode($from), json_encode($to), (string) json_encode($registry, JSON_UNESCAPED_UNICODE)), is_array($registry)));
        }
        \App\Content\Cms\ContentCache::bump(); // the published-content cache holds the settings too
    }
};
