<?php

namespace App\Filament\Resources\AssociatedSites\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\AssociatedSites\AssociatedSiteResource;
use Filament\Resources\Pages\EditRecord;

class EditAssociatedSite extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = AssociatedSiteResource::class;
}
