<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Filament\Cms\Support\CmsLabels;
use App\Settings\LanguageSettings;
use App\Support\Languages as SiteLanguages;
use BackedEnum;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Languages (docs/briefs/design-2.1-and-admin-cms.md §12.3, §3.7; App\Settings\LanguageSettings, applied by
 * App\Support\Languages): the site languages that are on (English always), the default language, the
 * language-suggestion bar and the digits of Arabic pages. The defaults are today's site: every page unchanged.
 */
class Languages extends SettingsPage
{
    use EditsSettings;

    protected static string $screen = 'settings_languages';

    protected static string $settings = LanguageSettings::class;

    protected static ?string $slug = 'settings/languages';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?int $navigationSort = 40;

    /** The form value of "as the server setting" (stored as null). */
    private const ENVIRONMENT = 'environment';

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.settings_languages.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.settings_languages.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.settings_languages.subheading');
    }

    public function form(Schema $schema): Schema
    {
        $locales = [];
        foreach (SiteLanguages::ALL as $locale) {
            $locales[$locale] = CmsLabels::locale($locale);
        }
        $env = SiteLanguages::suggestionFromEnvironment();
        $digitsEnv = config('design.d2.arabic_digits');

        return $schema->columns(1)->components([
            Section::make((string) __('cms_site.settings_languages.site'))
                ->description((string) __('cms_site.settings_languages.site_hint'))
                ->schema([
                    CheckboxList::make('enabled_locales')->label((string) __('cms_site.settings_languages.enabled'))
                        ->helperText((string) __('cms_site.settings_languages.enabled_hint'))
                        ->options($locales)
                        ->disableOptionWhen(fn (string $value): bool => $value === SiteLanguages::ALWAYS_ON)
                        ->columns(['default' => 1, 'sm' => 3])
                        ->live()
                        ->in(SiteLanguages::ALL),
                    Select::make('default_locale')->label((string) __('cms_site.settings_languages.default'))
                        ->helperText((string) __('cms_site.settings_languages.default_hint'))
                        ->options(fn (Get $get): array => array_intersect_key($locales, array_flip(self::enabledFrom($get('enabled_locales')))))
                        ->required()
                        ->selectablePlaceholder(false)
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (! in_array($value, self::enabledFrom($get('enabled_locales')), true)) {
                                $fail((string) __('cms_site.settings_languages.errors.default_off'));
                            }
                        }),
                ]),
            Section::make((string) __('cms_site.settings_languages.suggestion'))
                ->description((string) __('cms_site.settings_languages.suggestion_hint'))
                ->schema([
                    Radio::make('language_suggestion')->hiddenLabel()
                        ->options([
                            self::ENVIRONMENT => (string) __('cms_site.settings_languages.suggestion_env', [
                                'state' => (string) __($env ? 'cms_site.settings.state_on' : 'cms_site.settings.state_off'),
                            ]),
                            'on' => (string) __('cms_site.settings_languages.suggestion_on'),
                            'off' => (string) __('cms_site.settings_languages.suggestion_off'),
                        ])
                        ->required(),
                ]),
            Section::make((string) __('cms_site.settings_languages.digits'))
                ->description((string) __('cms_site.settings_languages.digits_hint'))
                ->schema([
                    Radio::make('arabic_digits')->hiddenLabel()
                        ->options([
                            'western' => (string) __('cms_site.settings_languages.digits_western'),
                            'eastern' => (string) __('cms_site.settings_languages.digits_eastern'),
                        ])
                        ->required(),
                    Text::make((string) __('cms_site.settings_languages.digits_env', ['value' => (string) $digitsEnv]))
                        ->visible(filled($digitsEnv))
                        ->extraAttributes(['class' => 'cms-settings-note cms-settings-note--warning']),
                ]),
        ]);
    }

    /** @return list<string> the checked languages with English, in the site's order */
    private static function enabledFrom(mixed $value): array
    {
        $checked = is_array($value) ? $value : [];

        return array_values(array_filter(SiteLanguages::ALL, fn (string $l) => $l === SiteLanguages::ALWAYS_ON || in_array($l, $checked, true)));
    }

    protected function fieldLabels(): array
    {
        return [
            'language_suggestion' => (string) __('cms_site.settings_languages.suggestion'),
            'arabic_digits' => (string) __('cms_site.settings_languages.digits'),
        ];
    }

    protected function legalFields(): array
    {
        return ['enabled_locales', 'default_locale'];
    }

    protected function settingsToForm(array $data): array
    {
        $data['enabled_locales'] = self::enabledFrom($data['enabled_locales'] ?? SiteLanguages::ALL);
        $suggestion = $data['language_suggestion'] ?? null;
        $data['language_suggestion'] = $suggestion === null ? self::ENVIRONMENT : ($suggestion ? 'on' : 'off');

        return $data;
    }

    protected function formToSettings(array $data): array
    {
        $enabled = self::enabledFrom($data['enabled_locales'] ?? []);
        $default = (string) ($data['default_locale'] ?? SiteLanguages::ALWAYS_ON);

        return [
            'enabled_locales' => $enabled,
            'default_locale' => in_array($default, $enabled, true) ? $default : SiteLanguages::ALWAYS_ON,
            'language_suggestion' => match ($data['language_suggestion'] ?? self::ENVIRONMENT) {
                'on' => true,
                'off' => false,
                default => null,
            },
            'arabic_digits' => ($data['arabic_digits'] ?? 'western') === 'eastern' ? 'eastern' : 'western',
        ];
    }
}
