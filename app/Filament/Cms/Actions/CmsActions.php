<?php

namespace App\Filament\Cms\Actions;

use App\Content\Cms\CopySource;
use App\Content\Cms\Editing\Preview;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Status;
use App\Content\Cms\Tree;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Cms\Support\CmsLabels;
use App\Filament\Support\EnquiryStats;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The editing actions of every CMS record (docs/briefs/design-2.1-and-admin-cms.md §8.5, §9.3): publish (after a
 * confirmation; Cmd/Ctrl+Enter), preview per language (a signed link, new tab), discard draft, schedule, take off the
 * site, view on site, the approval status, and the Trash. Edit pages save the draft through their own save action
 * (Cmd/Ctrl+S). Record-level actions resolve the record from the action, so they work in headers, schemas and tables.
 */
final class CmsActions
{
    /**
     * Before an action on an edit page: the form's unsaved changes are saved as the draft first, so Publish and Preview
     * always act on what the editor sees. On other pages (tables) nothing happens.
     */
    private static function saveFirst(mixed $livewire): void
    {
        if (is_object($livewire) && method_exists($livewire, 'saveDraftSilently')) {
            $livewire->saveDraftSilently();
        }
    }

    public static function publish(): Action
    {
        return Action::make('cmsPublish')
            ->label((string) __('cms.action.publish'))
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->color('primary')
            ->keyBindings(['mod+enter'])
            ->requiresConfirmation()
            ->modalHeading((string) __('cms.action.publish_heading'))
            ->modalDescription((string) __('cms.action.publish_body'))
            ->modalSubmitActionLabel((string) __('cms.action.publish'))
            ->schema([
                TextInput::make('note')->label((string) __('cms.field.note'))->helperText((string) __('cms.field.note_hint'))->maxLength(190),
            ])
            ->action(function (Model $record, array $data, mixed $livewire) {
                self::saveFirst($livewire);
                $record->refresh();
                Publisher::publish($record, filled($data['note'] ?? null) ? (string) $data['note'] : null);
                Notification::make()->success()->title((string) __('cms.action.published'))->send();
                self::refresh($livewire);
            });
    }

    public static function preview(): ActionGroup
    {
        $actions = [];
        foreach (Tree::LOCALES as $locale) {
            $actions[] = Action::make('cmsPreview_'.$locale)
                ->label((string) __('cms.action.preview_in', ['language' => CmsLabels::locale($locale)]))
                ->extraAttributes(['lang' => app()->getLocale()])
                ->action(function (Model $record, mixed $livewire) use ($locale) {
                    self::saveFirst($livewire);
                    $record->refresh();
                    $livewire->js('window.open('.json_encode(Preview::url($record, $locale)).', "_blank", "noopener")');
                });
        }

        return ActionGroup::make($actions)
            ->label((string) __('cms.action.preview'))
            ->tooltip((string) __('cms.action.preview_hint'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->button();
    }

    public static function viewOnSite(): ActionGroup
    {
        $actions = [];
        foreach (Tree::LOCALES as $locale) {
            $actions[] = Action::make('cmsView_'.$locale)
                ->label(CmsLabels::locale($locale))
                ->url(fn (Model $record) => Preview::publicUrl($record, $locale), shouldOpenInNewTab: true);
        }

        return ActionGroup::make($actions)
            ->label((string) __('cms.action.view_on_site'))
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->color('gray')
            ->link()
            ->visible(fn (?Model $record, mixed $livewire) => ($r = self::recordOf($record, $livewire)) !== null && $r->getAttribute('status') === Status::PUBLISHED && ! $r->trashed());
    }

    public static function discard(): Action
    {
        return Action::make('cmsDiscard')
            ->label((string) __('cms.action.discard'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription((string) __('cms.action.discard_body'))
            ->visible(fn (Model $record) => Publisher::hasDraft($record))
            ->action(function (Model $record, mixed $livewire) {
                Publisher::discard($record);
                Notification::make()->success()->title((string) __('cms.action.discarded'))->send();
                self::refresh($livewire, refill: true);
            });
    }

    public static function schedule(): Action
    {
        $zone = EnquiryStats::timezone();

        return Action::make('cmsSchedule')
            ->label((string) __('cms.action.schedule'))
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->modalHeading((string) __('cms.action.schedule_heading'))
            ->fillForm(fn (Model $record) => ['publish_at' => $record->getAttribute('publish_at')?->copy()->setTimezone($zone)])
            ->schema([
                DateTimePicker::make('publish_at')
                    ->label((string) __('cms.field.publish_at'))
                    ->helperText((string) __('cms.field.publish_at_hint'))
                    ->timezone($zone)
                    ->seconds(false)
                    ->native(false)
                    ->minDate(now($zone)->startOfMinute())
                    ->required(),
            ])
            ->action(function (Model $record, array $data, mixed $livewire) use ($zone) {
                self::saveFirst($livewire);
                $record->refresh();
                $at = Carbon::parse((string) $data['publish_at'], 'UTC');
                Publisher::schedule($record, $at);
                Notification::make()->success()->title((string) __('cms.action.scheduled', ['date' => $at->copy()->setTimezone($zone)->translatedFormat('j M Y, H:i')]))->send();
                self::refresh($livewire);
            });
    }

    public static function unschedule(): Action
    {
        return Action::make('cmsUnschedule')
            ->label((string) __('cms.action.unschedule'))
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (Model $record) => $record->getAttribute('publish_at') !== null)
            ->action(function (Model $record, mixed $livewire) {
                Publisher::schedule($record, null);
                Notification::make()->success()->title((string) __('cms.action.unscheduled'))->send();
                self::refresh($livewire);
            });
    }

    public static function unpublish(): Action
    {
        return Action::make('cmsUnpublish')
            ->label((string) __('cms.action.unpublish'))
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription((string) __('cms.action.unpublish_body'))
            ->visible(fn (Model $record) => $record->getAttribute('status') === Status::PUBLISHED)
            ->action(function (Model $record, mixed $livewire) {
                Publisher::unpublish($record);
                Notification::make()->success()->title((string) __('cms.action.unpublished'))->send();
                self::refresh($livewire);
            });
    }

    /** Mark the record's copy as client approved, or as pending again (brief §8.7). Immediate, not a draft. */
    public static function approval(): ActionGroup
    {
        $make = fn (string $source, string $label) => Action::make('cmsCopy_'.$source)
            ->label((string) __($label))
            ->icon(CmsLabels::copyIcon($source))
            ->visible(fn (Model $record) => $record->getAttribute('copy_source') !== $source && ($source !== CopySource::PENDING || $record->getAttribute('copy_source') === CopySource::CLIENT_APPROVED))
            ->requiresConfirmation()
            ->action(function (Model $record, mixed $livewire) use ($source) {
                $record->setAttribute('copy_source', $source);
                $record->setAttribute('updated_by', auth()->id());
                $record->save();
                Notification::make()->success()->title((string) __('cms.copy.marked'))->send();
                self::refresh($livewire);
            });

        return ActionGroup::make([
            $make(CopySource::CLIENT_APPROVED, 'cms.copy.mark_approved'),
            $make(CopySource::PENDING, 'cms.copy.mark_pending'),
        ])->label((string) __('cms.copy.label'))->icon(Heroicon::OutlinedCheckBadge)->color('gray')->button()
            ->visible(fn (?Model $record, mixed $livewire) => ($r = self::recordOf($record, $livewire)) !== null && $r->getAttribute('copy_source') !== CopySource::DOCX);
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->label((string) __('cms.action.delete'))
            ->successNotificationTitle((string) __('cms.action.deleted'));
    }

    public static function restore(): RestoreAction
    {
        return RestoreAction::make()->label((string) __('cms.action.restore'))->successNotificationTitle((string) __('cms.action.restored'));
    }

    /** Permanent delete: super admin only (brief §8.5, §12.1; CmsAccess is the Phase 7 hook). */
    public static function forceDelete(): ForceDeleteAction
    {
        return ForceDeleteAction::make()
            ->label((string) __('cms.action.force_delete'))
            ->modalDescription((string) __('cms.action.force_delete_body'))
            ->successNotificationTitle((string) __('cms.action.force_deleted'))
            ->visible(fn (Model $record) => CmsAccess::superAdmin() && method_exists($record, 'trashed') && $record->trashed())
            ->authorize(fn () => CmsAccess::superAdmin())
            ->before(fn (Model $record) => $record->revisions()->delete());
    }

    // ------------------------------------------------------------------ bulk

    public static function bulkPublish(): BulkAction
    {
        return BulkAction::make('cmsBulkPublish')
            ->label((string) __('cms.action.publish'))
            ->icon(Heroicon::OutlinedCloudArrowUp)
            ->requiresConfirmation()
            ->modalDescription((string) __('cms.action.publish_body'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                $records->each(fn (Model $record) => Publisher::publish($record));
                Notification::make()->success()->title(trans_choice('cms.action.bulk_published', $records->count(), ['count' => $records->count()]))->send();
            });
    }

    public static function bulkUnpublish(): BulkAction
    {
        return BulkAction::make('cmsBulkUnpublish')
            ->label((string) __('cms.action.unpublish'))
            ->icon(Heroicon::OutlinedEyeSlash)
            ->requiresConfirmation()
            ->modalDescription((string) __('cms.action.unpublish_body'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                $records->each(fn (Model $record) => Publisher::unpublish($record));
                Notification::make()->success()->title(trans_choice('cms.action.bulk_unpublished', $records->count(), ['count' => $records->count()]))->send();
            });
    }

    public static function bulkDelete(): DeleteBulkAction
    {
        return DeleteBulkAction::make()->label((string) __('cms.action.delete'));
    }

    /**
     * Export the selected records as CSV (key, status, approval and every translatable text per language).
     *
     * @param  callable(Model): array<string, string|null>  $row
     */
    public static function bulkExport(string $filename, callable $row): BulkAction
    {
        return BulkAction::make('cmsBulkExport')
            ->label((string) __('cms.action.export'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function (Collection $records) use ($filename, $row): StreamedResponse {
                $rows = $records->map(fn (Model $record) => $row($record))->values()->all();

                return response()->streamDownload(function () use ($rows) {
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM: Excel opens Arabic and Russian correctly
                    if ($rows !== []) {
                        fputcsv($out, array_keys($rows[0]), escape: '');
                    }
                    foreach ($rows as $line) {
                        fputcsv($out, array_values($line), escape: '');
                    }
                    fclose($out);
                }, $filename.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    /** The record of an action group (groups get no record of their own): the action's, else the page's. */
    private static function recordOf(?Model $record, mixed $livewire): ?Model
    {
        if ($record !== null) {
            return $record;
        }

        return is_object($livewire) && method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
    }

    /** After an action on an edit page: reload the record (and the form when the working copy changed). */
    private static function refresh(mixed $livewire, bool $refill = false): void
    {
        if (is_object($livewire) && method_exists($livewire, 'refreshCmsRecord')) {
            $livewire->refreshCmsRecord($refill);
        }
    }
}
