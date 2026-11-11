<?php

namespace App\Filament\Cms\Concerns;

use App\Content\Cms\Tree;
use App\Filament\Cms\Support\CmsLabels;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The locale switcher of an edit screen (docs/briefs/design-2.1-and-admin-cms.md §8.3: "Editing: English"). Every
 * translatable field of the form exists in all three languages (App\Filament\Cms\Forms\Translatable); this property
 * decides which one is shown. Starts in the admin's interface language. A validation error in a hidden language
 * switches to that language.
 */
trait HasEditingLocale
{
    public string $editingLocale = 'en';

    public bool $editingLocaleResolved = false;

    /** The first time the form is filled: ?locale=ar, else the admin's interface language. */
    protected function resolveEditingLocale(): string
    {
        if (! $this->editingLocaleResolved) {
            $requested = request()->query('locale');
            $this->editingLocale = is_string($requested) && in_array($requested, Tree::LOCALES, true)
                ? $requested
                : (in_array(app()->getLocale(), Tree::LOCALES, true) ? app()->getLocale() : 'en');
            $this->editingLocaleResolved = true;
        }

        return $this->editingLocale;
    }


    public function setEditingLocale(string $locale): void
    {
        if (in_array($locale, Tree::LOCALES, true)) {
            $this->editingLocale = $locale;
            $this->editingLocaleResolved = true;
        }
    }

    protected function onValidationError(ValidationException $exception): void
    {
        foreach (array_keys($exception->errors()) as $key) {
            if (preg_match('/\.(en|ar|ru)(\.|$)/', (string) $key, $m) && $m[1] !== $this->editingLocale) {
                $this->editingLocale = $m[1];
                if (isset($this->data) && is_array($this->data)) {
                    $this->data['cms_editing_locale'] = $m[1];
                }
                Notification::make()->warning()->title((string) __('cms.locale.error_switched', ['language' => CmsLabels::locale($m[1])]))->send();

                return;
            }
        }
    }
}
