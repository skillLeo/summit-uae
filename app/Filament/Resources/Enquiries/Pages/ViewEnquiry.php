<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryActions;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryStats;
use App\Models\Enquiry;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/** @property Enquiry $record */
class ViewEnquiry extends ViewRecord
{
    protected static string $resource = EnquiryResource::class;

    /** Livewire rehydrates the record without the panel's casts: put them back (encrypted internal note). */
    public function hydrate(): void
    {
        if ($this->record instanceof Enquiry) {
            $this->record->mergeCasts(EnquiryResource::ADMIN_CASTS);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->reference;
    }

    public function getSubheading(): ?string
    {
        return __('admin.field.received').': '.$this->record->created_at
            ->setTimezone(EnquiryStats::timezone())
            ->translatedFormat(AdminFormat::dateTime());
    }

    protected function getHeaderActions(): array
    {
        return [
            ...EnquiryActions::transitions(),
            EditAction::make()->color('gray'),
        ];
    }
}
