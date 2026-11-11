<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\Faqs\FaqResource;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = FaqResource::class;
}
