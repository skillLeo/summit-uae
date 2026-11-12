<?php

namespace App\Console\Commands;

use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use App\Models\User;
use App\Requests\BusinessHours;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Demo data for the client meeting (docs/briefs/design-2.1-and-admin-cms.md §10.7):
 *
 *   php artisan summit:demo-requests --days=120 --count=180   sample requests over the last 120 days
 *   php artisan summit:demo-requests --purge                   removes every demo record (and the sample case handlers)
 *
 * Local and staging only: production refuses. Every record has is_demo = true and an obviously sample name ("Sample
 * Client 042"), sample addresses on the reserved example.invalid domain and phone numbers from one unused block; no
 * realistic personal data. Three sample case handlers (users.is_demo, no panel access) carry the workload. The admin
 * shows a "Demo data" banner while any demo request exists. Deterministic with --seed.
 */
final class DemoRequestsCommand extends Command
{
    protected $signature = 'summit:demo-requests
        {--days=120 : spread the requests over this many days, ending now}
        {--count=180 : how many requests}
        {--seed=2026 : random seed (the same seed gives the same data)}
        {--purge : remove every demo request and sample case handler instead}';

    protected $description = 'Create (or --purge) clearly marked sample requests for the client meeting (not in production)';

    private Randomizer $random;

    private ?int $privacy = null;

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Refusing to run in production: demo requests are for local and staging only.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purge();
        }

        $days = max(1, (int) $this->option('days'));
        $count = max(1, min(5000, (int) $this->option('count')));
        $this->random = new Randomizer(new Mt19937((int) $this->option('seed')));

        $handlers = $this->handlers();
        $staff = User::query()->where('is_admin', true)->where('is_demo', false)->pluck('id')->all();
        $people = array_merge($handlers, array_slice($staff, 0, 1));
        $hours = BusinessHours::fromSettings();
        $now = CarbonImmutable::now();
        $start = Enquiry::query()->where('is_demo', true)->count();
        $this->privacy = DB::table('revisions')->where('revisionable_type', 'page')
            ->where('revisionable_id', (string) config('cms.privacy_page', 'L01'))->max('id');

        DB::transaction(function () use ($count, $days, $people, $hours, $now, $start): void {
            for ($i = 1; $i <= $count; $i++) {
                $this->one($start + $i, $days, $people, $hours, $now);
            }
        });

        $total = Enquiry::query()->where('is_demo', true)->count();
        $this->info("Created {$count} demo requests over {$days} days ({$total} demo requests in total). Remove them with --purge.");

        return self::SUCCESS;
    }

    private function purge(): int
    {
        $ids = Enquiry::query()->withTrashed()->where('is_demo', true)->pluck('id');
        DB::transaction(function () use ($ids): void {
            foreach ($ids->chunk(500) as $chunk) {
                EnquiryEvent::query()->whereIn('enquiry_id', $chunk)->delete();
                DB::table('enquiry_notes')->whereIn('enquiry_id', $chunk)->delete();
                Enquiry::query()->withTrashed()->whereIn('id', $chunk)->forceDelete();
            }
            $users = User::query()->where('is_demo', true)->pluck('id');
            DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())->whereIn('notifiable_id', $users)->delete();
            User::query()->whereIn('id', $users)->delete();
        });
        // panel notifications about demo requests
        foreach (DB::table('notifications')->get(['id', 'data']) as $row) {
            if (preg_match('/Sample Client|SLC-\d{6}-[0-9A-Z]{6}/', (string) $row->data) === 1 && $this->mentionsGone((string) $row->data)) {
                DB::table('notifications')->where('id', $row->id)->delete();
            }
        }
        $this->info("Removed {$ids->count()} demo requests and the sample case handlers.");

        return self::SUCCESS;
    }

    private function mentionsGone(string $data): bool
    {
        preg_match_all('/SLC-\d{6}-[0-9A-Z]{6}/', $data, $m);
        foreach ($m[0] as $reference) {
            if (Enquiry::query()->withTrashed()->where('reference', $reference)->exists()) {
                return false;
            }
        }

        return $m[0] !== [];
    }

    /** @return list<int> three sample case handlers (no panel access: is_admin false, unusable password) */
    private function handlers(): array
    {
        $ids = [];
        foreach ([1, 2, 3] as $n) {
            $user = User::query()->firstOrNew(['email' => "sample-handler-{$n}@example.invalid"]);
            $user->forceFill([
                'name' => "Sample Case Handler {$n}",
                'password' => Str::random(64),
                'is_admin' => false,
                'is_demo' => true,
            ])->save();
            $ids[] = (int) $user->getKey();
        }

        return $ids;
    }

    /** @param  list<int>  $people */
    private function one(int $n, int $days, array $people, BusinessHours $hours, CarbonImmutable $now): void
    {
        $zone = $hours->timezone();
        $received = $this->receivedAt($days, $now, $zone);
        $ageDays = $received->diffInDays($now);
        $locale = $this->pick(['en' => 50, 'ar' => 30, 'ru' => 20]);
        $roll = $this->random->getInt(1, 100);
        $preferred = $roll <= 60 ? $locale : ($roll <= 80 ? '' : $this->pick(['en' => 50, 'ar' => 25, 'ru' => 25]));
        $matter = $this->pick(['S01' => 9, 'S02' => 7, 'S03' => 6, 'S04' => 8, 'S05' => 7, 'S06' => 14, 'S07' => 10, 'S08' => 5, 'S09' => 6, 'S10' => 4, 'S11' => 5, 'other' => 7, 'unsure' => 4, '' => 8]);
        $contact = $this->pick(['email' => 45, 'phone' => 25, 'both' => 30]);
        $number = sprintf('%03d', $n);
        $spam = $this->random->getInt(1, 100) <= 6;

        $status = Enquiry::STATUS_NEW;
        $first = $closed = $outcome = null;
        $assignee = null;
        if (! $spam) {
            // older requests have moved on; the last few days are still in the queue
            $reviewed = $ageDays > 3 ? $this->random->getInt(1, 100) <= 96 : $this->random->getInt(1, 100) <= 45;
            if ($reviewed) {
                $first = $this->afterBusinessHours($received, $this->pick(['0.5' => 25, '2' => 30, '6' => 20, '12' => 15, '24' => 7, '40' => 3]), $hours, $now);
                $status = Enquiry::STATUS_IN_REVIEW;
                $assignee = $people[$this->random->getInt(0, count($people) - 1)];
                if ($ageDays > 6 && $this->random->getInt(1, 100) <= 85) {
                    $closed = $first->addHours($this->random->getInt(4, 24 * 12));
                    if ($closed < $now) {
                        $status = Enquiry::STATUS_CLOSED;
                        $outcome = $this->pick(['engaged' => 38, 'not_engaged' => 30, 'referred' => 12, 'no_reply' => 20]);
                    } else {
                        $closed = null;
                    }
                }
            } elseif ($this->random->getInt(1, 100) <= 30) {
                $assignee = $people[$this->random->getInt(0, count($people) - 1)];
            }
        } else {
            $status = Enquiry::STATUS_SPAM;
        }

        $page = $this->pick(['/'.$locale.'/' => 45, '/'.$locale.'/contact-us/' => 30, '/'.$locale.'/practice-areas/' => 10, '/'.$locale.'/legal-resources/' => 8, '/'.$locale.'/calculators/' => 7]);
        $referrer = $this->pick(['google.com' => 40, '' => 30, 'bing.com' => 8, 'linkedin.com' => 8, 'instagram.com' => 6, 'duckduckgo.com' => 4, 'yandex.ru' => 4]);
        $campaign = $this->random->getInt(1, 100) <= 22 ? $this->pick(['employment-rights' => 40, 'cheque-recovery' => 35, 'tenancy-guide' => 25]) : null;

        $enquiry = new Enquiry;
        $enquiry->forceFill([
            'reference' => Enquiry::newReference($received->setTimezone($zone)),
            'idempotency_key' => (string) Str::uuid(),
            'locale' => $locale,
            'status' => $status,
            'received_at' => $received,
            'created_at' => $received,
            'updated_at' => $closed ?? $first ?? $received,
            'name' => "Sample Client {$number}",
            'email' => $contact !== 'phone' ? "sample-client-{$number}@example.invalid" : null,
            'phone' => $contact !== 'email' ? '+97150000'.sprintf('%04d', $n % 10000) : null,
            'preferred_language' => $preferred === '' ? null : $preferred,
            'matter_type' => $matter === '' ? null : $matter,
            'summary' => "Sample request {$number} for the demo. It is not a real enquiry and holds no personal data.",
            'deadline_date' => $this->random->getInt(1, 100) <= 25 ? $received->addDays($this->random->getInt(2, 40))->format('Y-m-d') : null,
            'deadline_details' => null,
            'consent_at' => $received,
            'consent_text' => (string) rescue(fn () => ui(Enquiry::CONSENT_LABEL_KEY, $locale), 'Consent', report: false),
            'ip_hash' => hash('sha256', 'demo-'.$n),
            'assignee_id' => $assignee,
            'outcome' => $outcome,
            'first_reviewed_at' => $first,
            'closed_at' => $closed,
            'status_changed_at' => $closed ?? $first,
            'spam_score' => $spam ? 60 : 0,
            'spam_reasons' => $spam ? [$this->pick(['links' => 50, 'too_fast' => 30, 'honeypot' => 20])] : null,
            'source_page' => $page,
            'referrer' => $referrer === '' ? null : $referrer,
            'utm_source' => $campaign ? $this->pick(['google' => 60, 'linkedin' => 40]) : null,
            'utm_medium' => $campaign ? 'cpc' : null,
            'utm_campaign' => $campaign,
            'device_class' => $this->pick(['mobile' => 58, 'desktop' => 36, 'tablet' => 6]),
            'design' => $this->pick(['1' => 50, '2' => 50]),
            'privacy_revision_id' => $this->privacy,
            'is_demo' => true,
        ]);
        $enquiry->timestamps = false;
        $enquiry->save();

        $events = [['received', null, $status === Enquiry::STATUS_SPAM ? 'spam' : 'new', $received, null]];
        if ($first !== null) {
            $events[] = ['assigned', null, (string) $assignee, $first, null];
            $events[] = ['status', 'new', 'in_review', $first, null];
        } elseif ($assignee !== null) {
            $events[] = ['assigned', null, (string) $assignee, $received->addMinutes(20), null];
        }
        if ($closed !== null) {
            $events[] = ['status', 'in_review', 'closed', $closed, null];
            $events[] = ['outcome', null, $outcome, $closed, null];
        }
        foreach ($events as [$type, $from, $to, $at]) {
            EnquiryEvent::query()->create(['enquiry_id' => $enquiry->getKey(), 'user_id' => null, 'type' => $type, 'from' => $from, 'to' => $to, 'created_at' => $at]);
        }
    }

    /** Mostly on working days in office hours, some evenings and weekends, more requests in recent weeks. */
    private function receivedAt(int $days, CarbonImmutable $now, string $zone): CarbonImmutable
    {
        for ($try = 0; $try < 20; $try++) {
            // a gentle upward trend: later days are a little more likely
            $u = $this->random->nextFloat();
            $dayBack = (int) floor($days * (1 - sqrt($u * 0.55 + 0.45 * $u * $u)));
            $day = $now->setTimezone($zone)->subDays(max(0, $dayBack))->startOfDay();
            $weekend = in_array($day->dayOfWeekIso, [6, 7], true);
            if ($weekend && $this->random->getInt(1, 100) > 35) {
                continue;
            }
            $hour = (int) $this->pick(['8' => 4, '9' => 8, '10' => 11, '11' => 11, '12' => 9, '13' => 8, '14' => 9, '15' => 9, '16' => 8, '17' => 6, '18' => 4, '19' => 4, '20' => 4, '21' => 3, '22' => 2]);
            $at = $day->setTime($hour, $this->random->getInt(0, 59), $this->random->getInt(0, 59))->utc();
            if ($at < $now) {
                return $at;
            }
        }

        return $now->subMinutes($this->random->getInt(5, 600));
    }

    private function afterBusinessHours(CarbonImmutable $from, string $hours, BusinessHours $calendar, CarbonImmutable $now): CarbonImmutable
    {
        $target = (float) $hours * (0.5 + $this->random->nextFloat());
        $at = $from;
        // step in 15-minute increments until the business hours reach the target (capped at 30 days)
        for ($i = 0; $i < 30 * 96; $i++) {
            $at = $at->addMinutes(15);
            if ($calendar->between($from, $at) >= $target || $at >= $now) {
                break;
            }
        }

        return $at < $now ? $at : $now->subMinutes(1);
    }

    /** @param  array<string, int>  $weights */
    private function pick(array $weights): string
    {
        $total = array_sum($weights);
        $roll = $this->random->getInt(1, $total);
        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_last($weights);
    }
}
