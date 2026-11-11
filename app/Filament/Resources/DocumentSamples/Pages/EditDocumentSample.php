<?php

namespace App\Filament\Resources\DocumentSamples\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\DocumentSamples\DocumentSampleResource;
use Filament\Resources\Pages\EditRecord;

class EditDocumentSample extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = DocumentSampleResource::class;
}
