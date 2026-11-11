<?php

namespace App\Filament\Resources\InterfaceText\Pages;

use App\Filament\Resources\InterfaceText\InterfaceTextCsv;
use App\Filament\Resources\InterfaceText\InterfaceTextResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The interface-text list with the CSV round trip (InterfaceTextCsv): export every string or the ones the current
 * search and filters show; import a translated file as drafts (or import and publish), with a report of what changed
 * and what was rejected.
 */
class ListInterfaceText extends ListRecords
{
    protected static string $resource = InterfaceTextResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.ui.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('exportFiltered')
                    ->label((string) __('cms_site.ui.export_filtered'))
                    ->action(fn (): StreamedResponse => $this->download(filtered: true)),
                Action::make('exportAll')
                    ->label((string) __('cms_site.ui.export_all'))
                    ->action(fn (): StreamedResponse => $this->download(filtered: false)),
            ])->label((string) __('cms_site.ui.export'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')->button(),
            Action::make('importCsv')
                ->label((string) __('cms_site.ui.import'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->modalHeading((string) __('cms_site.ui.import_heading'))
                ->modalDescription((string) __('cms_site.ui.import_body'))
                ->modalSubmitActionLabel((string) __('cms_site.ui.import'))
                ->schema([
                    FileUpload::make('file')
                        ->label((string) __('cms_site.ui.import_file'))
                        ->storeFiles(false)
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->maxSize(4096)
                        ->required(),
                    Toggle::make('publish')
                        ->label((string) __('cms_site.ui.import_publish'))
                        ->helperText((string) __('cms_site.ui.import_publish_hint'))
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;
                    $csv = $file instanceof TemporaryUploadedFile ? (string) $file->get() : (is_string($file) ? $file : '');
                    $this->notifyImport(InterfaceTextCsv::import($csv, (bool) ($data['publish'] ?? false)));
                }),
        ];
    }

    private function download(bool $filtered): StreamedResponse
    {
        $query = $filtered ? $this->getFilteredTableQuery() : null;
        $csv = InterfaceTextCsv::export($query);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'interface-text-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param array{updated: int, published: int, unchanged: int, rejected: list<array{line: int, key: string, reason: string}>, error: string|null} $report */
    public function notifyImport(array $report): void
    {
        if ($report['error'] !== null) {
            Notification::make()->danger()->persistent()
                ->title((string) __('cms_site.ui.import_failed'))
                ->body((string) __('cms_site.ui.import_errors.'.$report['error']))
                ->send();

            return;
        }
        $lines = [
            trans_choice($report['published'] > 0 ? 'cms_site.ui.import_published' : 'cms_site.ui.import_updated', $report['updated'], ['count' => $report['updated']]),
            trans_choice('cms_site.ui.import_unchanged', $report['unchanged'], ['count' => $report['unchanged']]),
        ];
        $rejected = $report['rejected'];
        if ($rejected !== []) {
            $lines[] = trans_choice('cms_site.ui.import_rejected', count($rejected), ['count' => count($rejected)]);
            foreach (array_slice($rejected, 0, 8) as $row) {
                $lines[] = (string) __('cms_site.ui.import_row', [
                    'line' => $row['line'],
                    'key' => $row['key'],
                    'reason' => (string) __('cms_site.ui.import_reasons.'.$row['reason']),
                ]);
            }
            if (count($rejected) > 8) {
                $lines[] = (string) __('cms_site.ui.import_more', ['count' => count($rejected) - 8]);
            }
        }
        $notification = Notification::make()
            ->title((string) __('cms_site.ui.import_done'))
            ->body(implode('<br>', array_map('e', $lines)));
        ($rejected !== [] ? $notification->warning()->persistent() : $notification->success())->send();
        $this->resetTable();
    }
}
