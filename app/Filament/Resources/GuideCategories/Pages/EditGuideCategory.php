<?php

namespace App\Filament\Resources\GuideCategories\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\GuideCategories\GuideCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditGuideCategory extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = GuideCategoryResource::class;
}
