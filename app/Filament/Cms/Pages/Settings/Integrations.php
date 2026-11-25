<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Filament\Cms\Support\CmsAccess;
use App\Settings\IntegrationSettings;
use App\Support\Analytics;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Integrations (docs/briefs/design2-only-black-header-ga4.md Part C2; App\Settings\IntegrationSettings): the Google
 * Analytics 4 measurement ID. Super admin only: no built-in role holds settings_integrations (App\Access\Screens), and
 * the field itself is read-only for anyone else (a custom role given the screen sees it but cannot change it).
 * Validated against App\Support\Analytics::PATTERN (^G-[A-Z0-9]{6,12}$); empty = the server setting GA4_MEASUREMENT_ID
 * (config services.ga4.id). The usual confirmed save (the confirmation says it has a data impact) and one activity log
 * entry with the old and new values. The public site reads it at once (the save replaces the content cache version).
 */
class Integrations extends SettingsPage
{
    use EditsSettings;

    protected static string $screen = 'settings_integrations';

    protected static string $settings = IntegrationSettings::class;

    protected static ?string $slug = 'settings/integrations';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 75;

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.settings_integrations.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.settings_integrations.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.settings_integrations.subheading');
    }

    public function form(Schema $schema): Schema
    {
        $superAdmin = CmsAccess::superAdmin();
        $env = config('services.ga4.id');
        $env = is_string($env) && trim($env) !== '' ? trim($env) : null;

        return $schema->columns(1)->components([
            Section::make((string) __('cms_site.settings_integrations.ga4'))
                ->description((string) __($superAdmin ? 'cms_site.settings_integrations.ga4_hint' : 'cms_site.settings_integrations.locked'))
                ->schema([
                    TextInput::make('ga4_measurement_id')->label((string) __('cms_site.settings_integrations.ga4_id'))
                        ->helperText(new HtmlString(str_replace('%env%', $env === null
                            ? e((string) __('cms_site.settings_integrations.env_empty'))
                            : '<bdi dir="ltr">'.e($env).'</bdi>', e((string) __('cms_site.settings_integrations.ga4_id_hint', ['env' => '%env%'])))))
                        ->placeholder('G-XXXXXXXXXX')
                        ->maxLength(14)
                        ->regex(Analytics::PATTERN)
                        ->validationMessages(['regex' => (string) __('cms_site.settings_integrations.errors.format')])
                        ->disabled(! $superAdmin)
                        ->extraInputAttributes(['dir' => 'ltr', 'spellcheck' => 'false', 'autocomplete' => 'off']),
                    Text::make(fn (): HtmlString => self::status())->extraAttributes(['class' => 'cms-settings-note', 'aria-live' => 'polite']),
                ]),
        ]);
    }

    /** One sentence: what the public site does with the saved values on this server. */
    public static function status(): HtmlString
    {
        $id = Analytics::measurementId();
        if ($id === null) {
            return new HtmlString(e((string) __('cms_site.settings_integrations.status_off')));
        }
        $key = app()->isProduction() ? 'cms_site.settings_integrations.status_on' : 'cms_site.settings_integrations.status_not_production';

        return new HtmlString(str_replace('%id%', '<bdi dir="ltr">'.e($id).'</bdi>', e((string) __($key, ['id' => '%id%']))));
    }

    protected function legalFields(): array
    {
        return ['ga4_measurement_id'];
    }

    protected function fieldLabels(): array
    {
        return ['ga4_measurement_id' => (string) __('cms_site.settings_integrations.ga4_id')];
    }

    protected function formToSettings(array $data): array
    {
        $id = is_string($data['ga4_measurement_id'] ?? null) ? trim($data['ga4_measurement_id']) : null;
        if (! CmsAccess::superAdmin()) {
            $id = app(IntegrationSettings::class)->ga4_measurement_id; // the ID belongs to the super admin
        }

        return ['ga4_measurement_id' => $id === '' ? null : $id];
    }
}
