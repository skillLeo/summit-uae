<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\ContentPages\ContentPageResource;
use Filament\Resources\Pages\EditRecord;

class EditContentPage extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = ContentPageResource::class;
}
