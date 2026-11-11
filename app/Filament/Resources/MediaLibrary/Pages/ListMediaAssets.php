<?php

namespace App\Filament\Resources\MediaLibrary\Pages;

use App\Filament\Resources\MediaLibrary\MediaLibraryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMediaAssets extends ListRecords
{
    protected static string $resource = MediaLibraryResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('cms_media.library.description');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label((string) __('cms_media.library.create'))];
    }
}
