<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Tree;
use App\Filament\Cms\Forms\CmsForm;
use App\Filament\Cms\Forms\Translatable;
use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Filament\Cms\Support\CmsLabels;
use App\Settings\ContactSettings;
use App\Settings\WhatsAppSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * WhatsApp (docs/briefs/design-2.1-and-admin-cms.md §12.3; App\Settings\WhatsAppSettings): the WA01 draft per language.
 * It is THE one draft every WhatsApp control of the site opens (a DOCX rule; never sent automatically), so the screen
 * shows the final link per language exactly as App\Content\ContentRepository::whatsappUrl() builds it:
 * https://wa.me/<the WhatsApp number of General and Contact>?text=<the draft, URL-encoded>.
 */
class WhatsApp extends SettingsPage
{
    use EditsSettings;

    protected static string $settings = WhatsAppSettings::class;

    protected static ?string $slug = 'settings/whatsapp';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.whatsapp.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.whatsapp.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.whatsapp.subheading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            CmsForm::localeSwitcher(),
            Section::make((string) __('cms_site.whatsapp.draft'))
                ->description((string) __('cms_site.whatsapp.draft_hint'))
                ->schema([
                    Translatable::make('draft', fn (string $locale) => Textarea::make($locale)->rows(4)->autosize()->required()->live(onBlur: true),
                        (string) __('cms_site.whatsapp.draft_label'), self::limit()),
                ]),
            Section::make((string) __('cms_site.whatsapp.preview'))
                ->description((string) __('cms_site.whatsapp.preview_hint'))
                ->schema(array_map(fn (string $locale) => Text::make(fn (Get $get) => self::previewLine($locale, $get('draft.'.$locale)))
                    ->extraAttributes(['class' => 'cms-wa-preview']), Tree::LOCALES)),
        ]);
    }

    /** The WA01 link of a language as the site builds it. */
    public static function link(?string $draft): ?string
    {
        $number = (string) app(ContactSettings::class)->whatsapp_number;
        if (! is_string($draft) || $draft === '' || $number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($draft);
    }

    private static function previewLine(string $locale, mixed $draft): HtmlString
    {
        $link = self::link(is_string($draft) ? $draft : null);
        $label = '<span class="cms-wa-preview__lang">'.e(CmsLabels::locale($locale)).'</span>';
        $value = $link === null
            ? '<span class="cms-wa-preview__missing">'.e((string) __('cms.locale.missing')).'</span>'
            : '<a class="cms-wa-preview__link" href="'.e($link).'" target="_blank" rel="noopener" dir="ltr">'.e($link).'</a>';

        return new HtmlString($label.$value);
    }

    /** 115 % of the longest approved WA01 draft (the limits rule of brief §8.4), at least 600 characters. */
    private static function limit(): int
    {
        $longest = 0;
        foreach ((array) (app(WhatsAppSettings::class)->draft ?? []) as $text) {
            $longest = max($longest, mb_strlen((string) $text));
        }
        $pages = Limits::for('pages')['whatsapp_draft'] ?? 0;

        return max(600, (int) ceil(max($longest, (int) $pages) * 1.15));
    }

    protected function settingsToForm(array $data): array
    {
        $data['draft'] = array_merge(array_fill_keys(Tree::LOCALES, null), (array) ($data['draft'] ?? []));

        return $data;
    }

    /** The drafts in en, ar, ru order (the stored order), trimmed of surrounding blank lines only. */
    protected function formToSettings(array $data): array
    {
        $draft = [];
        foreach (Tree::LOCALES as $locale) {
            $text = $data['draft'][$locale] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $draft[$locale] = str_replace("\r\n", "\n", trim($text));
            }
        }
        $data['draft'] = $draft;

        return $data;
    }
}
