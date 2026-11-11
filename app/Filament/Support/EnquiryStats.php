<?php

namespace App\Filament\Support;

use App\Models\Enquiry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dashboard figures. Counts only ever read reference-free aggregates (no names, no summaries) and
 * exclude spam everywhere. Day buckets follow the firm's time zone (config summit.contact.timezone,
 * Asia/Dubai). Nothing here is tracked beyond what the form already stores on submission.
 */
final class EnquiryStats
{
    /** Columns the dashboard may group by (never free text or personal data). */
    private const GROUPABLE = ['matter_type', 'locale', 'preferred_language', 'status'];

    /** @return Builder<Enquiry> every request except spam */
    public static function received(): Builder
    {
        return Enquiry::query()->where('status', '!=', Enquiry::STATUS_SPAM);
    }

    public static function timezone(): string
    {
        return (string) config('summit.contact.timezone', 'Asia/Dubai');
    }

    /** Requests received in the last $days days (rolling window ending now). */
    public static function countSince(int $days, int $offsetDays = 0): int
    {
        $end = now()->subDays($offsetDays);

        return self::received()
            ->where('created_at', '>', $end->copy()->subDays($days))
            ->where('created_at', '<=', $end)
            ->count();
    }

    /**
     * Requests per local day for the last $days days, oldest first (sparkline data).
     *
     * @return list<int>
     */
    public static function daily(int $days): array
    {
        $zone = self::timezone();
        $today = CarbonImmutable::now($zone)->startOfDay();
        $buckets = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $buckets[$today->subDays($i)->format('Y-m-d')] = 0;
        }

        $since = $today->subDays($days - 1)->utc();
        foreach (self::received()->where('created_at', '>=', $since)->pluck('created_at') as $at) {
            $day = CarbonImmutable::parse($at)->setTimezone($zone)->format('Y-m-d');
            if (array_key_exists($day, $buckets)) {
                $buckets[$day]++;
            }
        }

        return array_values($buckets);
    }

    public static function awaitingReview(): int
    {
        return Enquiry::query()->where('status', Enquiry::STATUS_NEW)->count();
    }

    /**
     * @return array<string, int> grouped column value ('' for not chosen) => count
     */
    public static function countBy(string $column, ?int $days = null): array
    {
        if (! in_array($column, self::GROUPABLE, true)) {
            throw new \InvalidArgumentException("Cannot group enquiries by [{$column}].");
        }

        $query = self::received();
        if ($days !== null) {
            $query->where('created_at', '>', now()->subDays($days));
        }

        $counts = [];
        foreach ($query->selectRaw("{$column} as grp, count(*) as total")->groupBy($column)->get() as $row) {
            $counts[(string) ($row->getAttribute('grp') ?? '')] = (int) $row->getAttribute('total');
        }

        return $counts;
    }
}
