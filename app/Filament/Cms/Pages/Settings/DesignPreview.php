<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Design\DesignManager;
use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Filament\Support\RequestFormat;
use App\Settings\DesignSettings;
use App\Support\Audit;
use App\Support\Languages as SiteLanguages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Design Preview (docs/briefs/design-2.1-and-admin-cms.md §12.3; App\Settings\DesignSettings, applied by
 * App\Design\DesignManager, App\Http\Middleware\SetDesign and DesignSwitcher): the design visitors see until they
 * choose one, who sees the "Design 1 | Design 2" switch, and share links: a signed link, valid 7 days, that shows the
 * switch in the browser that opens it. Both settings are null by default, and then the server settings
 * SUMMIT_DEFAULT_DESIGN and SUMMIT_DESIGN_SWITCHER decide as before; SUMMIT_DESIGN_SWITCHER=false always wins in
 * production. "Revoke share links" ends every link and every browser that opened one.
 */
class DesignPreview extends SettingsPage
{
    use EditsSettings;

    protected static string $screen = 'settings_design';

    protected static string $settings = DesignSettings::class;

    protected static ?string $slug = 'settings/design-preview';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?int $navigationSort = 80;

    private const ENVIRONMENT = 'environment';

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.settings_design.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.settings_design.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.settings_design.subheading');
    }

    public function form(Schema $schema): Schema
    {
        $envDesign = DesignManager::normalize(config('design.default')) ?? 1;
        $envSwitcher = (bool) config('design.switcher');
        $forcedOff = app()->isProduction() && config('design.switcher_env') === false;

        return $schema->columns(1)->components([
            Section::make((string) __('cms_site.settings_design.default'))
                ->description((string) __('cms_site.settings_design.default_hint'))
                ->schema([
                    Radio::make('default_design')->hiddenLabel()
                        ->options([
                            self::ENVIRONMENT => (string) __('cms_site.settings_design.default_env', ['design' => $envDesign]),
                            '1' => (string) __('cms_site.settings_design.design', ['design' => 1]),
                            '2' => (string) __('cms_site.settings_design.design', ['design' => 2]),
                        ])
                        ->required(),
                ]),
            Section::make((string) __('cms_site.settings_design.switcher'))
                ->description((string) __('cms_site.settings_design.switcher_hint'))
                ->schema([
                    Radio::make('switcher')->hiddenLabel()
                        ->options([
                            self::ENVIRONMENT => (string) __('cms_site.settings_design.switcher_env', [
                                'state' => (string) __($envSwitcher ? 'cms_site.settings_design.mode.everyone' : 'cms_site.settings_design.mode.off'),
                            ]),
                            'off' => (string) __('cms_site.settings_design.switcher_off'),
                            'admins' => (string) __('cms_site.settings_design.switcher_admins'),
                            'link' => (string) __('cms_site.settings_design.switcher_link'),
                        ])
                        ->required(),
                    Text::make(fn (): string => (string) __('cms_site.settings_design.now', [
                        'state' => (string) __('cms_site.settings_design.mode.'.app(DesignManager::class)->switcherMode()),
                    ]))->extraAttributes(['class' => 'cms-settings-note']),
                    Text::make((string) __('cms_site.settings_design.production_off'))
                        ->visible($forcedOff)
                        ->extraAttributes(['class' => 'cms-settings-note cms-settings-note--warning']),
                ]),
            Section::make((string) __('cms_site.settings_design.share'))
                ->description((string) __('cms_site.settings_design.share_hint', ['days' => (int) config('design.share_days', 7)]))
                ->schema([
                    TextInput::make('share_link')->label((string) __('cms_site.settings_design.share_link'))
                        ->helperText(fn (Get $get): string => (string) __('cms_site.settings_design.share_link_hint', ['date' => (string) $get('share_expires')]))
                        ->readOnly()
                        ->copyable(copyMessage: (string) __('cms_site.settings_design.copied'))
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => filled($get('share_link')))
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Text::make((string) __('cms_site.settings_design.share_mode_hint'))
                        ->visible(fn (Get $get): bool => filled($get('share_link')) && $get('switcher') !== 'link')
                        ->extraAttributes(['class' => 'cms-settings-note cms-settings-note--warning']),
                    Actions::make([
                        Action::make('createShareLink')
                            ->label((string) __('cms_site.settings_design.create'))
                            ->color('gray')
                            ->visible(fn (): bool => $this->canEdit())
                            ->action(fn () => $this->createShareLink()),
                        Action::make('revokeShareLinks')
                            ->label((string) __('cms_site.settings_design.revoke'))
                            ->color('danger')
                            ->link()
                            ->requiresConfirmation()
                            ->modalHeading((string) __('cms_site.settings_design.revoke_heading'))
                            ->modalDescription((string) __('cms_site.settings_design.revoke_body'))
                            ->modalSubmitActionLabel((string) __('cms_site.settings_design.revoke'))
                            ->visible(fn (): bool => $this->canEdit())
                            ->action(fn () => $this->revokeShareLinks()),
                    ]),
                ]),
        ]);
    }

    public function createShareLink(): void
    {
        if (! $this->canEdit()) {
            return;
        }
        $locale = SiteLanguages::isEnabled(app()->getLocale()) ? app()->getLocale() : SiteLanguages::ALWAYS_ON;
        $link = DesignManager::createShareLink($locale);
        $this->data['share_link'] = $link['url'];
        $this->data['share_expires'] = RequestFormat::dateTime($link['expires']);
        Audit::log(static::screenKey(), 'share_link', null, [
            'group' => DesignSettings::group(),
            'expires_at' => $link['expires']->toIso8601String(),
            'version' => DesignManager::shareVersion(),
        ]);
        Notification::make()->success()->title((string) __('cms_site.settings_design.created'))->send();
    }

    public function revokeShareLinks(): void
    {
        if (! $this->canEdit()) {
            return;
        }
        $settings = app(DesignSettings::class);
        $old = $settings->share_version;
        $settings->share_version = max(1, $old) + 1;
        $settings->save();
        content()->forget(); // this request reads the new version too
        $this->data['share_link'] = null;
        $this->data['share_expires'] = null;
        Audit::log(static::screenKey(), 'update', null, [
            'group' => DesignSettings::group(),
            'changes' => ['share_version' => ['old' => $old, 'new' => $settings->share_version]],
        ]);
        Notification::make()->success()->title((string) __('cms_site.settings_design.revoked'))->send();
    }

    protected function fieldLabels(): array
    {
        return [
            'default_design' => (string) __('cms_site.settings_design.default'),
            'switcher' => (string) __('cms_site.settings_design.switcher'),
        ];
    }

    protected function settingsToForm(array $data): array
    {
        $data['default_design'] = $data['default_design'] === null ? self::ENVIRONMENT : (string) $data['default_design'];
        $data['switcher'] = $data['switcher'] ?? self::ENVIRONMENT;
        unset($data['share_version']);
        $data['share_link'] = null;
        $data['share_expires'] = null;

        return $data;
    }

    protected function formToSettings(array $data): array
    {
        $design = DesignManager::normalize((string) ($data['default_design'] ?? ''));
        $switcher = $data['switcher'] ?? self::ENVIRONMENT;

        return [
            'default_design' => $design,
            'switcher' => in_array($switcher, ['off', 'admins', 'link'], true) ? $switcher : null,
        ];
    }
}
