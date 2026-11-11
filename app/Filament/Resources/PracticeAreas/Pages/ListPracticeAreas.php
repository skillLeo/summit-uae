<?php

namespace App\Filament\Resources\PracticeAreas\Pages;

use App\Filament\Resources\PracticeAreas\PracticeAreaResource;
use Filament\Resources\Pages\ListRecords;

class ListPracticeAreas extends ListRecords
{
    protected static string $resource = PracticeAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
