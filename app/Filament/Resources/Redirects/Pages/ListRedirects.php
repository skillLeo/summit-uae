<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.redirects.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label((string) __('cms_site.redirects.create'))];
    }
}
