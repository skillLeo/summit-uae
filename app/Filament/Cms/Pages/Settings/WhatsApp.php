<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Content\Cms\Editing\Limits;
use App\Content\Cms\Tree;
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
 * WhatsApp (docs/briefs/design-2.1-and-admin-cms.md §12.3; App\Settings\WhatsAppSettings): the one WhatsApp message.
 * Client PDF item 6: one standard message, in English, on every page in every language (no Arabic or Russian
 * version), for every WhatsApp control of the site (never sent automatically). The screen edits that one message and
 * stores it for en, ar and ru alike (the settings keep their per-language shape), and shows the final link exactly as
 * App\Content\ContentRepository::whatsappUrl() builds it: https://wa.me/<the WhatsApp number of General and
 * Contact>?text=<the message, URL-encoded>.
 */
class WhatsApp extends SettingsPage
{
    use EditsSettings;

    protected static string $screen = 'settings_whatsapp';

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
            Section::make((string) __('cms_site.whatsapp.draft'))
                ->description((string) __('cms_site.whatsapp.draft_hint'))
                ->schema([
                    // one message, written in English for every language (lang="en": the e2e flow finds it so too)
                    Textarea::make('message')->label((string) __('cms_site.whatsapp.draft_label'))->rows(4)->autosize()->required()
                        ->maxLength(self::limit())->live(onBlur: true)->extraInputAttributes(['lang' => 'en', 'dir' => 'ltr']),
                ]),
            Section::make((string) __('cms_site.whatsapp.preview'))
                ->description((string) __('cms_site.whatsapp.preview_hint'))
                ->schema([Text::make(fn (Get $get) => self::previewLine($get('message')))->extraAttributes(['class' => 'cms-wa-preview'])]),
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

    private static function previewLine(mixed $draft): HtmlString
    {
        $link = self::link(is_string($draft) ? $draft : null);
        $label = '<span class="cms-wa-preview__lang">'.e(implode(' · ', array_map(fn (string $l) => CmsLabels::locale($l), Tree::LOCALES))).'</span>';
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

    protected function fieldLabels(): array
    {
        return ['draft' => (string) __('cms_site.whatsapp.draft')];
    }

    /** The one message: the English draft (the others are the same since client PDF item 6). */
    protected function settingsToForm(array $data): array
    {
        $draft = (array) ($data['draft'] ?? []);
        $data['message'] = $draft['en'] ?? (reset($draft) ?: null);
        unset($data['draft']);

        return $data;
    }

    /** The message for en, ar and ru alike (the stored order), trimmed of surrounding blank lines only. */
    protected function formToSettings(array $data): array
    {
        $text = is_string($data['message'] ?? null) ? str_replace("\r\n", "\n", trim($data['message'])) : '';
        $data['draft'] = $text === '' ? [] : array_fill_keys(Tree::LOCALES, $text);
        unset($data['message']);

        return $data;
    }
}
