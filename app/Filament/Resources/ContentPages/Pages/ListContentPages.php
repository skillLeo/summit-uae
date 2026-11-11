<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Resources\ContentPages\ContentPageResource;
use Filament\Resources\Pages\ListRecords;

class ListContentPages extends ListRecords
{
    protected static string $resource = ContentPageResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('cms_pages.pages.description');
    }
}
