<?php

namespace App\Filament\Resources\Situations\Pages;

use App\Filament\Resources\Situations\SituationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSituations extends ListRecords
{
    protected static string $resource = SituationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
