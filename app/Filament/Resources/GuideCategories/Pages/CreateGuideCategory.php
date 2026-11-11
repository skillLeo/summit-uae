<?php

namespace App\Filament\Resources\GuideCategories\Pages;

use App\Filament\Cms\Concerns\CreatesCmsRecord;
use App\Filament\Resources\GuideCategories\GuideCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuideCategory extends CreateRecord
{
    use CreatesCmsRecord;

    protected static string $resource = GuideCategoryResource::class;
}
