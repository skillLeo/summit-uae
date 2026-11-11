<?php

namespace App\Filament\Resources\AssociatedSites\Pages;

use App\Filament\Cms\Concerns\CreatesCmsRecord;
use App\Filament\Resources\AssociatedSites\AssociatedSiteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssociatedSite extends CreateRecord
{
    use CreatesCmsRecord;

    protected static string $resource = AssociatedSiteResource::class;
}
