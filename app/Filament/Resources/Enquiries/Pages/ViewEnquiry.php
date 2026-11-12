<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryActions;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryStats;
use App\Models\Enquiry;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The request detail (docs/briefs/design-2.1-and-admin-cms.md §10.4). Keyboard: J and K for the next and previous
 * request of the inbox view the list was on (same order), A assign, S status, N note, E email, ? for the list of
 * shortcuts (resources/views/filament/admin/shortcuts.blade.php presses the button with that data-shortcut).
 *
 * @property Enquiry $record
 */
class ViewEnquiry extends ViewRecord
{
    protected static string $resource = EnquiryResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->reference;
    }

    public function getSubheading(): ?string
    {
        return __('admin.field.received').': '.($this->record->received_at ?? $this->record->created_at)
            ->copy()->setTimezone(EnquiryStats::timezone())
            ->translatedFormat(AdminFormat::dateTime());
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->neighbour('previous', 'k', Heroicon::OutlinedChevronUp),
            $this->neighbour('next', 'j', Heroicon::OutlinedChevronDown),
            ...EnquiryActions::transitions(),
            EnquiryActions::spam(),
            EnquiryActions::notSpam(),
            EnquiryActions::privacy(),
        ];
    }

    /** Also callable on the page itself (the same actions the Status and Notes panels show). */
    public function statusAction(): Action
    {
        return EnquiryActions::status();
    }

    public function assignAction(): Action
    {
        return EnquiryActions::assign();
    }

    public function noteAction(): Action
    {
        return EnquiryActions::note();
    }

    public function editNoteAction(): Action
    {
        return EnquiryActions::editNote();
    }

    public function deleteNoteAction(): Action
    {
        return EnquiryActions::deleteNote();
    }

    private function neighbour(string $direction, string $key, Heroicon $icon): Action
    {
        $target = $this->neighbourRecord($direction);

        return Action::make($direction)
            ->label(__('requests.action.'.$direction))
            ->hiddenLabel()
            ->tooltip(__('requests.action.'.$direction).' ('.strtoupper($key).')')
            ->icon($icon)
            ->color('gray')
            ->outlined()
            ->extraAttributes(['data-shortcut' => $key])
            ->disabled($target === null)
            ->url($target === null ? null : EnquiryResource::getUrl('view', ['record' => $target]));
    }

    /** The next (older) or previous (newer) request in the inbox view the list was on. */
    public function neighbourRecord(string $direction): ?Enquiry
    {
        $tab = (string) session(ListEnquiries::SESSION_TAB, 'all');
        $scope = ListEnquiries::views()[$tab] ?? ListEnquiries::views()['all'];
        /** @var Builder<Enquiry> $query */
        $query = $scope(EnquiryResource::getEloquentQuery());
        $at = $this->record->received_at ?? $this->record->created_at;
        $id = $this->record->getKey();
        $older = $direction === 'next';

        return $query
            ->where(fn (Builder $q) => $q->where('received_at', $older ? '<' : '>', $at)
                ->orWhere(fn (Builder $q) => $q->where('received_at', $at)->where('id', $older ? '<' : '>', $id)))
            ->orderBy('received_at', $older ? 'desc' : 'asc')
            ->orderBy('id', $older ? 'desc' : 'asc')
            ->first();
    }
}
