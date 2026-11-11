<?php

namespace App\Filament\Resources\Enquiries;

use App\Filament\Support\EnquiryStatus;
use App\Models\Enquiry;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Status changes and the attachment download, shared by the list, the view and the edit page.
 * Every status change records status_changed_at; nothing is logged (no personal data in logs).
 */
final class EnquiryActions
{
    public static function setStatus(Enquiry $record, mixed $status): bool
    {
        $status = EnquiryStatus::normalize($status);
        if ($status === null || $record->status === $status) {
            return false;
        }

        $record->forceFill(['status' => $status, 'status_changed_at' => now()])->save();

        return true;
    }

    /** One-click transition on a request: "Mark in review", "Close request" or "Reopen". */
    public static function transition(string $name, EnquiryStatus $to, Heroicon $icon, string $label, array $from): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($to->getColor())
            ->visible(fn (Enquiry $record): bool => in_array($record->status, $from, true))
            ->action(function (Enquiry $record) use ($to): void {
                if (self::setStatus($record, $to->value)) {
                    Notification::make()
                        ->title(__('admin.notify.status_changed', ['status' => $to->getLabel()]))
                        ->success()
                        ->send();
                }
            });
    }

    /** @return list<Action> */
    public static function transitions(): array
    {
        return [
            self::transition('markInReview', EnquiryStatus::InReview, Heroicon::OutlinedClock, (string) __('admin.action.mark_in_review'), [Enquiry::STATUS_NEW]),
            self::transition('close', EnquiryStatus::Closed, Heroicon::OutlinedCheckCircle, (string) __('admin.action.close'), EnquiryStatus::OPEN),
            self::transition('reopen', EnquiryStatus::InReview, Heroicon::OutlinedArrowPath, (string) __('admin.action.reopen'), ['closed', Enquiry::STATUS_SPAM]),
        ];
    }

    public static function statusField(string $name = 'status'): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->label(__('admin.field.status'))
            ->options(EnquiryStatus::class)
            ->inline()
            ->required();
    }

    /** Bulk: change the status of the selected requests. */
    public static function bulkStatus(): BulkAction
    {
        return BulkAction::make('changeStatus')
            ->label(__('admin.action.change_status'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->schema([self::statusField()])
            ->action(function (Collection $records, array $data): void {
                $changed = 0;
                foreach ($records as $record) {
                    $changed += self::setStatus($record, $data['status'] ?? null) ? 1 : 0;
                }
                Notification::make()
                    ->title(trans_choice('admin.notify.status_changed_many', $changed, ['count' => $changed]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * The attachment, if one was stored (only possible while summit.features.attachments_enabled is on):
     * streamed through this Livewire action inside the signed-in session. There is no route and no URL,
     * so nothing can be shared or opened outside the panel. The file name holds only the reference.
     */
    public static function downloadAttachment(): Action
    {
        return Action::make('downloadAttachment')
            ->label(__('admin.action.download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (Enquiry $record): bool => filled($record->attachment_path))
            ->action(function (Enquiry $record): ?StreamedResponse {
                $disk = Storage::disk(Enquiry::ATTACHMENT_DISK);
                $path = (string) $record->attachment_path;
                if ($path === '' || ! $disk->exists($path)) {
                    Notification::make()->title(__('admin.notify.file_missing'))->danger()->send();

                    return null;
                }

                return $disk->download($path, $record->reference.'.'.pathinfo($path, PATHINFO_EXTENSION));
            });
    }
}
