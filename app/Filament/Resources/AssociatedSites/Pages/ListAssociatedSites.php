<?php

namespace App\Filament\Resources\AssociatedSites\Pages;

use App\Filament\Resources\AssociatedSites\AssociatedSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssociatedSites extends ListRecords
{
    protected static string $resource = AssociatedSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
