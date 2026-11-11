<?php

namespace App\Filament\Resources\Team\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\Team\TeamResource;
use Filament\Resources\Pages\EditRecord;

class EditTeamMember extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = TeamResource::class;

    protected function beforeSave(): void
    {
        TeamResource::validateAreas($this->getRecord(), $this->data ?? [], fn (string $locale) => $this->switchTo($locale));
    }

    private function switchTo(string $locale): void
    {
        $this->setEditingLocale($locale);
        $this->data['cms_editing_locale'] = $locale;
    }
}
