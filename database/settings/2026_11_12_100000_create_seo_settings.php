<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The SEO global defaults (App\Settings\SeoSettings) with today's values: no title suffix, no default description and
 * no default social image exist today (null), and every page may be indexed unless SUMMIT_NOINDEX says otherwise
 * (robots_index true). With these values the public pages are byte-identical to before.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.title_suffix', null);
        $this->migrator->add('seo.default_description', null);
        $this->migrator->add('seo.default_og_image', null);
        $this->migrator->add('seo.robots_index', true);
    }

    public function down(): void
    {
        foreach (['title_suffix', 'default_description', 'default_og_image', 'robots_index'] as $name) {
            $this->migrator->deleteIfExists('seo.'.$name);
        }
    }
};
