<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Support\AdminLocale;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * The admin's own profile: name, e-mail, password, and the interface language of the panel
 * (users.admin_locale; Arabic turns the panel right to left). is_admin is never editable here.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            Select::make('admin_locale')
                ->label(__('admin.locale.label'))
                ->options(collect(User::ADMIN_LOCALES)->mapWithKeys(fn (string $l) => [$l => AdminLocale::nativeName($l)])->all())
                ->default(fn (): string => app()->getLocale())
                ->selectablePlaceholder(false)
                ->native(true)
                ->in(User::ADMIN_LOCALES)
                ->required(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['admin_locale'] = AdminLocale::valid($data['admin_locale'] ?? null) ? $data['admin_locale'] : app()->getLocale();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $locale = $data['admin_locale'] ?? null;
        unset($data['admin_locale']);

        // admin_locale is not mass assignable on User: set it explicitly, validated against the list.
        if ($record instanceof User && AdminLocale::valid($locale)) {
            $record->forceFill(['admin_locale' => $locale]);
            session()->put(AdminLocale::SESSION_KEY, $locale);
        }

        return parent::handleRecordUpdate($record, $data);
    }

    protected function getRedirectUrl(): ?string
    {
        // Reload so a new interface language (and direction) applies to the whole page.
        return static::getUrl();
    }
}
