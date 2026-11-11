<?php

namespace App\Filament\Resources\Calculators\Pages;

use App\Filament\Cms\Concerns\EditsCmsRecord;
use App\Filament\Resources\Calculators\CalculatorResource;
use Filament\Resources\Pages\EditRecord;

/** One calculator rule set: its effective date, legal basis and rules, the test panel, and publishing (super admin). */
class EditCalculatorRuleSet extends EditRecord
{
    use EditsCmsRecord;

    protected static string $resource = CalculatorResource::class;
}
