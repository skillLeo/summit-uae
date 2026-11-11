<?php

namespace App\Filament\Resources\GuideCategories\Pages;

use App\Filament\Resources\GuideCategories\GuideCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListGuideCategories extends ListRecords
{
    protected static string $resource = GuideCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
