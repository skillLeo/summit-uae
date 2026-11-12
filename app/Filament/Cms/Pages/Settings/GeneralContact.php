<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Settings\ContactSettings;
use BackedEnum;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * General and Contact (docs/briefs/design-2.1-and-admin-cms.md §12.3; App\Settings\ContactSettings): the phone number
 * as shown and as dialled, the e-mail address and its mailto link, and the WhatsApp number every WA01 link opens. Every
 * phone, e-mail and WhatsApp control of the site reads them (App\Content\ContentRepository::setting()). The shown and the
 * linked form must name the same number and address.
 */
class GeneralContact extends SettingsPage
{
    use EditsSettings;

    protected static string $screen = 'settings_general';

    protected static string $settings = ContactSettings::class;

    protected static ?string $slug = 'settings/contact';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.contact.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.contact.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.contact.subheading');
    }

    public function form(Schema $schema): Schema
    {
        $ltr = ['dir' => 'ltr'];

        return $schema->columns(1)->components([
            Section::make((string) __('cms_site.contact.phone'))
                ->description((string) __('cms_site.contact.phone_hint'))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('phone_display')->label((string) __('cms_site.contact.phone_display'))
                            ->helperText((string) __('cms_site.contact.phone_display_hint'))
                            ->required()->maxLength(32)->regex('/^\+[1-9][0-9]{0,3}( [0-9]{1,4}){1,5}$/')
                            ->extraInputAttributes($ltr + ['inputmode' => 'tel']),
                        TextInput::make('phone_href')->label((string) __('cms_site.contact.phone_href'))
                            ->helperText((string) __('cms_site.contact.phone_href_hint'))
                            ->required()->maxLength(32)->regex('/^tel:\+[1-9][0-9]{6,14}$/')
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $digits = preg_replace('/\D/', '', (string) $get('phone_display'));
                                if (is_string($value) && $digits !== '' && $value !== 'tel:+'.$digits) {
                                    $fail((string) __('cms_site.contact.errors.phone_mismatch', ['href' => 'tel:+'.$digits]));
                                }
                            })
                            ->extraInputAttributes($ltr),
                    ]),
                ]),
            Section::make((string) __('cms_site.contact.email'))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema([
                        TextInput::make('email')->label((string) __('cms_site.contact.email_display'))
                            ->required()->email()->maxLength(120)
                            ->extraInputAttributes($ltr + ['inputmode' => 'email']),
                        TextInput::make('email_href')->label((string) __('cms_site.contact.email_href'))
                            ->helperText((string) __('cms_site.contact.email_href_hint'))
                            ->required()->maxLength(130)->regex('/^mailto:[^\s@]+@[^\s@]+$/')
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $email = trim((string) $get('email'));
                                if (is_string($value) && $email !== '' && $value !== 'mailto:'.$email) {
                                    $fail((string) __('cms_site.contact.errors.email_mismatch', ['href' => 'mailto:'.$email]));
                                }
                            })
                            ->extraInputAttributes($ltr),
                    ]),
                ]),
            Section::make((string) __('cms_site.contact.whatsapp'))
                ->schema([
                    TextInput::make('whatsapp_number')->label((string) __('cms_site.contact.whatsapp_number'))
                        ->helperText((string) __('cms_site.contact.whatsapp_number_hint'))
                        ->required()->regex('/^[1-9][0-9]{7,14}$/')
                        ->extraInputAttributes($ltr + ['inputmode' => 'numeric']),
                ]),
        ]);
    }

    protected function formToSettings(array $data): array
    {
        foreach (['phone_display', 'phone_href', 'email', 'email_href', 'whatsapp_number'] as $field) {
            $data[$field] = trim((string) ($data[$field] ?? ''));
        }

        return $data;
    }
}
