<?php

namespace App\Filament\Cms\Pages\Settings\Concerns;

use App\Filament\Cms\Concerns\HasEditingLocale;
use App\Filament\Cms\Support\CmsAccess;
use Filament\Notifications\Notification;

/**
 * A settings screen of the CMS (docs/briefs/design-2.1-and-admin-cms.md §9.4, §12.3; Filament\Pages\SettingsPage of
 * filament/spatie-laravel-settings-plugin): one settings group per screen, grouped fields with help text, the locale
 * switcher for the texts per language (App\Filament\Cms\Forms\Translatable), and a save that takes effect at once.
 * Saving a group fires Spatie\LaravelSettings\Events\SettingsSaved, which replaces the published-content cache
 * version (App\Providers\AppServiceProvider), so the public site shows the new values on the next request.
 *
 * Settings belong to the super admin (§12.1); App\Filament\Cms\Support\CmsAccess::superAdmin() is the Phase 7 hook.
 */
trait EditsSettings
{
    use HasEditingLocale;

    public static function canAccess(): bool
    {
        return CmsAccess::superAdmin();
    }

    public function canEdit(): bool
    {
        return CmsAccess::superAdmin();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ['cms_editing_locale' => $this->resolveEditingLocale()] + $this->settingsToForm($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['cms_editing_locale']);

        return $this->formToSettings($data);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function settingsToForm(array $data): array
    {
        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function formToSettings(array $data): array
    {
        return $data;
    }

    public function getSavedNotification(): ?Notification
    {
        return Notification::make()->success()
            ->title((string) __('cms_site.settings.saved'))
            ->body((string) __('cms_site.settings.saved_body'));
    }
}
