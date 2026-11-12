<?php

namespace App\Reports;

use App\Models\Enquiry;
use App\Requests\BusinessHours;
use App\Requests\ReviewTarget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The KPI definitions of docs/briefs/design-2.1-and-admin-cms.md §11.3, the only place figures are computed (the
 * dashboard and the reports both read them; tests/Feature/Reports/KpiTest checks every formula against fixtures).
 *
 * - Periods are [start, end) in Asia/Dubai; data is stored in UTC (App\Reports\Period).
 * - Spam is excluded everywhere; demo records are excluded in production (Enquiry::scopeCounted). The spam rate is
 *   the one figure that counts spam, as its numerator.
 * - Received(P): received_at in P. Change: Received(P) minus Received(previous period of the same length), always as
 *   an absolute number; a percentage only when the previous value is 10 or more, else "small base" or, at 0, "no
 *   earlier data".
 * - Waiting now: status New. Oldest wait: now minus the earliest received_at among them.
 * - TTFR: first_reviewed_at minus received_at in business hours (office hours: weekdays and closures). Median and P90
 *   over the requests received in P that have been reviewed, with n and the number still unreviewed. Percentiles are
 *   linear between the closest ranks (Excel PERCENTILE.INC): the median of an even count is the mean of the middle two.
 * - Within target %: of the requests received in P, the reviewed ones within the internal target, divided by those
 *   plus the missed ones; unreviewed requests already past the target count as missed, unreviewed ones still inside it
 *   are left out.
 * - Closed(P): closed_at in P. Engagement rate: Engaged / closed with an outcome in P, hidden below n = 5.
 * - Spam rate: spam / all submissions received in P.
 * - Shares: count per category / Received(P); an empty value counts as "Not chosen".
 *
 * Filters (§11.2): matter type, site language, preferred language, status, assignee, source page; "" = not chosen.
 */
final class Kpi
{
    public const FILTERS = ['matter_type', 'locale', 'preferred_language', 'status', 'assignee_id', 'source_page'];

    public const SMALL_BASE = 10;

    public const MIN_ENGAGEMENT_N = 5;

    /** Time to first review buckets in business hours (§11.2 Workflow); "1 to 2 business days" ends at 2 working days. */
    public const HISTOGRAM = ['lt1', '1to4', '4to8', '1to2d', 'gt2d'];

    private readonly BusinessHours $hours;

    private readonly CarbonImmutable $now;

    /** @param  array<string, mixed>  $filters */
    public function __construct(private readonly array $filters = [], ?BusinessHours $hours = null, ?CarbonImmutable $now = null)
    {
        $this->hours = $hours ?? BusinessHours::fromSettings();
        $this->now = $now ?? CarbonImmutable::now();
    }

    /** @param  array<string, mixed>  $filters */
    public function withFilters(array $filters): self
    {
        return new self($filters, $this->hours, $this->now);
    }

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function hours(): BusinessHours
    {
        return $this->hours;
    }

    // ------------------------------------------------------------------ queries

    /** @return Builder<Enquiry> counted requests (no spam; no demo in production) with the filters */
    public function query(): Builder
    {
        return $this->filtered(Enquiry::query()->counted());
    }

    /** @return Builder<Enquiry> every submission (spam included; no demo in production) with the filters */
    public function submissions(): Builder
    {
        $query = Enquiry::query();
        if (app()->isProduction()) {
            $query->where('is_demo', false);
        }

        return $this->filtered($query, withStatus: false);
    }

    /**
     * @param  Builder<Enquiry>  $query
     * @return Builder<Enquiry>
     */
    private function filtered(Builder $query, bool $withStatus = true): Builder
    {
        foreach (self::FILTERS as $column) {
            if (! $withStatus && $column === 'status') {
                continue;
            }
            $value = $this->filters[$column] ?? null;
            if ($value === null || $value === [] ) {
                continue;
            }
            $values = array_values((array) $value);
            $query->where(function (Builder $q) use ($column, $values): void {
                $real = array_values(array_filter($values, fn ($v) => $v !== '' && $v !== null));
                if ($real !== []) {
                    $q->whereIn($column, $real);
                }
                if (in_array('', $values, true)) {
                    $q->orWhereNull($column)->orWhere($column, '');
                }
            });
        }

        return $query;
    }

    /** @return Builder<Enquiry> */
    public function receivedIn(Period $p): Builder
    {
        return $this->query()->where('received_at', '>=', $p->utcStart())->where('received_at', '<', $p->utcEnd());
    }

    // ------------------------------------------------------------------ volume

    public function received(Period $p): int
    {
        return $this->receivedIn($p)->count();
    }

    /** @return array{current: int, previous: int, change: int, percent: float|null, note: string|null} */
    public function change(Period $p): array
    {
        $current = $this->received($p);
        $previous = $this->received($p->previous());

        return self::compare($current, $previous);
    }

    /** @return array{current: int, previous: int, change: int, percent: float|null, note: string|null} */
    public static function compare(int $current, int $previous): array
    {
        $note = null;
        $percent = null;
        if ($previous >= self::SMALL_BASE) {
            $percent = round(($current - $previous) / $previous * 100, 1);
        } elseif ($previous === 0) {
            $note = 'no_earlier_data';
        } else {
            $note = 'small_base';
        }

        return ['current' => $current, 'previous' => $previous, 'change' => $current - $previous, 'percent' => $percent, 'note' => $note];
    }

    /** @return array{count: int, oldest_hours: float|null, oldest_business_hours: float|null, oldest_received_at: CarbonImmutable|null} */
    public function waitingNow(): array
    {
        $query = $this->query()->where('status', Enquiry::STATUS_NEW);
        $count = (clone $query)->count();
        $oldest = (clone $query)->min('received_at');
        $at = $oldest === null ? null : CarbonImmutable::parse($oldest, 'UTC');

        return [
            'count' => $count,
            'oldest_received_at' => $at,
            'oldest_hours' => $at === null ? null : round(BusinessHours::calendar($at, $this->now), 1),
            'oldest_business_hours' => $at === null ? null : round($this->hours->between($at, $this->now), 1),
        ];
    }

    /** @return array<string, int> Dubai date (Y-m-d, or the Monday of the week) => count, every bucket present */
    public function series(Period $p): array
    {
        $out = [];
        foreach ($p->buckets() as $start) {
            $out[$start->format('Y-m-d')] = 0;
        }
        $weekly = $p->bucket() === 'week';
        foreach ($this->receivedIn($p)->pluck('received_at') as $at) {
            $local = CarbonImmutable::parse($at)->setTimezone(Period::zone());
            $key = ($weekly ? $local->startOfWeek(\Carbon\CarbonInterface::MONDAY) : $local)->format('Y-m-d');
            if (array_key_exists($key, $out)) {
                $out[$key]++;
            }
        }

        return $out;
    }

    /** @return array<int, array<int, int>> ISO weekday (1-7) => hour (0-23) => count, Dubai time */
    public function heatmap(Period $p): array
    {
        $grid = [];
        foreach (range(1, 7) as $d) {
            $grid[$d] = array_fill(0, 24, 0);
        }
        foreach ($this->receivedIn($p)->pluck('received_at') as $at) {
            $local = CarbonImmutable::parse($at)->setTimezone(Period::zone());
            $grid[$local->dayOfWeekIso][(int) $local->format('G')]++;
        }

        return $grid;
    }

    // ------------------------------------------------------------------ time to first review

    /** @return list<float> business hours to the first review of every reviewed request received in P */
    public function ttfrValues(Period $p): array
    {
        $out = [];
        foreach ($this->receivedIn($p)->whereNotNull('first_reviewed_at')->get(['received_at', 'first_reviewed_at']) as $e) {
            $out[] = $this->hours->between($e->received_at, $e->first_reviewed_at);
        }

        return $out;
    }

    /** @return array{median: float|null, p90: float|null, n: int, unreviewed: int} */
    public function ttfr(Period $p): array
    {
        $values = $this->ttfrValues($p);

        return [
            'median' => self::percentile($values, 50),
            'p90' => self::percentile($values, 90),
            'n' => count($values),
            'unreviewed' => $this->receivedIn($p)->whereNull('first_reviewed_at')->count(),
        ];
    }

    /** Linear interpolation between closest ranks (PERCENTILE.INC); null for no values. */
    public static function percentile(array $values, float $percent): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $rank = ($percent / 100) * (count($values) - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        $value = $values[$low] + ($values[$high] - $values[$low]) * ($rank - $low);

        return round($value, 2);
    }

    /** @return array<string, int> HISTOGRAM bucket => count */
    public function ttfrHistogram(Period $p): array
    {
        $day = max(1.0, $this->hours->hoursPerDay());
        $out = array_fill_keys(self::HISTOGRAM, 0);
        foreach ($this->ttfrValues($p) as $h) {
            $key = match (true) {
                $h < 1 => 'lt1',
                $h < 4 => '1to4',
                $h < 8 => '4to8',
                $h <= 2 * $day => '1to2d',
                default => 'gt2d',
            };
            $out[$key]++;
        }

        return $out;
    }

    /** @return array{share: float|null, met: int, missed: int, excluded: int, target: int} */
    public function withinTarget(Period $p, ?int $target = null): array
    {
        $target ??= ReviewTarget::hours();
        $met = $missed = $excluded = 0;
        foreach ($this->receivedIn($p)->get(['received_at', 'first_reviewed_at']) as $e) {
            $end = $e->first_reviewed_at ?? $this->now;
            $hours = $this->hours->between($e->received_at, $end);
            if ($e->first_reviewed_at !== null) {
                $hours <= $target ? $met++ : $missed++;
            } elseif ($hours > $target) {
                $missed++;
            } else {
                $excluded++;
            }
        }
        $n = $met + $missed;

        return ['share' => $n === 0 ? null : round($met / $n * 100, 1), 'met' => $met, 'missed' => $missed, 'excluded' => $excluded, 'target' => $target];
    }

    /** @return array<string, float|null> bucket start => share within target */
    public function withinTargetTrend(Period $p): array
    {
        $out = [];
        $buckets = $p->buckets();
        foreach ($buckets as $i => $start) {
            $end = $buckets[$i + 1] ?? $p->end;
            $from = $start < $p->start ? $p->start : $start;
            $out[$start->format('Y-m-d')] = $this->withinTarget(new Period($from, $end))['share'];
        }

        return $out;
    }

    // ------------------------------------------------------------------ outcomes

    /** @return Builder<Enquiry> */
    public function closedIn(Period $p): Builder
    {
        return $this->query()->whereNotNull('closed_at')->where('closed_at', '>=', $p->utcStart())->where('closed_at', '<', $p->utcEnd());
    }

    public function closed(Period $p): int
    {
        return $this->closedIn($p)->count();
    }

    /** @return array{rate: float|null, engaged: int, n: int, hidden: bool} */
    public function engagement(Period $p): array
    {
        $rows = $this->closedIn($p)->whereNotNull('outcome')->pluck('outcome');
        $n = $rows->count();
        $engaged = $rows->filter(fn ($o) => $o === 'engaged')->count();

        return ['rate' => $n < self::MIN_ENGAGEMENT_N ? null : round($engaged / $n * 100, 1), 'engaged' => $engaged, 'n' => $n, 'hidden' => $n < self::MIN_ENGAGEMENT_N];
    }

    /** @return array<string, int> outcome => count of requests closed in P */
    public function outcomes(Period $p): array
    {
        $out = array_fill_keys(Enquiry::OUTCOMES, 0);
        foreach ($this->closedIn($p)->whereNotNull('outcome')->pluck('outcome') as $o) {
            if (isset($out[$o])) {
                $out[$o]++;
            }
        }

        return $out;
    }

    /** @return array{rate: float|null, spam: int, all: int} */
    public function spamRate(Period $p): array
    {
        $all = $this->submissions()->where('received_at', '>=', $p->utcStart())->where('received_at', '<', $p->utcEnd());
        $total = (clone $all)->count();
        $spam = (clone $all)->where('status', Enquiry::STATUS_SPAM)->count();

        return ['rate' => $total === 0 ? null : round($spam / $total * 100, 1), 'spam' => $spam, 'all' => $total];
    }

    // ------------------------------------------------------------------ mix

    /**
     * @param  list<string>|null  $order  categories in display order; the rest after them, "" (Not chosen) last
     * @return array<string, array{count: int, share: float}>
     */
    public function shares(Period $p, string $column, ?array $order = null): array
    {
        $total = $this->received($p);
        $counts = [];
        foreach ($this->receivedIn($p)->pluck($column) as $value) {
            $key = $value === null ? '' : (string) $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        if ($order !== null) {
            $sorted = [];
            foreach ($order as $key) {
                $sorted[$key] = $counts[$key] ?? 0;
            }
            foreach ($counts as $key => $c) {
                $sorted[$key] ??= $c;
            }
            $counts = $sorted;
        }
        $out = [];
        foreach ($counts as $key => $c) {
            $out[(string) $key] = ['count' => $c, 'share' => $total === 0 ? 0.0 : round($c / $total * 100, 1)];
        }

        return $out;
    }

    /** @return array<string, int> phone_only | email_only | both */
    public function contactMethods(Period $p): array
    {
        $out = ['phone_only' => 0, 'email_only' => 0, 'both' => 0];
        foreach ($this->receivedIn($p)->get(['phone', 'email']) as $e) {
            $phone = filled($e->phone);
            $email = filled($e->email);
            $key = $phone && $email ? 'both' : ($phone ? 'phone_only' : ($email ? 'email_only' : null));
            if ($key !== null) {
                $out[$key]++;
            }
        }

        return $out;
    }

    /** @return array<string, int> status => count of the requests received in P (the pipeline) */
    public function pipeline(Period $p): array
    {
        $out = [Enquiry::STATUS_NEW => 0, Enquiry::STATUS_IN_REVIEW => 0, Enquiry::STATUS_CLOSED => 0];
        foreach ($this->receivedIn($p)->pluck('status') as $s) {
            if (isset($out[$s])) {
                $out[$s]++;
            }
        }

        return $out;
    }

    /** @return Collection<int, array{assignee_id: int|null, open: int, received: int, closed: int}> */
    public function workload(Period $p): Collection
    {
        $rows = [];
        foreach ($this->receivedIn($p)->get(['assignee_id', 'status']) as $e) {
            $key = (string) ($e->assignee_id ?? '');
            $rows[$key] ??= ['assignee_id' => $e->assignee_id, 'open' => 0, 'received' => 0, 'closed' => 0];
            $rows[$key]['received']++;
            if ($e->isOpen()) {
                $rows[$key]['open']++;
            }
        }
        foreach ($this->closedIn($p)->get(['assignee_id']) as $e) {
            $key = (string) ($e->assignee_id ?? '');
            $rows[$key] ??= ['assignee_id' => $e->assignee_id, 'open' => 0, 'received' => 0, 'closed' => 0];
            $rows[$key]['closed']++;
        }

        return collect(array_values($rows))->sortByDesc('received')->values();
    }

    /**
     * Top 10 values of a source column plus "Other" (§11.2 Sources); "" = none recorded.
     *
     * @return array<string, int>
     */
    public function top(Period $p, string $column, int $limit = 10): array
    {
        if (! in_array($column, ['source_page', 'referrer', 'utm_campaign', 'utm_source'], true)) {
            throw new \InvalidArgumentException("Cannot rank by [{$column}].");
        }
        $counts = [];
        foreach ($this->receivedIn($p)->pluck($column) as $value) {
            $key = $value === null ? '' : (string) $value;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        $none = $counts[''] ?? 0;
        unset($counts['']);
        arsort($counts);
        $top = array_slice($counts, 0, $limit, true);
        $other = array_sum(array_slice($counts, $limit, null, true));
        if ($other > 0) {
            $top['__other'] = $other;
        }
        if ($none > 0) {
            $top[''] = $none;
        }

        return $top;
    }
}
