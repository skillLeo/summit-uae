<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Requests\RequestExport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use App\Models\Enquiry;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The inbox (docs/briefs/design-2.1-and-admin-cms.md §10.3): the saved views of §9.1 as tabs with counts (New, In
 * review, Assigned to me, Closed, Spam, All). The active tab is remembered for the request detail's J / K (previous and
 * next follow the same view and order).
 */
class ListEnquiries extends ListRecords
{
    protected static string $resource = EnquiryResource::class;

    public const SESSION_TAB = 'requests.inbox.tab';

    public function getTitle(): string|Htmlable
    {
        return (string) __('requests.nav.inbox');
    }

    public function getSubheading(): ?string
    {
        return (string) __('requests.empty.description');
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $tabs = [];
        foreach (self::views() as $key => $scope) {
            $tabs[$key] = Tab::make(__('requests.tabs.'.$key))
                ->badge(fn (): int => $scope(EnquiryResource::getEloquentQuery())->count())
                ->badgeColor($key === 'new' ? 'primary' : 'gray')
                ->modifyQueryUsing($scope);
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'new';
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();
        session()->put(self::SESSION_TAB, $this->activeTab);
    }

    public function mount(): void
    {
        parent::mount();
        session()->put(self::SESSION_TAB, $this->activeTab);
    }

    /** @return array<string, \Closure(Builder): Builder> */
    public static function views(): array
    {
        return [
            'new' => fn (Builder $query): Builder => $query->where('status', Enquiry::STATUS_NEW),
            'in_review' => fn (Builder $query): Builder => $query->where('status', Enquiry::STATUS_IN_REVIEW),
            'mine' => fn (Builder $query): Builder => $query->where('assignee_id', auth()->id())->whereIn('status', [Enquiry::STATUS_NEW, Enquiry::STATUS_IN_REVIEW]),
            'closed' => fn (Builder $query): Builder => $query->where('status', Enquiry::STATUS_CLOSED),
            'spam' => fn (Builder $query): Builder => $query->where('status', Enquiry::STATUS_SPAM),
            'all' => fn (Builder $query): Builder => $query,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('exportCsv')->label(__('requests.action.export_csv'))->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn () => RequestExport::download($this->getFilteredSortedTableQuery()->with('assignee')->get(), 'csv')),
                Action::make('exportXlsx')->label(__('requests.action.export_xlsx'))->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn () => RequestExport::download($this->getFilteredSortedTableQuery()->with('assignee')->get(), 'xlsx')),
            ])
                ->label(__('requests.action.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->button()
                ->color('gray')
                ->visible(fn (): bool => CmsAccess::can('requests', 'export')),
        ];
    }
}
