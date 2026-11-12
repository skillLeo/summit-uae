<?php

namespace App\Filament\Cms\Pages\System;

use App\Filament\Cms\Support\CmsAccess;
use App\Health\Outcome;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;
use Throwable;
use UnitEnum;

/**
 * Health (docs/briefs/design-2.1-and-admin-cms.md §12.4; Phase 7; spatie/laravel-health, config/health.php): database,
 * cache, queue, scheduler, mail, disk, last backup and debug mode (App\Health\*), each with a status badge (icon and
 * label), the message and the time of the run (Dubai). "Run checks" runs them now; the scheduler runs them every five
 * minutes. The first visit runs them when nothing is stored yet.
 */
class Health extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = 'system';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 70;

    protected static ?string $slug = 'system/health';

    protected string $view = 'filament.system.health';

    /** The order of the screen (the registered order of App\Providers\SystemServiceProvider). */
    public const CHECKS = ['database', 'cache', 'queue', 'schedule', 'mail', 'disk', 'backup', 'debug'];

    public static function canAccess(): bool
    {
        return CmsAccess::can('health', 'view');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('system.health.title');
    }

    public function getTitle(): string
    {
        return (string) __('system.health.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('system.health.subheading');
    }

    public function mount(): void
    {
        if (self::latest() === null) {
            self::runChecks();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runChecks')
                ->label((string) __('system.health.run'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    self::runChecks();
                    $this->resetTable();
                    Notification::make()->success()->title((string) __('system.health.ran'))->send();
                }),
        ];
    }

    public static function runChecks(): void
    {
        abort_unless(CmsAccess::can('health', 'view'), 403);
        try {
            Artisan::call('health:check', ['--no-notification' => true]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function latest(): ?StoredCheckResults
    {
        try {
            foreach (app(\Spatie\Health\Health::class)->resultStores() as $store) {
                /** @var ResultStore $store */
                $results = $store->latestResults();
                if ($results !== null) {
                    return $results;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public function getCheckedAt(): ?string
    {
        $latest = self::latest();

        return $latest === null ? null : SystemLabels::dubai($latest->finishedAt, 'j M Y, H:i:s');
    }

    /** @return list<array{key: string, label: string, status: string, message: string, meta: array<string, mixed>, fallback: string}> */
    public static function rows(): array
    {
        $latest = self::latest();
        if ($latest === null) {
            return [];
        }
        $rows = [];
        foreach ($latest->storedCheckResults as $result) {
            /** @var StoredCheckResult $result */
            $name = (string) $result->name;
            $rows[$name] = [
                'key' => $name,
                'label' => self::label($name, (string) $result->label),
                'status' => in_array($result->status, ['ok', 'warning', 'failed', 'crashed', 'skipped'], true) ? $result->status : 'failed',
                'message' => $result->status === 'crashed'
                    ? (string) __('system.health.message.crashed')
                    : Outcome::message($result->meta, (string) $result->notificationMessage),
                'meta' => $result->status === 'crashed' ? ['message' => 'crashed'] : $result->meta,
                'fallback' => (string) $result->notificationMessage,
            ];
        }
        $order = array_flip(self::CHECKS);
        uksort($rows, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        return array_values($rows);
    }

    public static function label(string $name, string $fallback = ''): string
    {
        $key = 'system.health.check.'.$name;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : $fallback;
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'ok' => 'success',
            'warning' => 'warning',
            'skipped' => 'gray',
            default => 'danger',
        };
    }

    public static function statusIcon(string $status): Heroicon
    {
        return match ($status) {
            'ok' => Heroicon::CheckCircle,
            'warning' => Heroicon::ExclamationTriangle,
            'skipped' => Heroicon::MinusCircle,
            default => Heroicon::XCircle,
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(self::rows())->mapWithKeys(fn (array $r): array => [$r['key'] => $r + ['__key' => $r['key']]])->all())
            ->columns([
                TextColumn::make('label')
                    ->label((string) __('system.health.column.check'))
                    ->weight('medium')
                    ->wrap()
                    // on phones the result sits under the check's name (the Result column shows from md up)
                    ->description(fn (array $record): HtmlString => new HtmlString('<span class="slc-system-below-md">'.Outcome::html($record['meta'], $record['fallback'])->toHtml().'</span>')),
                TextColumn::make('status')
                    ->label((string) __('system.health.column.status'))
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->icon(fn (string $state): Heroicon => self::statusIcon($state))
                    ->formatStateUsing(fn (string $state): string => (string) __('system.health.status.'.$state)),
                TextColumn::make('message')
                    ->label((string) __('system.health.column.message'))
                    ->formatStateUsing(fn ($state, array $record): HtmlString => Outcome::html($record['meta'], $record['fallback']))
                    ->html()
                    ->visibleFrom('md')
                    ->wrap(),
            ])
            ->paginated(false)
            ->emptyStateHeading((string) __('system.health.empty'))
            ->emptyStateDescription((string) __('system.health.empty_body'));
    }
}
