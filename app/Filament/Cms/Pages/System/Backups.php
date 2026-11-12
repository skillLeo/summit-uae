<?php

namespace App\Filament\Cms\Pages\System;

use App\Filament\Cms\Support\CmsAccess;
use App\Health\BackupFiles;
use App\Health\DiskSpaceCheck;
use App\Settings\BackupSettings;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * Backups (docs/briefs/design-2.1-and-admin-cms.md §12.4; Phase 7; spatie/laravel-backup, config/backup.php): the
 * backups on the private backup disk (newest first, Dubai time, size), "Run backup now" (backups.create), download
 * streamed inside the session (backups.export), delete with confirmation (backups.delete) and the daily schedule
 * (App\Settings\BackupSettings, Dubai time; backups.create). Every action is logged (App\Support\Audit).
 */
class Backups extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'system/backups';

    protected string $view = 'filament.system.backups';

    public static function canAccess(): bool
    {
        return CmsAccess::can('backups', 'view');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('system.backups.title');
    }

    public function getTitle(): string
    {
        return (string) __('system.backups.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('system.backups.subheading');
    }

    /** @return array{enabled: bool, time: string, next: ?string, location: string, count: int, total: string} */
    public function getSchedule(): array
    {
        $settings = self::settings();
        $files = BackupFiles::all();
        $next = null;
        if ($settings['enabled']) {
            [$h, $m] = array_map('intval', explode(':', $settings['time']));
            $at = now(SystemLabels::TIMEZONE)->setTime($h, $m);
            if ($at->isPast()) {
                $at->addDay();
            }
            $next = $at->translatedFormat('j M Y, H:i');
        }

        return [
            'enabled' => $settings['enabled'],
            'time' => $settings['time'],
            'next' => $next,
            'location' => 'storage/app/'.(BackupFiles::diskName() === 'local' ? 'private/' : '').BackupFiles::folder(),
            'count' => count($files),
            'total' => "\u{2066}".DiskSpaceCheck::size(array_sum(array_column($files, 'size')))."\u{2069}",
        ];
    }

    /** @return array{enabled: bool, time: string} */
    public static function settings(): array
    {
        try {
            $s = app(BackupSettings::class);

            return ['enabled' => (bool) $s->enabled, 'time' => (string) $s->time];
        } catch (Throwable) {
            return ['enabled' => true, 'time' => '02:30'];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')
                ->label((string) __('system.backups.schedule.edit'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->visible(fn (): bool => CmsAccess::can('backups', 'create'))
                ->authorize(fn (): bool => CmsAccess::can('backups', 'create'))
                ->modalHeading((string) __('system.backups.schedule.heading'))
                ->modalDescription((string) __('system.backups.schedule.description'))
                ->modalSubmitActionLabel((string) __('system.save'))
                ->modalWidth('md')
                ->fillForm(fn (): array => self::settings())
                ->schema([
                    Toggle::make('enabled')->label((string) __('system.backups.schedule.enabled')),
                    Select::make('time')->label((string) __('system.backups.schedule.time'))
                        ->helperText((string) __('system.backups.schedule.time_hint'))
                        ->options(self::times())->required()->native(false)->selectablePlaceholder(false),
                ])
                ->action(function (array $data): void {
                    self::saveSchedule((bool) ($data['enabled'] ?? false), (string) ($data['time'] ?? '02:30'));
                    Notification::make()->success()->title((string) __('system.backups.schedule.saved'))->send();
                }),
            Action::make('runBackup')
                ->label((string) __('system.backups.run'))
                ->icon(Heroicon::OutlinedPlay)
                ->visible(fn (): bool => CmsAccess::can('backups', 'create'))
                ->authorize(fn (): bool => CmsAccess::can('backups', 'create'))
                ->requiresConfirmation()
                ->modalHeading((string) __('system.backups.run_heading'))
                ->modalDescription((string) __('system.backups.run_body'))
                ->modalSubmitActionLabel((string) __('system.backups.run_confirm'))
                ->modalIcon(Heroicon::OutlinedCircleStack)
                ->action(function (): void {
                    try {
                        $file = self::run();
                        Notification::make()->success()->title((string) __('system.backups.run_done'))->body($file)->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()
                            ->title((string) __($e->getMessage() === 'busy' ? 'system.backups.run_busy' : 'system.backups.run_failed'))
                            ->body($e->getMessage() === 'busy' ? null : (string) __('system.backups.run_failed_body'))
                            ->persistent()->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(BackupFiles::all())
                ->mapWithKeys(fn (array $f): array => [$f['name'] => $f + ['__key' => $f['name'], 'date' => $f['modified']]])
                ->all())
            ->columns([
                TextColumn::make('date')
                    ->label((string) __('system.backups.column.date'))
                    ->formatStateUsing(fn ($state): string => SystemLabels::dubai(Carbon::createFromTimestamp((int) $state), 'j M Y, H:i'))
                    ->description(fn (array $record): string => Carbon::createFromTimestamp((int) $record['modified'])->diffForHumans()),
                TextColumn::make('name')
                    ->label((string) __('system.backups.column.name'))
                    ->extraAttributes(['dir' => 'ltr', 'class' => 'slc-system-mono'])
                    ->visibleFrom('md'),
                TextColumn::make('size')
                    ->label((string) __('system.backups.column.size'))
                    ->formatStateUsing(fn ($state): string => "\u{2066}".DiskSpaceCheck::size((int) $state)."\u{2069}")
                    ->alignEnd()
                    ->extraAttributes(['class' => 'slc-system-figures']),
            ])
            ->recordActions([
                Action::make('download')
                    ->label((string) __('system.backups.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (): bool => CmsAccess::can('backups', 'export'))
                    ->authorize(fn (): bool => CmsAccess::can('backups', 'export'))
                    ->action(fn (array $record): StreamedResponse => self::download((string) $record['name'])),
                Action::make('delete')
                    ->label((string) __('system.backups.delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (): bool => CmsAccess::can('backups', 'delete'))
                    ->authorize(fn (): bool => CmsAccess::can('backups', 'delete'))
                    ->requiresConfirmation()
                    ->modalHeading((string) __('system.backups.delete_heading'))
                    ->modalDescription(fn (array $record): string => (string) __('system.backups.delete_body', ['name' => $record['name']]))
                    ->action(function (array $record): void {
                        self::delete((string) $record['name']);
                        Notification::make()->success()->title((string) __('system.backups.deleted'))->send();
                    }),
            ])
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedCircleStack)
            ->emptyStateHeading((string) __('system.backups.empty'))
            ->emptyStateDescription((string) __('system.backups.empty_body'));
    }

    // ------------------------------------------------------------------ actions (also used by the tests)

    public static function run(): string
    {
        abort_unless(CmsAccess::can('backups', 'create'), 403);
        try {
            $file = BackupFiles::run();
        } catch (RuntimeException $e) {
            Audit::log('backups', 'run_failed');

            throw $e;
        }
        Audit::log('backups', 'run', null, ['file' => $file]);

        return $file;
    }

    public static function download(string $name): StreamedResponse
    {
        abort_unless(CmsAccess::can('backups', 'export'), 403);
        $file = BackupFiles::find($name);
        abort_if($file === null, 404);
        Audit::log('backups', 'download', null, ['file' => $file['name']]);

        return BackupFiles::disk()->download($file['path'], $file['name'], ['Content-Type' => 'application/zip']);
    }

    public static function delete(string $name): void
    {
        abort_unless(CmsAccess::can('backups', 'delete'), 403);
        $file = BackupFiles::find($name);
        abort_if($file === null, 404);
        BackupFiles::disk()->delete($file['path']);
        Audit::log('backups', 'delete', null, ['file' => $file['name']]);
    }

    public static function saveSchedule(bool $enabled, string $time): void
    {
        abort_unless(CmsAccess::can('backups', 'create'), 403);
        abort_unless(array_key_exists($time, self::times()), 422);
        $settings = app(BackupSettings::class);
        $before = ['enabled' => (bool) $settings->enabled, 'time' => (string) $settings->time];
        $settings->enabled = $enabled;
        $settings->time = $time;
        $settings->save();
        $after = ['enabled' => $enabled, 'time' => $time];
        if ($before !== $after) {
            activity(Audit::LOG)->event('schedule')
                ->withChanges(['attributes' => array_diff_assoc($after, $before), 'old' => array_intersect_key($before, array_diff_assoc($after, $before))])
                ->log('backups.schedule');
        }
    }

    /** @return array<string, string> every half hour, Dubai time */
    public static function times(): array
    {
        $out = [];
        for ($h = 0; $h < 24; $h++) {
            foreach ([0, 30] as $m) {
                $t = sprintf('%02d:%02d', $h, $m);
                $out[$t] = $t;
            }
        }

        return $out;
    }
}
