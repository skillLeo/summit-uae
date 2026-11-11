<?php

namespace App\Filament\Resources\Guides\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\Guides\GuideResource;
use Filament\Resources\Pages\EditRecord;

class EditGuide extends EditRecord
{
    use EditsCmsRecord;
    protected static string $resource = GuideResource::class;
}
