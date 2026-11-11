<?php

namespace App\Filament\Resources\Team\Pages;

use App\Filament\Cms\Concerns\CreatesCmsRecord;
use App\Filament\Resources\Team\TeamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTeamMember extends CreateRecord
{
    use CreatesCmsRecord;

    protected static string $resource = TeamResource::class;

    protected function beforeCreate(): void
    {
        TeamResource::validateAreas(null, $this->data ?? [], fn (string $locale) => $this->switchTo($locale));
    }

    private function switchTo(string $locale): void
    {
        $this->setEditingLocale($locale);
        $this->data['cms_editing_locale'] = $locale;
    }
}
