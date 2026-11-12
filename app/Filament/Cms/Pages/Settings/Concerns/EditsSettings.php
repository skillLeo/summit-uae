<?php

namespace App\Filament\Cms\Pages\Settings\Concerns;

use App\Filament\Cms\Concerns\HasEditingLocale;
use App\Filament\Cms\Support\CmsAccess;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Spatie\LaravelSettings\Settings;

/**
 * A settings screen of the CMS (docs/briefs/design-2.1-and-admin-cms.md §9.4, §12.3; Filament\Pages\SettingsPage of
 * filament/spatie-laravel-settings-plugin): one settings group per screen, grouped fields with help text, the locale
 * switcher for the texts per language (App\Filament\Cms\Forms\Translatable), and a save that takes effect at once.
 * Saving a group fires Spatie\LaravelSettings\Events\SettingsSaved, which replaces the published-content cache
 * version (App\Providers\AppServiceProvider), so the public site shows the new values on the next request.
 *
 * Access (§12.1): each screen names its key of the permission matrix (App\Access\Screens) in `protected static string
 * $screen`; opening needs "<screen>.view", saving "<screen>.update" (the form is read-only otherwise).
 *
 * Saving (§9.4): the Save button, Cmd/Ctrl+S and Enter first validate the form (confirmSave()); when something
 * changed, a confirmation says in one sentence which settings change (and, for the fields of legalFields(), that they
 * have a legal or data impact); only "Save" in that dialog writes them (save()). Every save writes one entry to the
 * activity log (App\Support\Audit: "<screen>.update", properties group + the changed fields with old and new
 * values). Fields of secretFields() are logged as changed without their values.
 *
 * linkedSettings() lets a screen edit a value that belongs to another group (Brand edits the SEO screen's default
 * social image), so the value has one home.
 */
trait EditsSettings
{
    use HasEditingLocale;

    /** @var array<string, mixed>|null the values before the save in progress (beforeSave → afterSave) */
    protected ?array $settingsBeforeSave = null;

    /** @var array<string, mixed> the linked values of the save in progress */
    protected array $linkedPending = [];

    public static function screenKey(): string
    {
        return static::$screen;
    }

    public static function canAccess(): bool
    {
        return CmsAccess::can(static::screenKey(), 'view');
    }

    public function canEdit(): bool
    {
        return CmsAccess::can(static::screenKey(), 'update');
    }

    /** @return list<string> fields whose change has a legal or data impact (the confirmation says so) */
    protected function legalFields(): array
    {
        return [];
    }

    /** @return list<string> fields that are logged as changed, never with their values (none today) */
    protected function secretFields(): array
    {
        return [];
    }

    /**
     * Fields of this screen that are stored in another settings group: form field => [settings class, property].
     *
     * @return array<string, array{0: class-string<Settings>, 1: string}>
     */
    protected function linkedSettings(): array
    {
        return [];
    }

    /** @return array<string, string> field => its label in the confirmation (otherwise the form field's own label) */
    protected function fieldLabels(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($this->linkedSettings() as $field => [$class, $property]) {
            $data[$field] = app($class)->{$property};
        }

        return ['cms_editing_locale' => $this->resolveEditingLocale()] + $this->settingsToForm($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['cms_editing_locale']);
        $data = $this->formToSettings($data);

        $this->linkedPending = [];
        foreach (array_keys($this->linkedSettings()) as $field) {
            if (array_key_exists($field, $data)) {
                $this->linkedPending[$field] = $data[$field];
            }
            unset($data[$field]);
        }

        return $data;
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

    // ------------------------------------------------------------------ confirmation

    /** The form's submit (the Save button, Cmd/Ctrl+S, Enter) asks for the confirmation first. */
    protected function getSubmitFormLivewireMethodName(): string
    {
        return 'confirmSave';
    }

    /** Validates, then opens the confirmation with the changed fields; nothing is written here. */
    public function confirmSave(): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $data = $this->mutateFormDataBeforeSave($this->form->getState());
        $changes = self::diff($this->currentSettings(), $data + $this->linkedPending);
        if ($changes === []) {
            Notification::make()->title((string) __('cms_site.settings.unchanged'))->send();

            return;
        }

        $this->mountAction('saveSettings', ['fields' => array_keys($changes)]);
    }

    public function saveSettingsAction(): Action
    {
        return Action::make('saveSettings')
            ->requiresConfirmation()
            ->modalHeading((string) __('cms_site.settings.confirm.heading'))
            ->modalDescription(fn (array $arguments): string => $this->confirmationSentence((array) ($arguments['fields'] ?? [])))
            ->modalSubmitActionLabel((string) __('cms_site.settings.confirm.save'))
            ->modalCancelActionLabel((string) __('cms_site.settings.confirm.cancel'))
            ->visible(fn (): bool => $this->canEdit())
            ->action(fn () => $this->save());
    }

    /** @param list<string> $fields */
    public function confirmationSentence(array $fields): string
    {
        $fields = array_values(array_filter($fields, 'is_string'));
        $labels = self::joinList(array_map(fn (string $field) => $this->labelOf($field), $fields));
        $legal = array_intersect($fields, $this->legalFields()) !== [];

        return (string) __($legal ? 'cms_site.settings.confirm.body_legal' : 'cms_site.settings.confirm.body', ['fields' => $labels]);
    }

    private function labelOf(string $field): string
    {
        $labels = $this->fieldLabels();
        if (isset($labels[$field])) {
            return $labels[$field];
        }
        foreach ($this->form->getFlatFields(withHidden: true) as $key => $component) {
            if ($key === $field || $component->getName() === $field) {
                $label = $component->getLabel();
                if (filled($label)) {
                    return strip_tags((string) $label);
                }
            }
        }

        return str_replace('_', ' ', $field);
    }

    /** @param list<string> $items "A", "A and B", "A, B and C" in the admin language */
    private static function joinList(array $items): string
    {
        $items = array_map(fn (string $item) => (string) __('cms_site.settings.confirm.quote', ['label' => $item]), $items);
        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }
        $last = array_pop($items);

        return implode((string) __('cms_site.settings.confirm.comma'), $items).(string) __('cms_site.settings.confirm.and').$last;
    }

    // ------------------------------------------------------------------ audit

    /** @return array<string, mixed> the stored values of this screen: its group and its linked values */
    protected function currentSettings(): array
    {
        $values = app(static::getSettings())->toArray();
        foreach ($this->linkedSettings() as $field => [$class, $property]) {
            $values[$field] = app($class)->{$property};
        }

        return $values;
    }

    protected function beforeSave(): void
    {
        $this->settingsBeforeSave = $this->currentSettings();
    }

    protected function afterSave(): void
    {
        foreach ($this->linkedPending as $field => $value) {
            [$class, $property] = $this->linkedSettings()[$field];
            $linked = app($class);
            if ($linked->{$property} !== $value) {
                $linked->{$property} = $value;
                $linked->save();
            }
        }

        $changes = self::diff($this->settingsBeforeSave ?? [], $this->currentSettings());
        if ($changes !== []) {
            foreach (array_intersect(array_keys($changes), $this->secretFields()) as $secret) {
                $changes[$secret] = ['changed' => true];
            }
            Audit::log(static::screenKey(), 'update', null, [
                'group' => app(static::getSettings())::group(),
                'changes' => $changes,
            ]);
        }
        $this->settingsBeforeSave = null;
        $this->afterSettingsSaved($changes);
    }

    /** @param array<string, array{old: mixed, new: mixed}> $changes */
    protected function afterSettingsSaved(array $changes): void {}

    /**
     * The fields of $after whose value differs from $before (lists compare in order, maps by key).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $out = [];
        foreach ($after as $field => $new) {
            $old = $before[$field] ?? null;
            if (self::normalise($old) !== self::normalise($new)) {
                $out[$field] = ['old' => $old, 'new' => $new];
            }
        }

        return $out;
    }

    private static function normalise(mixed $value): string
    {
        if (is_array($value) && ! array_is_list($value)) {
            ksort($value);
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getSavedNotification(): ?Notification
    {
        return Notification::make()->success()
            ->title((string) __('cms_site.settings.saved'))
            ->body((string) __('cms_site.settings.saved_body'));
    }
}
