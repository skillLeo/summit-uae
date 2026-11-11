<?php

namespace App\Filament\Resources\PracticeAreas\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\PracticeAreas\PracticeAreaResource;
use Filament\Resources\Pages\EditRecord;

class EditPracticeArea extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = PracticeAreaResource::class;
}
