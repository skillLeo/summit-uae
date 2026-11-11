<?php

namespace App\Filament\Cms\Pages\Settings;

use App\Filament\Cms\Pages\Settings\Concerns\EditsSettings;
use App\Settings\OfficeHoursSettings;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Office Hours (docs/briefs/design-2.1-and-admin-cms.md §12.3; App\Settings\OfficeHoursSettings): the weekly schedule in
 * Asia/Dubai (ISO weekdays, 1 = Monday), the opening and closing time, closure dates, the proposed live status ("Open
 * now, closes at 6 pm") on or off, and the internal review target in business hours, which is never shown on the site.
 *
 * Design 2 reads the schedule and the live-status switch (App\Design\D2\Hours: the #d2-hours-copy payload of every
 * page). The approved hours SENTENCES ("Monday to Friday, 9 am to 6 pm") are page copy from the DOCX, edited on the
 * Home and Contact pages, not here: a changed schedule should be matched there.
 */
class OfficeHours extends SettingsPage
{
    use EditsSettings;

    protected static string $settings = OfficeHoursSettings::class;

    protected static ?string $slug = 'settings/office-hours';

    protected static string|UnitEnum|null $navigationGroup = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return (string) __('cms_site.hours.title');
    }

    public function getTitle(): string
    {
        return (string) __('cms_site.hours.title');
    }

    public function getSubheading(): ?string
    {
        return (string) __('cms_site.hours.subheading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make((string) __('cms_site.hours.schedule'))
                ->description((string) __('cms_site.hours.schedule_hint'))
                ->schema([
                    TextInput::make('timezone')->label((string) __('cms_site.hours.timezone'))
                        ->helperText((string) __('cms_site.hours.timezone_hint'))
                        ->disabled()->dehydrated(false)->extraInputAttributes(['dir' => 'ltr']),
                    CheckboxList::make('days')->label((string) __('cms_site.hours.days'))
                        ->options(self::dayOptions())
                        ->columns(['default' => 2, 'sm' => 4, 'lg' => 7])
                        ->required()
                        ->in(array_map('strval', range(1, 7))),
                    Grid::make(['default' => 1, 'sm' => 2])->schema([
                        TimePicker::make('open')->label((string) __('cms_site.hours.open'))
                            ->seconds(false)->format('H:i')->displayFormat('H:i')->required()->extraInputAttributes(['dir' => 'ltr']),
                        TimePicker::make('close')->label((string) __('cms_site.hours.close'))
                            ->seconds(false)->format('H:i')->displayFormat('H:i')->required()->extraInputAttributes(['dir' => 'ltr'])
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $open = substr((string) $get('open'), 0, 5);
                                if (is_string($value) && $open !== '' && substr($value, 0, 5) <= $open) {
                                    $fail((string) __('cms_site.hours.errors.close_before_open'));
                                }
                            }),
                    ]),
                ]),
            Section::make((string) __('cms_site.hours.closures'))
                ->description((string) __('cms_site.hours.closures_hint'))
                ->schema([
                    Repeater::make('closures')->hiddenLabel()
                        ->simple(DatePicker::make('date')->native(false)->format('Y-m-d')->displayFormat('j M Y')->required()->distinct())
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->addActionLabel((string) __('cms_site.hours.add_closure')),
                ]),
            Section::make((string) __('cms_site.hours.status'))
                ->schema([
                    Toggle::make('live_status')->label((string) __('cms_site.hours.live_status'))
                        ->helperText((string) __('cms_site.hours.live_status_hint')),
                    TextInput::make('review_target_hours')->label((string) __('cms_site.hours.review_target'))
                        ->helperText((string) __('cms_site.hours.review_target_hint'))
                        ->numeric()->integer()->minValue(1)->maxValue(500)->suffix((string) __('cms_site.hours.hours_suffix'))
                        ->extraInputAttributes(['dir' => 'ltr']),
                ]),
        ]);
    }

    /** @return array<string, string> ISO weekday => its name in the admin's language */
    public static function dayOptions(): array
    {
        $monday = CarbonImmutable::parse('2026-11-09')->locale(app()->getLocale());
        $out = [];
        foreach (range(1, 7) as $day) {
            $out[(string) $day] = $monday->addDays($day - 1)->translatedFormat('l');
        }

        return $out;
    }

    protected function settingsToForm(array $data): array
    {
        $data['days'] = array_map('strval', (array) ($data['days'] ?? []));
        $data['closures'] = array_values((array) ($data['closures'] ?? []));

        return $data;
    }

    /** As stored today: ISO days as sorted integers, "HH:MM", closure dates sorted, the time zone unchanged. */
    protected function formToSettings(array $data): array
    {
        $days = array_values(array_unique(array_map('intval', (array) ($data['days'] ?? []))));
        sort($days);
        $closures = array_values(array_unique(array_filter(array_map(fn ($d) => is_string($d) ? substr($d, 0, 10) : null, (array) ($data['closures'] ?? [])))));
        sort($closures);
        $target = $data['review_target_hours'] ?? null;

        return [
            'timezone' => (string) app(OfficeHoursSettings::class)->timezone,
            'days' => $days,
            'open' => substr((string) $data['open'], 0, 5),
            'close' => substr((string) $data['close'], 0, 5),
            'closures' => $closures,
            'live_status' => (bool) ($data['live_status'] ?? false),
            'review_target_hours' => $target === null || $target === '' ? null : (int) $target,
        ];
    }
}
