<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A reporting period (docs/briefs/design-2.1-and-admin-cms.md §11.2, §11.3): [start, end) in Asia/Dubai, compared with
 * the period of the same length right before it. Data is stored in UTC; queries use utcStart() / utcEnd().
 *
 * Presets: today, 7d, 30d, 90d (each ending at the end of today, Dubai), this_month, last_month, this_quarter, ytd,
 * custom (from and to are Dubai dates, both included).
 */
final class Period
{
    public const PRESETS = ['today', '7d', '30d', '90d', 'this_month', 'last_month', 'this_quarter', 'ytd', 'custom'];

    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $preset = 'custom',
    ) {
        if ($end <= $start) {
            throw new InvalidArgumentException('A period must end after it starts.');
        }
    }

    public static function zone(): string
    {
        return (string) config('summit.contact.timezone', 'Asia/Dubai');
    }

    public static function make(string $preset, ?string $from = null, ?string $to = null, ?CarbonImmutable $now = null): self
    {
        $zone = self::zone();
        $now = ($now ?? CarbonImmutable::now())->setTimezone($zone);
        $tomorrow = $now->startOfDay()->addDay();

        return match ($preset) {
            'today' => new self($now->startOfDay(), $tomorrow, $preset),
            '7d' => new self($tomorrow->subDays(7), $tomorrow, $preset),
            '30d' => new self($tomorrow->subDays(30), $tomorrow, $preset),
            '90d' => new self($tomorrow->subDays(90), $tomorrow, $preset),
            'this_month' => new self($now->startOfMonth(), $now->startOfMonth()->addMonth(), $preset),
            'last_month' => new self($now->startOfMonth()->subMonth(), $now->startOfMonth(), $preset),
            'this_quarter' => new self($now->firstOfQuarter()->startOfDay(), $now->firstOfQuarter()->startOfDay()->addMonths(3), $preset),
            'ytd' => new self($now->startOfYear(), $tomorrow, $preset),
            'custom' => self::custom($from, $to, $zone, $now),
            default => throw new InvalidArgumentException("Unknown period [{$preset}]."),
        };
    }

    private static function custom(?string $from, ?string $to, string $zone, CarbonImmutable $now): self
    {
        $start = $from ? CarbonImmutable::parse(substr($from, 0, 10), $zone)->startOfDay() : $now->startOfDay()->subDays(29);
        $end = ($to ? CarbonImmutable::parse(substr($to, 0, 10), $zone)->startOfDay() : $now->startOfDay())->addDay();
        if ($end <= $start) {
            [$start, $end] = [$end->subDay(), $start->addDay()];
        }

        return new self($start, $end, 'custom');
    }

    /** The period of the same length that ends where this one starts. */
    public function previous(): self
    {
        if (in_array($this->preset, ['this_month', 'last_month'], true)) {
            return new self($this->start->subMonth(), $this->start, $this->preset);
        }
        if ($this->preset === 'this_quarter') {
            return new self($this->start->subMonths(3), $this->start, $this->preset);
        }
        if ($this->preset === 'ytd') {
            return new self($this->start->subYear(), $this->end->subYear(), $this->preset);
        }
        $seconds = $this->end->getTimestamp() - $this->start->getTimestamp();

        return new self($this->start->subSeconds($seconds), $this->start, $this->preset);
    }

    public function utcStart(): CarbonImmutable
    {
        return $this->start->utc();
    }

    public function utcEnd(): CarbonImmutable
    {
        return $this->end->utc();
    }

    public function days(): int
    {
        return (int) round(($this->end->getTimestamp() - $this->start->getTimestamp()) / 86400);
    }

    /** Columns per day up to 31 days, per ISO week beyond (§11.2 Volume). */
    public function bucket(): string
    {
        return $this->days() <= 31 ? 'day' : 'week';
    }

    /** @return list<CarbonImmutable> the bucket starts (Dubai), oldest first */
    public function buckets(): array
    {
        $out = [];
        if ($this->bucket() === 'day') {
            for ($d = $this->start; $d < $this->end; $d = $d->addDay()) {
                $out[] = $d;
            }

            return $out;
        }
        for ($d = $this->start->startOfWeek(\Carbon\CarbonInterface::MONDAY); $d < $this->end; $d = $d->addWeek()) {
            $out[] = $d;
        }

        return $out;
    }

    public function contains(CarbonImmutable $at): bool
    {
        return $at >= $this->start && $at < $this->end;
    }
}
