<?php

namespace App\Filament\Resources\CaseStudies\Pages;

use App\Filament\Cms\Concerns\CreatesCmsRecord;
use App\Filament\Resources\CaseStudies\CaseStudyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCaseStudy extends CreateRecord
{
    use CreatesCmsRecord;

    protected static string $resource = CaseStudyResource::class;
}
