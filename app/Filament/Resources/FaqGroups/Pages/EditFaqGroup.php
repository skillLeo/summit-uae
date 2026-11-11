<?php

namespace App\Filament\Resources\FaqGroups\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\FaqGroups\FaqGroupResource;
use Filament\Resources\Pages\EditRecord;

class EditFaqGroup extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = FaqGroupResource::class;
}
