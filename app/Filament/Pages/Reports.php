<?php

namespace App\Filament\Pages;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Reports\ReportData;
use App\Filament\Reports\ReportState;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\User;
use App\Reports\Period;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Reports: analysis with filters (docs/briefs/design-2.1-and-admin-cms.md §11.2 to §11.4, Phase 6).
 *
 * One filter row (date range with Custom from / to, compare with the previous period, matter type, site language,
 * preferred language, status, assignee, source page), kept in the URL (?filters[range]=90d) and in the session, so a
 * reload keeps them. Tabs Volume, Mix, Workflow, Sources (?tab=mix). Each report is a card (App\Filament\Reports\
 * Widgets): a chart (Chart.js through Filament's ChartWidget) or a Blade heatmap / table with bars in cells, a
 * "Chart | Table" toggle and the CSV of that table. Every figure comes from App\Reports\Kpi (spam excluded except in
 * the spam rate's numerator; demo records count outside production).
 */
class Reports extends Page
{
    use HasFiltersForm;

    protected static string|UnitEnum|null $navigationGroup = 'overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    /** Right after the Dashboard (Filament gives the dashboard -2). */
    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.reports.page';

    #[Url]
    public string $tab = 'volume';

    public static function canAccess(): bool
    {
        return CmsAccess::can('reports', 'view');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('reports.title');
    }

    public function getTitle(): string|Htmlable
    {
        return (string) __('reports.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('reports.subheading');
    }

    public function mount(): void
    {
        if (! array_key_exists($this->tab, ReportData::TABS)) {
            $this->tab = 'volume';
        }
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, ReportData::TABS)) {
            $this->tab = 'volume';
        }
    }

    public function reportState(): ReportState
    {
        return ReportState::from($this->filters);
    }

    /** @return array<string, string> */
    public static function tabs(): array
    {
        $out = [];
        foreach (array_keys(ReportData::TABS) as $tab) {
            $out[$tab] = (string) __('reports.tabs.'.$tab);
        }

        return $out;
    }

    public function filtersForm(Schema $schema): Schema
    {
        $notChosen = [ReportState::NOT_CHOSEN => (string) __('reports.filter.not_chosen')];
        $any = (string) __('reports.filter.any');
        $ranges = [];
        foreach (Period::PRESETS as $preset) {
            $ranges[$preset] = (string) __('reports.range.'.$preset);
        }
        $statuses = [];
        foreach (ReportState::STATUSES as $status) {
            $statuses[$status] = EnquiryStatus::label($status);
        }
        $isCustom = fn (Get $get): bool => $get('range') === 'custom';

        return $schema
            ->columns(['default' => 1, 'sm' => 2, 'md' => 2, 'lg' => 3, 'xl' => 5, '2xl' => 5])
            ->extraAttributes(['class' => 'slc-report-filters__form'])
            ->components([
                Select::make('range')
                    ->label(__('reports.filter.range'))
                    ->options($ranges)
                    ->default(ReportState::DEFAULT_PRESET)
                    ->selectablePlaceholder(false)
                    ->extraInputAttributes(['data-report-range' => 'true']),
                DatePicker::make('from')
                    ->label(__('reports.filter.from'))
                    ->native()
                    ->maxDate(fn () => now(Period::zone())->toDateString())
                    ->visible($isCustom),
                DatePicker::make('to')
                    ->label(__('reports.filter.to'))
                    ->native()
                    ->maxDate(fn () => now(Period::zone())->toDateString())
                    ->visible($isCustom),
                Select::make('matter_type')
                    ->label(__('reports.filter.matter_type'))
                    ->placeholder($any)
                    ->options(EnquiryLabels::matterOptions() + $notChosen),
                Select::make('locale')
                    ->label(__('reports.filter.locale'))
                    ->placeholder($any)
                    ->options(EnquiryLabels::languageOptions()),
                Select::make('preferred_language')
                    ->label(__('reports.filter.preferred_language'))
                    ->placeholder($any)
                    ->options(EnquiryLabels::languageOptions() + $notChosen),
                Select::make('status')
                    ->label(__('reports.filter.status'))
                    ->placeholder($any)
                    ->options($statuses),
                Select::make('assignee_id')
                    ->label(__('reports.filter.assignee_id'))
                    ->placeholder($any)
                    ->options(fn (): array => self::assigneeOptions() + [ReportState::NOT_CHOSEN => (string) __('reports.filter.unassigned')]),
                Select::make('source_page')
                    ->label(__('reports.filter.source_page'))
                    ->placeholder($any)
                    ->options(fn (): array => self::sourcePageOptions() + [ReportState::NOT_CHOSEN => (string) __('reports.value.none_recorded')]),
                Toggle::make('compare')
                    ->label(__('reports.filter.compare'))
                    ->inline(false),
            ]);
    }

    /** @return array<string, string> staff who can be assigned, and anyone who has been */
    public static function assigneeOptions(): array
    {
        $ids = Enquiry::query()->whereNotNull('assignee_id')->distinct()->pluck('assignee_id')->all();

        return User::query()
            ->where(fn ($q) => $q->where('is_admin', true)->orWhereIn('id', $ids))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => (string) $name])
            ->all();
    }

    /** @return array<string, string> the pages requests were sent from, most used first */
    public static function sourcePageOptions(): array
    {
        return Enquiry::query()->counted()
            ->whereNotNull('source_page')->where('source_page', '!=', '')
            ->selectRaw('source_page, count(*) as aggregate')
            ->groupBy('source_page')
            ->orderByDesc('aggregate')
            ->limit(50)
            ->pluck('source_page')
            ->mapWithKeys(fn ($page) => [(string) $page => (string) $page])
            ->all();
    }

    public function resetFilters(): void
    {
        $this->filters = null;
        $this->getFiltersForm()->fill();
        $this->updatedFilters();
    }
}
