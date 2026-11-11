<?php

namespace App\Filament\Resources\Situations\Pages;

use App\Filament\Cms\Concerns\CreatesCmsRecord;
use App\Filament\Resources\Situations\SituationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSituation extends CreateRecord
{
    use CreatesCmsRecord;

    protected static string $resource = SituationResource::class;
}
