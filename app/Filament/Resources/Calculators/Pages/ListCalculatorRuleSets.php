<?php

namespace App\Filament\Resources\Calculators\Pages;

use App\Filament\Resources\Calculators\CalculatorResource;
use App\Models\Content\CalculatorRuleSet;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * The calculator rule sets by calculator and version, with "New version": a copy of the calculator's latest version as
 * a new draft (version + 1) with the effective date the super admin chooses (never before the latest version's date,
 * so the new version can take effect).
 */
class ListCalculatorRuleSets extends ListRecords
{
    protected static string $resource = CalculatorResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.calculators.subheading');
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        foreach (CalculatorRuleSet::CALCULATORS as $calculator) {
            $actions[] = Action::make('newVersion_'.$calculator)
                ->label(CalculatorResource::calculatorLabel($calculator))
                ->modalHeading((string) __('cms_site.calculators.new_version_heading', ['calculator' => CalculatorResource::calculatorLabel($calculator)]))
                ->modalDescription(fn () => (string) __('cms_site.calculators.new_version_body', [
                    'version' => (int) (CalculatorResource::latest($calculator)?->version ?? 0),
                ]))
                ->modalSubmitActionLabel((string) __('cms_site.calculators.new_version_submit'))
                ->schema([
                    DatePicker::make('effective_from')
                        ->label((string) __('cms_site.calculators.effective_from'))
                        ->helperText((string) __('cms_site.calculators.effective_from_hint'))
                        ->native(false)->displayFormat('j M Y')->format('Y-m-d')
                        ->minDate(fn () => CalculatorResource::latest($calculator)?->effective_from?->toDateString())
                        ->required(),
                ])
                ->action(function (array $data) use ($calculator) {
                    $record = CalculatorResource::newVersion($calculator, (string) $data['effective_from']);
                    Notification::make()->success()->title((string) __('cms_site.calculators.new_version_done', ['version' => $record->version]))->send();

                    return redirect(CalculatorResource::getUrl('edit', ['record' => $record]));
                });
        }

        return [
            ActionGroup::make($actions)
                ->label((string) __('cms_site.calculators.new_version'))
                ->icon(Heroicon::OutlinedDocumentDuplicate)
                ->color('primary')
                ->button(),
        ];
    }
}
