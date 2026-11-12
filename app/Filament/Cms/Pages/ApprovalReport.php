<?php

namespace App\Filament\Cms\Pages;

use App\Content\Cms\Editing\ApprovalReport as Report;
use App\Content\Cms\Tree;
use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Cms\Support\CmsLabels;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Approval report (docs/briefs/design-2.1-and-admin-cms.md §8.7): every pending item per language, with a link to it,
 * and an export for the client (CSV: kind, type, key, item, where, en, ar, ru, admin link).
 */
class ApprovalReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'content';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 99;

    protected static ?string $slug = 'approval-report';

    protected string $view = 'filament.cms.table-page';

    /** @var list<array<string, mixed>>|null */
    private ?array $rows = null;

    public static function canAccess(): bool
    {
        return CmsAccess::can('approval_report', 'view');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('cms.approval.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms.approval.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms.approval.subheading').' '.trans_choice('cms.approval.count', $n = count($this->allRows()), ['count' => $n]);
    }

    /** @return list<array<string, mixed>> */
    private function allRows(): array
    {
        return $this->rows ??= array_map(function (array $row) {
            $row['link'] = self::editUrl($row['model']);
            unset($row['model']);

            return $row;
        }, Report::rows());
    }

    private static function editUrl(\Illuminate\Database\Eloquent\Model $record): ?string
    {
        $resource = Filament::getPanel('admin')->getModelResource($record);

        return $resource !== null && $resource::hasPage('edit') ? $resource::getUrl('edit', ['record' => $record]) : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, array $filters, int|string $page, int|string $recordsPerPage) {
                $rows = $this->filtered($search, $filters);
                $per = (int) $recordsPerPage ?: 25;

                return new LengthAwarePaginator(array_slice($rows, ((int) $page - 1) * $per, $per), count($rows), $per, (int) $page);
            })
            ->columns([
                TextColumn::make('kind')->label((string) __('cms.approval.type'))
                    ->formatStateUsing(fn (string $state) => (string) __('cms.approval.kinds.'.$state))
                    ->badge()->color('warning')->icon(Heroicon::ExclamationCircle),
                TextColumn::make('label')->label((string) __('cms.approval.item'))
                    ->description(fn (array $record) => $record['key'])->wrap()->searchable(),
                TextColumn::make('where')->label((string) __('cms.approval.where'))->extraAttributes(['dir' => 'ltr'])->toggleable(),
                ...array_map(fn (string $locale) => TextColumn::make($locale)->label(CmsLabels::locale($locale))
                    ->limit(160)->wrap()->extraAttributes(['lang' => $locale, 'dir' => $locale === 'ar' ? 'rtl' : 'ltr'])
                    ->placeholder((string) __('cms.locale.missing')), Tree::LOCALES),
            ])
            ->filters([
                SelectFilter::make('kind')->label((string) __('cms.approval.type'))
                    ->options(['record' => (string) __('cms.approval.kinds.record'), 'block' => (string) __('cms.approval.kinds.block'), 'ui' => (string) __('cms.approval.kinds.ui')]),
            ])
            ->recordActions([
                Action::make('open')->label((string) __('cms.approval.open'))->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (array $record) => $record['link'])->visible(fn (array $record) => $record['link'] !== null),
            ])
            ->headerActions([
                Action::make('export')->label((string) __('cms.approval.export'))->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(fn () => $this->export()),
            ])
            ->paginated([25, 50, 100])
            ->searchable()
            ->emptyStateHeading((string) __('cms.approval.empty'));
    }

    /** @return list<array<string, mixed>> */
    private function filtered(?string $search, array $filters): array
    {
        $kind = $filters['kind']['value'] ?? null;
        $needle = $search !== null && trim($search) !== '' ? mb_strtolower(trim($search)) : null;

        return array_values(array_filter($this->allRows(), function (array $row) use ($kind, $needle) {
            if ($kind !== null && $kind !== '' && $row['kind'] !== $kind) {
                return false;
            }
            if ($needle === null) {
                return true;
            }

            return str_contains(mb_strtolower($row['label'].' '.$row['key'].' '.$row['en'].' '.$row['ar'].' '.$row['ru']), $needle);
        }));
    }

    public function export(): StreamedResponse
    {
        $rows = $this->allRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['kind', 'type', 'key', 'item', 'where', 'en', 'ar', 'ru', 'admin'], escape: '');
            foreach ($rows as $row) {
                fputcsv($out, [$row['kind'], $row['type'], $row['key'], $row['label'], $row['where'], $row['en'], $row['ar'], $row['ru'], $row['link'] ?? ''], escape: '');
            }
            fclose($out);
        }, 'approval-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
