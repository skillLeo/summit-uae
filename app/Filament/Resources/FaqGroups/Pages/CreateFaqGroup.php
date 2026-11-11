<?php

namespace App\Filament\Resources\FaqGroups\Pages;

use App\Filament\Cms\Concerns\CreatesCmsRecord;
use App\Filament\Resources\FaqGroups\FaqGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaqGroup extends CreateRecord
{
    use CreatesCmsRecord;

    protected static string $resource = FaqGroupResource::class;
}
