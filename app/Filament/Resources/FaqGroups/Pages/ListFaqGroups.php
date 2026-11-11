<?php

namespace App\Filament\Resources\FaqGroups\Pages;

use App\Filament\Resources\FaqGroups\FaqGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFaqGroups extends ListRecords
{
    protected static string $resource = FaqGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
