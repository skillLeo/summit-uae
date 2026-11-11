<?php

namespace App\Filament\Resources\Team\Pages;

use App\Filament\Resources\Team\TeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeamMembers extends ListRecords
{
    protected static string $resource = TeamResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('cms_media.team.description');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
