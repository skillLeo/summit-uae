<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Reports\ReportCsv;
use App\Filament\Reports\ReportData;
use App\Filament\Reports\ReportState;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One card of the Reports page (docs/briefs/design-2.1-and-admin-cms.md §11.2, §11.4): a heading, a "Chart | Table"
 * toggle and the CSV of exactly the table it shows. The page mounts a card per report with the normalised filter state
 * (ReportState::toArray()) and keys it by that state, so a filter change mounts it again with the new figures.
 */
trait IsReportCard
{
    #[Locked]
    public string $report = 'volume';

    /** @var array<string, mixed> */
    #[Locked]
    public array $state = [];

    private ?ReportData $reportData = null;

    public static function canView(): bool
    {
        return CmsAccess::can('reports', 'view');
    }

    public function reportData(): ReportData
    {
        return $this->reportData ??= new ReportData(ReportState::from($this->state));
    }

    /** @return array<string, mixed> */
    public function reportInfo(): array
    {
        return $this->reportData()->report($this->report);
    }

    /** Wide reports (over time, heatmap, matter type) take the whole row of the report grid. */
    public function getColumnSpan(): int|string|array
    {
        return $this->reportInfo()['wide'] ? 'full' : 1;
    }

    public function canExport(): bool
    {
        return CmsAccess::can('reports', 'export');
    }

    public function export(): StreamedResponse
    {
        abort_unless($this->canExport(), 403);
        $data = $this->reportData();

        return ReportCsv::download($data->table($this->report), ReportCsv::filename($this->report, $data));
    }

    public function getHeading(): string
    {
        return $this->reportData()->heading($this->report);
    }

    public function getDescription(): ?string
    {
        return $this->reportData()->description($this->report);
    }
}
