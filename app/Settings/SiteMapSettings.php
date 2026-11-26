<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * The client's site map (00_Site_Map_*.docx): the label of every homepage link key (H-L001 …) per language, kept
 * read-only for traceability (brief §8.2 Navigation). The Arabic site map has no link registry.
 */
class SiteMapSettings extends Settings
{
    /**
     * locale => link key => label. No @var type here: spatie/laravel-settings builds a cast from a docblock type and
     * fails on this nested array (a settings migration's $this->migrator->update() loads every settings class).
     */
    public array $link_registry;

    public static function group(): string
    {
        return 'site_map';
    }
}
