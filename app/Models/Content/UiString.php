<?php

namespace App\Models\Content;

use App\Content\ContentRepository;
use App\Models\Concerns\HasCmsFields;
use Spatie\TranslationLoader\LanguageLine;

/**
 * Interface text (docs/briefs/design-2.1-and-admin-cms.md §8.2 "Interface Text", §8.11 ui_strings): group, key, text
 * {en, ar, ru}, copy_source, note.
 *
 *   group "ui"        the approved interface strings of the DOCX files, read by ui()
 *   group "proposed"  lang/{locale}/proposed.php with proposed-d2/*.php: proposed() (Design 1) and d2p() (Design 2)
 *   group "switcher"  lang/{locale}/switcher.php (the design switch)
 *
 * The model of spatie/laravel-translation-loader: every translation group reads these rows first and the lang files
 * fill the rest. Two differences from the package's LanguageLine: the rows come from App\Content\ContentRepository's
 * cached snapshot of PUBLISHED rows, and a text missing in one language never falls back to another (Laravel's own
 * fallback locale still applies, exactly as with the files alone).
 */
final class UiString extends LanguageLine
{
    use HasCmsFields;

    protected $table = 'ui_strings';

    public static function getTranslationsForGroup(string $locale, string $group): array
    {
        return app(ContentRepository::class)->lines($group, $locale);
    }

    public function getTranslation(string $locale): ?string
    {
        $text = $this->text[$locale] ?? null;

        return is_string($text) ? $text : null;
    }
}
