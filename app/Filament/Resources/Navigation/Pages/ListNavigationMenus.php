<?php

namespace App\Filament\Resources\Navigation\Pages;

use App\Filament\Resources\Navigation\NavigationResource;
use Filament\Resources\Pages\ListRecords;

class ListNavigationMenus extends ListRecords
{
    protected static string $resource = NavigationResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.navigation.subheading');
    }
}
