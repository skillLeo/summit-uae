<?php

namespace App\Filament\Cms\Pages;

use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Cms\Support\CmsLabels;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Trash (docs/briefs/design-2.1-and-admin-cms.md §8.5, §12.4): every CMS record moved to the Trash, by type. Restore puts
 * it back with its status; permanent delete (with its revisions) is for the super admin only (CmsAccess, Phase 7 hook).
 */
class Trash extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'trash';

    protected string $view = 'filament.cms.table-page';

    public string $type = 'guide';

    public static function canAccess(): bool
    {
        return CmsAccess::can('trash', 'view');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('cms.trash.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms.trash.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms.trash.subheading');
    }

    /** @return array<string, string> type => label, the types that have something in the Trash first */
    public static function types(): array
    {
        $out = [];
        foreach (Registry::models() as $type => $class) {
            $resource = \Filament\Facades\Filament::getPanel('admin')->getModelResource($class);
            $label = $resource !== null ? $resource::getPluralModelLabel() : Str::headline($type);
            $count = $class::onlyTrashed()->count();
            $out[$type] = $label.($count > 0 ? ' ('.$count.')' : '');
        }

        return $out;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->modelClass()::onlyTrashed())
            ->columns([
                TextColumn::make('key')->label((string) __('cms.field.code'))->state(fn (Model $record) => (string) $record->getKey())->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('record')->label((string) __('cms.trash.record'))->state(fn (Model $record) => Registry::label($record))->wrap(),
                TextColumn::make('deleted_at')->label((string) __('cms.trash.deleted_at'))->formatStateUsing(fn ($state) => CmsLabels::dubai($state))->sortable(),
                TextColumn::make('updated_by')->label((string) __('cms.field.updated_by'))->state(fn (Model $record) => optional($record->updatedBy)->name),
            ])
            ->filters([
                SelectFilter::make('type')->label((string) __('cms.trash.type'))
                    ->options(fn () => self::types())
                    ->default('guide')
                    ->selectablePlaceholder(false)
                    ->query(fn ($query) => $query),
            ])
            ->recordActions([
                Action::make('restore')->label((string) __('cms.action.restore'))->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->authorize(fn () => CmsAccess::can('trash', 'restore'))
                    ->action(function (Model $record) {
                        $record->restore();
                        Notification::make()->success()->title((string) __('cms.action.restored'))->send();
                    }),
                Action::make('forceDelete')->label((string) __('cms.action.force_delete'))->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->requiresConfirmation()->modalDescription((string) __('cms.action.force_delete_body'))
                    ->visible(fn () => CmsAccess::can('trash', 'force_delete'))
                    ->authorize(fn () => CmsAccess::can('trash', 'force_delete'))
                    ->action(function (Model $record) {
                        self::forceDelete($record);
                        Notification::make()->success()->title((string) __('cms.action.force_deleted'))->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('restoreMany')->label((string) __('cms.action.restore'))->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->authorize(fn () => CmsAccess::can('trash', 'restore'))
                    ->action(fn (Collection $records) => $records->each->restore()),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->defaultSort('deleted_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading((string) __('cms.trash.empty'));
    }

    /** Permanent delete: super admin only. Revisions go with the record. */
    public static function forceDelete(Model $record): void
    {
        abort_unless(CmsAccess::can('trash', 'force_delete'), 403);
        $record->revisions()->delete();
        $record->forceDelete();
    }

    /** @return class-string<Model> */
    private function modelClass(): string
    {
        $type = $this->tableFilters['type']['value'] ?? $this->type;

        return Registry::models()[$type] ?? Registry::models()['guide'];
    }
}
