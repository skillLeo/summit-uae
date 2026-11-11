<?php

namespace App\Filament\Cms\RelationManagers;

use App\Content\Cms\Editing\Diff;
use App\Content\Cms\Editing\Publisher;
use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\Support\CmsLabels;
use App\Models\Revision;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The revisions of a CMS record (docs/briefs/design-2.1-and-admin-cms.md §8.5): every publish (and every import change)
 * with its time in Dubai, its author and note; "Compare" shows the field-by-field difference against the current
 * version, "Restore as draft" makes it the record's new draft (nothing changes on the site until it is published).
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    /** Rendered with the page (not when scrolled into view), so the history is always there. */
    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return (string) __('cms.revision.heading');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Revision $record) => CmsLabels::dubai($record->created_at))
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('created_at')->label((string) __('cms.revision.created_at'))
                    ->formatStateUsing(fn ($state) => CmsLabels::dubai($state)),
                TextColumn::make('user.name')->label((string) __('cms.revision.user'))->placeholder((string) __('cms.field.nobody')),
                TextColumn::make('note')->label((string) __('cms.revision.note'))
                    ->formatStateUsing(fn (?string $state) => self::note($state))->wrap(),
            ])
            ->recordActions([
                Action::make('compare')
                    ->label((string) __('cms.action.compare'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->modalHeading((string) __('cms.revision.diff_heading'))
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Revision $record) => view('filament.cms.revision-diff', [
                        'rows' => Diff::rows(self::snapshot($this->getOwnerRecord(), $record), Publisher::workingCopy($this->getOwnerRecord())),
                    ])),
                Action::make('restoreRevision')
                    ->label((string) __('cms.action.restore_revision'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->requiresConfirmation()
                    ->modalDescription((string) __('cms.action.restore_revision_body'))
                    ->action(function (Revision $record) {
                        Publisher::restore($this->getOwnerRecord(), $record);
                        Notification::make()->success()->title((string) __('cms.action.revision_restored'))->send();
                        $this->dispatch('cms-revision-restored');
                    }),
            ])
            ->emptyStateHeading((string) __('cms.revision.empty'));
    }

    /** @return array<string, mixed> the revision's values of the editable columns (import snapshots are whole rows) */
    public static function snapshot(Model $owner, Revision $revision): array
    {
        $snapshot = array_intersect_key((array) $revision->snapshot, Registry::fields($owner));

        return Publisher::normalize($owner, $snapshot);
    }

    private static function note(?string $note): string
    {
        return match (true) {
            $note === null || $note === '' => '',
            $note === 'published' => (string) __('cms.revision.notes.published'),
            $note === 'scheduled' => (string) __('cms.revision.notes.scheduled'),
            str_starts_with($note, 'summit:content-import') => (string) __('cms.revision.notes.imported'),
            default => $note,
        };
    }
}
