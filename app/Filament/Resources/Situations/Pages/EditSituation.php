<?php

namespace App\Filament\Resources\Situations\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\Situations\SituationResource;
use Filament\Resources\Pages\EditRecord;

class EditSituation extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = SituationResource::class;
}
