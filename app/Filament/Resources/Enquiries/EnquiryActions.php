<?php

namespace App\Filament\Resources\Enquiries;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Support\EnquiryStatus;
use App\Filament\Support\RequestFormat;
use App\Models\Enquiry;
use App\Models\EnquiryNote;
use App\Models\User;
use App\Requests\Notifier;
use App\Requests\Privacy;
use App\Requests\RequestExport;
use App\Requests\Workflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The request actions of the inbox and the request detail (docs/briefs/design-2.1-and-admin-cms.md §10.2-§10.6). Every
 * change goes through App\Requests\Workflow (status history, first_reviewed_at once) or App\Requests\Privacy (export,
 * anonymise, delete; logged). Permissions: CmsAccess::can('requests', …). Keyboard: each action carries the
 * data-shortcut its key presses (resources/views/filament/admin/shortcuts.blade.php).
 */
final class EnquiryActions
{
    public static function canUpdate(): bool
    {
        return CmsAccess::can('requests', 'update');
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /** @return array<string, string> the staff a request can be assigned to */
    public static function staffOptions(): array
    {
        $out = [];
        foreach (Notifier::staff()->sortBy('name') as $user) {
            $out[(string) $user->getKey()] = RequestFormat::assignee($user);
        }
        // demo case handlers, while demo data exists, so the demo looks like a team
        foreach (User::query()->where('is_demo', true)->orderBy('name')->get() as $user) {
            $out[(string) $user->getKey()] = $user->name;
        }

        return $out;
    }

    // ------------------------------------------------------------------ status

    public static function statusField(): ToggleButtons
    {
        return ToggleButtons::make('status')
            ->label(__('requests.field.status'))
            ->options(EnquiryStatus::class)
            ->inline()
            ->live()
            ->required();
    }

    public static function outcomeField(): Radio
    {
        return Radio::make('outcome')
            ->label(__('requests.field.outcome'))
            ->options(RequestFormat::outcomeOptions())
            ->inline()
            ->visible(fn (Get $get): bool => EnquiryStatus::normalize($get('status')) === Enquiry::STATUS_CLOSED)
            ->required(fn (Get $get): bool => EnquiryStatus::normalize($get('status')) === Enquiry::STATUS_CLOSED);
    }

    public static function status(): Action
    {
        return Action::make('status')
            ->label(__('requests.action.status'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (): bool => self::canUpdate())
            ->extraAttributes(['data-shortcut' => 's'])
            ->modalWidth('lg')
            ->fillForm(fn (Enquiry $record): array => ['status' => $record->status, 'outcome' => $record->outcome])
            ->schema([self::statusField(), self::outcomeField()])
            ->action(function (Enquiry $record, array $data): void {
                $status = EnquiryStatus::normalize($data['status'] ?? null);
                if ($status === null) {
                    return;
                }
                if (Workflow::make()->setStatus($record, $status, self::user(), $data['outcome'] ?? null)) {
                    Notification::make()->success()->title(__('requests.notice.status', ['status' => EnquiryStatus::label($status)]))->send();
                }
            });
    }

    /** One-click transition (list menu and header): Mark in review, Close (asks the outcome), Reopen. */
    public static function transition(string $name, string $to, Heroicon $icon, string $label, array $from): Action
    {
        $action = Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($name === 'markInReview' ? 'primary' : 'gray')
            ->visible(fn (Enquiry $record): bool => self::canUpdate() && in_array($record->status, $from, true) && ! $record->trashed())
            ->action(function (Enquiry $record, array $data) use ($to): void {
                if (Workflow::make()->setStatus($record, $to, self::user(), $data['outcome'] ?? null)) {
                    Notification::make()->success()->title(__('requests.notice.status', ['status' => EnquiryStatus::label($to)]))->send();
                }
            });

        if ($to === Enquiry::STATUS_CLOSED) {
            $action->modalHeading(__('requests.confirm.close_heading'))
                ->modalDescription(__('requests.confirm.close_body'))
                ->modalWidth('lg')
                ->schema([Radio::make('outcome')->label(__('requests.field.outcome'))->options(RequestFormat::outcomeOptions())->required()]);
        }

        return $action;
    }

    /** @return list<Action> */
    public static function transitions(): array
    {
        return [
            self::transition('markInReview', Enquiry::STATUS_IN_REVIEW, Heroicon::OutlinedClock, (string) __('admin.action.mark_in_review'), [Enquiry::STATUS_NEW]),
            self::transition('close', Enquiry::STATUS_CLOSED, Heroicon::OutlinedCheckCircle, (string) __('admin.action.close'), [Enquiry::STATUS_NEW, Enquiry::STATUS_IN_REVIEW]),
            self::transition('reopen', Enquiry::STATUS_IN_REVIEW, Heroicon::OutlinedArrowPath, (string) __('admin.action.reopen'), [Enquiry::STATUS_CLOSED]),
        ];
    }

    public static function spam(): Action
    {
        return Action::make('spam')
            ->label(__('requests.action.spam'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->visible(fn (Enquiry $record): bool => self::canUpdate() && $record->status !== Enquiry::STATUS_SPAM && ! $record->trashed())
            ->requiresConfirmation()
            ->modalHeading(__('requests.confirm.spam_heading'))
            ->modalDescription(__('requests.confirm.spam_body'))
            ->action(function (Enquiry $record): void {
                $reasons = array_values(array_unique([...(array) $record->spam_reasons, 'staff']));
                $record->forceFill(['spam_reasons' => $reasons])->save();
                Workflow::make()->setStatus($record, Enquiry::STATUS_SPAM, self::user());
                Notification::make()->success()->title(__('requests.notice.status', ['status' => EnquiryStatus::label(Enquiry::STATUS_SPAM)]))->send();
            });
    }

    public static function notSpam(): Action
    {
        return Action::make('notSpam')
            ->label(__('requests.action.not_spam'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (Enquiry $record): bool => self::canUpdate() && $record->status === Enquiry::STATUS_SPAM && ! $record->trashed())
            ->action(function (Enquiry $record): void {
                Workflow::make()->setStatus($record, Enquiry::STATUS_NEW, self::user());
                Notification::make()->success()->title(__('requests.notice.status', ['status' => EnquiryStatus::label(Enquiry::STATUS_NEW)]))->send();
            });
    }

    // ------------------------------------------------------------------ assignee

    public static function assign(): Action
    {
        return Action::make('assign')
            ->label(__('requests.action.assign'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('gray')
            ->visible(fn (): bool => self::canUpdate())
            ->extraAttributes(['data-shortcut' => 'a'])
            ->modalWidth('md')
            ->fillForm(fn (Enquiry $record): array => ['assignee_id' => $record->assignee_id === null ? '' : (string) $record->assignee_id])
            ->schema([
                Select::make('assignee_id')
                    ->label(__('requests.field.assignee'))
                    ->options(fn (): array => ['' => __('requests.value.unassigned')] + self::staffOptions())
                    ->native()
                    ->selectablePlaceholder(false),
            ])
            ->action(function (Enquiry $record, array $data): void {
                $id = filled($data['assignee_id'] ?? null) ? (int) $data['assignee_id'] : null;
                if (Workflow::make()->assign($record, $id, self::user())) {
                    $record->load('assignee');
                    Notification::make()->success()->title($id === null ? __('requests.notice.unassigned') : __('requests.notice.assigned', ['name' => $record->assignee?->name]))->send();
                }
            });
    }

    public static function assignMe(): Action
    {
        return Action::make('assignMe')
            ->label(__('requests.action.assign_me'))
            ->icon(Heroicon::OutlinedUser)
            ->color('gray')
            ->visible(fn (Enquiry $record): bool => self::canUpdate() && $record->assignee_id !== auth()->id())
            ->action(function (Enquiry $record): void {
                if (Workflow::make()->assign($record, (int) auth()->id(), self::user())) {
                    Notification::make()->success()->title(__('requests.notice.assigned', ['name' => self::user()?->name]))->send();
                }
            });
    }

    // ------------------------------------------------------------------ notes

    public static function note(): Action
    {
        return Action::make('note')
            ->label(__('requests.action.note'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (Enquiry $record): bool => self::canUpdate() && ! $record->isAnonymised())
            ->extraAttributes(['data-shortcut' => 'n'])
            ->modalWidth('xl')
            ->modalSubmitActionLabel(__('requests.action.save_note'))
            ->schema([
                Textarea::make('body')->label(__('requests.field.note'))->required()->rows(5)->maxLength(5000)->autofocus()
                    ->extraInputAttributes(['dir' => 'auto']),
                CheckboxList::make('mentions')->label(__('requests.field.mentions'))
                    ->helperText(__('requests.field.mentions_hint'))
                    ->options(fn (): array => array_diff_key(self::staffOptions(), [(string) auth()->id() => true]))
                    ->columns(2)
                    ->visible(fn (): bool => count(self::staffOptions()) > 1),
            ])
            ->action(function (Enquiry $record, array $data): void {
                $user = self::user();
                if ($user === null) {
                    return;
                }
                Workflow::make()->addNote($record, $user, (string) $data['body'], array_map('intval', (array) ($data['mentions'] ?? [])));
                Notification::make()->success()->title(__('requests.notice.note_added'))->send();
            });
    }

    public static function editNote(): Action
    {
        return Action::make('editNote')
            ->label(__('requests.action.edit_note'))
            ->icon(Heroicon::OutlinedPencil)
            ->link()
            ->color('gray')
            ->modalWidth('xl')
            ->fillForm(fn (array $arguments): array => ['body' => EnquiryNote::query()->find($arguments['note'] ?? 0)?->body])
            ->schema([Textarea::make('body')->label(__('requests.field.note'))->required()->rows(5)->maxLength(5000)->extraInputAttributes(['dir' => 'auto'])])
            ->action(function (array $arguments, array $data): void {
                $note = EnquiryNote::query()->find($arguments['note'] ?? 0);
                $user = self::user();
                if ($note === null || $user === null || ! Workflow::make()->canChangeNote($note, $user)) {
                    Notification::make()->warning()->title(__('requests.notice.note_locked'))->send();

                    return;
                }
                Workflow::make()->editNote($note, $user, (string) $data['body']);
                Notification::make()->success()->title(__('requests.notice.note_saved'))->send();
            });
    }

    public static function deleteNote(): Action
    {
        return Action::make('deleteNote')
            ->label(__('requests.action.delete_note'))
            ->icon(Heroicon::OutlinedTrash)
            ->link()
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('requests.confirm.delete_note_heading'))
            ->action(function (array $arguments): void {
                $note = EnquiryNote::query()->find($arguments['note'] ?? 0);
                $user = self::user();
                if ($note === null || $user === null || ! Workflow::make()->canChangeNote($note, $user)) {
                    Notification::make()->warning()->title(__('requests.notice.note_locked'))->send();

                    return;
                }
                Workflow::make()->deleteNote($note, $user);
                Notification::make()->success()->title(__('requests.notice.note_deleted'))->send();
            });
    }

    // ------------------------------------------------------------------ contact

    /** @return list<Action> Call, Email, Open WhatsApp (a chat with the number; nothing is sent) */
    public static function contact(): array
    {
        return [
            Action::make('call')->label(__('requests.action.call'))->icon(Heroicon::OutlinedPhone)->color('gray')
                ->visible(fn (Enquiry $record): bool => filled($record->phone))
                ->url(fn (Enquiry $record): string => 'tel:'.$record->phone),
            Action::make('email')->label(__('requests.action.email'))->icon(Heroicon::OutlinedEnvelope)->color('gray')
                ->visible(fn (Enquiry $record): bool => filled($record->email))
                ->extraAttributes(['data-shortcut' => 'e'])
                ->url(fn (Enquiry $record): string => 'mailto:'.$record->email.'?subject='.rawurlencode($record->reference)),
            Action::make('whatsapp')->label(__('requests.action.whatsapp'))->icon(Heroicon::OutlinedChatBubbleLeftRight)->color('gray')
                ->tooltip(__('requests.action.whatsapp_hint'))
                ->visible(fn (Enquiry $record): bool => RequestFormat::whatsappUrl($record->phone) !== null)
                ->url(fn (Enquiry $record): ?string => RequestFormat::whatsappUrl($record->phone), shouldOpenInNewTab: true)
                ->extraAttributes(['rel' => 'noopener noreferrer']),
        ];
    }

    // ------------------------------------------------------------------ privacy

    public static function privacy(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('exportPerson')
                ->label(__('requests.action.export_person'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => CmsAccess::can('requests', 'export'))
                ->action(function (Enquiry $record): StreamedResponse {
                    $data = Privacy::export($record, self::user());
                    Notification::make()->success()->title(__('requests.notice.exported'))->send();

                    return response()->streamDownload(
                        fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                        $record->reference.'-data.json',
                        ['Content-Type' => 'application/json; charset=UTF-8'],
                    );
                }),
            Action::make('anonymise')
                ->label(__('requests.action.anonymise'))
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->visible(fn (Enquiry $record): bool => CmsAccess::can('requests', 'delete') && ! $record->isAnonymised())
                ->requiresConfirmation()
                ->modalHeading(__('requests.confirm.anonymise_heading'))
                ->modalDescription(__('requests.confirm.anonymise_body'))
                ->action(function (Enquiry $record): void {
                    Privacy::anonymise($record, self::user())
                        ? Notification::make()->success()->title(__('requests.notice.anonymised'))->send()
                        : Notification::make()->warning()->title(__('requests.notice.already_anonymised'))->send();
                }),
            Action::make('delete')
                ->label(__('requests.action.delete'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (Enquiry $record): bool => CmsAccess::can('requests', 'delete') && ! $record->trashed())
                ->requiresConfirmation()
                ->modalHeading(__('requests.confirm.delete_heading'))
                ->modalDescription(__('requests.confirm.delete_body'))
                ->action(function (Enquiry $record, $livewire): void {
                    Privacy::delete($record, self::user());
                    Notification::make()->success()->title(__('requests.notice.deleted'))->send();
                    $livewire->redirect(EnquiryResource::getUrl('index'));
                }),
            Action::make('restore')
                ->label(__('requests.action.restore'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn (Enquiry $record): bool => CmsAccess::can('requests', 'restore') && $record->trashed())
                ->action(function (Enquiry $record): void {
                    Privacy::restore($record, self::user());
                    Notification::make()->success()->title(__('requests.notice.restored'))->send();
                }),
            Action::make('forceDelete')
                ->label(__('requests.action.force_delete'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (Enquiry $record): bool => CmsAccess::can('requests', 'force_delete') && $record->trashed())
                ->authorize(fn (): bool => CmsAccess::can('requests', 'force_delete'))
                ->requiresConfirmation()
                ->modalHeading(__('requests.confirm.force_delete_heading'))
                ->modalDescription(__('requests.confirm.force_delete_body'))
                ->action(function (Enquiry $record, $livewire): void {
                    Privacy::forceDelete($record, self::user());
                    $livewire->redirect(EnquiryResource::getUrl('index'));
                }),
        ])
            ->label(__('requests.action.privacy'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->button()
            ->color('gray');
    }

    // ------------------------------------------------------------------ bulk

    /** @return list<BulkAction> */
    public static function bulk(): array
    {
        return [
            BulkAction::make('bulkAssign')
                ->label(__('requests.action.assign'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (): bool => self::canUpdate())
                ->schema([
                    Select::make('assignee_id')->label(__('requests.field.assignee'))
                        ->options(fn (): array => ['' => __('requests.value.unassigned')] + self::staffOptions())
                        ->native()->selectablePlaceholder(false),
                ])
                ->action(function (Collection $records, array $data): void {
                    $id = filled($data['assignee_id'] ?? null) ? (int) $data['assignee_id'] : null;
                    $changed = 0;
                    foreach ($records as $record) {
                        $changed += Workflow::make()->assign($record, $id, self::user()) ? 1 : 0;
                    }
                    Notification::make()->success()->title(trans_choice('requests.notice.assigned_many', $changed, ['count' => $changed]))->send();
                })
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('bulkStatus')
                ->label(__('requests.action.status'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->visible(fn (): bool => self::canUpdate())
                ->schema([self::statusField(), self::outcomeField()])
                ->action(function (Collection $records, array $data): void {
                    $status = EnquiryStatus::normalize($data['status'] ?? null);
                    $changed = 0;
                    foreach ($records as $record) {
                        $changed += $status !== null && Workflow::make()->setStatus($record, $status, self::user(), $data['outcome'] ?? null) ? 1 : 0;
                    }
                    Notification::make()->success()->title(trans_choice('requests.notice.status_many', $changed, ['count' => $changed]))->send();
                })
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('bulkSpam')
                ->label(__('requests.action.spam'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn (): bool => self::canUpdate())
                ->requiresConfirmation()
                ->modalHeading(__('requests.confirm.spam_heading'))
                ->modalDescription(__('requests.confirm.spam_body'))
                ->action(function (Collection $records): void {
                    $changed = 0;
                    foreach ($records as $record) {
                        if ($record->status === Enquiry::STATUS_SPAM) {
                            continue;
                        }
                        $record->forceFill(['spam_reasons' => array_values(array_unique([...(array) $record->spam_reasons, 'staff']))])->save();
                        $changed += Workflow::make()->setStatus($record, Enquiry::STATUS_SPAM, self::user()) ? 1 : 0;
                    }
                    Notification::make()->success()->title(trans_choice('requests.notice.status_many', $changed, ['count' => $changed]))->send();
                })
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('bulkCsv')
                ->label(__('requests.action.export_csv'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => CmsAccess::can('requests', 'export'))
                ->action(fn (Collection $records): StreamedResponse => RequestExport::download($records, 'csv')),
            BulkAction::make('bulkXlsx')
                ->label(__('requests.action.export_xlsx'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => CmsAccess::can('requests', 'export'))
                ->action(fn (Collection $records): StreamedResponse => RequestExport::download($records, 'xlsx')),
        ];
    }

    // ------------------------------------------------------------------ attachment

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
