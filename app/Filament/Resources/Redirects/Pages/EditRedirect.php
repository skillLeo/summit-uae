<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRedirect extends EditRecord
{
    protected static string $resource = RedirectResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ['updated_by' => auth()->id()] + $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->modalDescription((string) __('cms_site.redirects.delete_body'))];
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }
}
