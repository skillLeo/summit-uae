<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Cms\Concerns\CreatesCmsRecord;
use App\Filament\Resources\Faqs\FaqResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaq extends CreateRecord
{
    use CreatesCmsRecord;

    protected static string $resource = FaqResource::class;
}
