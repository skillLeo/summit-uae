<?php

namespace App\Filament\Resources\CaseStudies\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\CaseStudies\CaseStudyResource;
use Filament\Resources\Pages\EditRecord;

class EditCaseStudy extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = CaseStudyResource::class;
}
