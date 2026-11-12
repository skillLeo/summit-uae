<?php

namespace App\Requests;

use App\Models\Enquiry;
use Carbon\CarbonImmutable;

/**
 * The figures of the daily digest and the overdue sweep (docs/briefs/design-2.1-and-admin-cms.md §10.5). Spam never
 * counts; demo records are left out in production (Enquiry::scopeCounted).
 */
final class Digest
{
    /** @return array{date: string, received: int, waiting: int, overdue: int, oldest_hours: float|null, references: list<array{reference: string, hours: float, overdue: bool}>, url: string} */
    public static function figures(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $hours = BusinessHours::fromSettings();
        $target = ReviewTarget::hours();

        $received = Enquiry::query()->counted()->where('received_at', '>=', $now->subDay())->where('received_at', '<', $now)->count();
        $waiting = Enquiry::query()->counted()->where('status', Enquiry::STATUS_NEW)->orderBy('received_at')->get(['id', 'reference', 'received_at', 'created_at', 'status', 'first_reviewed_at']);

        $rows = [];
        foreach ($waiting as $enquiry) {
            $h = round($hours->between($enquiry->received_at ?? $enquiry->created_at, $now), 1);
            $rows[] = ['reference' => $enquiry->reference, 'hours' => $h, 'overdue' => $h > $target];
        }

        return [
            'date' => $now->setTimezone($hours->timezone())->format('Y-m-d'),
            'received' => $received,
            'waiting' => count($rows),
            'overdue' => count(array_filter($rows, fn ($r) => $r['overdue'])),
            'oldest_hours' => $rows[0]['hours'] ?? null,
            'references' => array_slice($rows, 0, 50),
            'url' => (string) rescue(fn () => route('filament.admin.resources.requests.index'), url('/admin'), report: false),
        ];
    }

    /** Sends the overdue alert once for every request past the target; returns how many were sent. */
    public static function sweepOverdue(Notifier $notifier, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $hours = BusinessHours::fromSettings();
        $count = 0;
        $candidates = Enquiry::query()->counted()->where('is_demo', false)->where('status', Enquiry::STATUS_NEW)
            ->whereNull('first_reviewed_at')->whereNull('overdue_notified_at')->get();
        foreach ($candidates as $enquiry) {
            if (! ReviewTarget::isOverdue($enquiry, $hours, $now)) {
                continue;
            }
            $enquiry->forceFill(['overdue_notified_at' => $now])->save();
            $notifier->overdue($enquiry);
            Workflow::make()->event($enquiry, 'overdue', null, null, null, ['target' => ReviewTarget::hours()]);
            $count++;
        }

        return $count;
    }
}
