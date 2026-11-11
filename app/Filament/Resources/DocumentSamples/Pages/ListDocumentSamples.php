<?php

namespace App\Filament\Resources\DocumentSamples\Pages;

use App\Filament\Resources\DocumentSamples\DocumentSampleResource;
use Filament\Resources\Pages\ListRecords;

class ListDocumentSamples extends ListRecords
{
    protected static string $resource = DocumentSampleResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('cms_media.docs.description');
    }
}
