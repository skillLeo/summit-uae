<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\EnquiryStatus;
use App\Models\Enquiry;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Review a request: status and internal note only. The status column is a plain string on the shared
 * model, so it is validated against EnquiryStatus here and status_changed_at is stamped on a change.
 *
 * @property Enquiry $record
 */
class EditEnquiry extends EditRecord
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

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->color('gray'),
        ];
    }

    /** Only the review fields ever reach the model, whatever the browser sends. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $status = EnquiryStatus::normalize($data['status'] ?? null);
        $updates = ['internal_note' => filled($data['internal_note'] ?? null) ? (string) $data['internal_note'] : null];

        if ($status !== null && $status !== $record->getAttribute('status')) {
            $updates['status'] = $status;
            $updates['status_changed_at'] = now();
        }

        $record->forceFill($updates)->save();

        return $record;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->success()->title(__('admin.notify.saved'));
    }

    protected function getRedirectUrl(): ?string
    {
        return EnquiryResource::getUrl('view', ['record' => $this->record]);
    }
}
