<?php

namespace App\Filament\Resources\Guides\Pages;

use App\Filament\Cms\Concerns\CreatesCmsRecord;
use App\Filament\Resources\Guides\GuideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuide extends CreateRecord
{
    use CreatesCmsRecord;
    protected static string $resource = GuideResource::class;
}
