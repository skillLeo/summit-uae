<?php

namespace App\Requests;

use App\Settings\OfficeHoursSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Business hours between two instants (docs/briefs/design-2.1-and-admin-cms.md §11.3 "Time to first review", §10.3
 * "Waiting"), from the office-hours setting: the working weekdays (ISO, 1 = Monday), one opening and closing time,
 * and the closure dates, all in the office time zone (Asia/Dubai). Weekends and closures count 0; a working day counts
 * the part of [open, close) that falls inside the interval. Times are stored in UTC and converted here.
 */
final class BusinessHours
{
    /** @var array<int, true> */
    private array $days;

    /** @var array<string, true> Y-m-d */
    private array $closures;

    private int $openMinutes;

    private int $closeMinutes;

    /**
     * @param  list<int>  $days
     * @param  list<string>  $closures
     */
    public function __construct(
        array $days = [1, 2, 3, 4, 5],
        string $open = '09:00',
        string $close = '18:00',
        array $closures = [],
        private readonly string $timezone = 'Asia/Dubai',
    ) {
        $this->days = array_fill_keys(array_map('intval', $days), true);
        $this->closures = array_fill_keys(array_map(fn ($d) => substr((string) $d, 0, 10), $closures), true);
        $this->openMinutes = self::minutes($open);
        $this->closeMinutes = max($this->openMinutes, self::minutes($close));
    }

    /** From the Office Hours settings; the default week (Monday to Friday, 9:00 to 18:00) if they cannot be read. */
    public static function fromSettings(): self
    {
        try {
            $s = app(OfficeHoursSettings::class);

            return new self((array) $s->days, (string) $s->open, (string) $s->close, (array) $s->closures, (string) ($s->timezone ?: 'Asia/Dubai'));
        } catch (Throwable) {
            return new self;
        }
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /** Working hours per working day (9 for 9:00 to 18:00). */
    public function hoursPerDay(): float
    {
        return ($this->closeMinutes - $this->openMinutes) / 60;
    }

    /** Whether this local date is a working day (a working weekday that is not a closure). */
    public function isWorkingDay(CarbonInterface $day): bool
    {
        $local = CarbonImmutable::instance($day)->setTimezone($this->timezone);

        return isset($this->days[$local->dayOfWeekIso]) && ! isset($this->closures[$local->format('Y-m-d')]);
    }

    /** Business hours in [from, to); 0 when to is not after from. */
    public function between(CarbonInterface $from, CarbonInterface $to): float
    {
        $start = CarbonImmutable::instance($from)->setTimezone($this->timezone);
        $end = CarbonImmutable::instance($to)->setTimezone($this->timezone);
        if ($end <= $start || $this->closeMinutes === $this->openMinutes) {
            return 0.0;
        }

        $seconds = 0;
        for ($day = $start->startOfDay(); $day < $end; $day = $day->addDay()) {
            if (! $this->isWorkingDay($day)) {
                continue;
            }
            // wall-clock opening and closing of this local day (Dubai has no daylight saving; other zones are honoured)
            $open = $day->setTime(intdiv($this->openMinutes, 60), $this->openMinutes % 60);
            $close = $day->setTime(intdiv($this->closeMinutes, 60), $this->closeMinutes % 60);
            $a = $start > $open ? $start : $open;
            $b = $end < $close ? $end : $close;
            if ($b > $a) {
                $seconds += $b->getTimestamp() - $a->getTimestamp();
            }
        }

        return $seconds / 3600;
    }

    /** Calendar hours in [from, to) (shown in tooltips next to business hours). */
    public static function calendar(CarbonInterface $from, CarbonInterface $to): float
    {
        return max(0, $to->getTimestamp() - $from->getTimestamp()) / 3600;
    }

    private static function minutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return max(0, min(24 * 60, ((int) $h) * 60 + (int) $m));
    }
}
