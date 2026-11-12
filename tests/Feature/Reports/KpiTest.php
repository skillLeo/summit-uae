<?php

namespace Tests\Feature\Reports;

use App\Models\Enquiry;
use App\Models\User;
use App\Reports\Kpi;
use App\Reports\Period;
use App\Requests\BusinessHours;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every KPI formula of docs/briefs/design-2.1-and-admin-cms.md §11.3 against fixtures (§14.2 "Reports"): period edges
 * at Dubai midnight, a previous period of 0, median and P90 with odd and even counts, business hours across weekends
 * and closures, spam and demo exclusion, within target, engagement, spam rate and shares.
 */
final class KpiTest extends TestCase
{
    use RefreshDatabase;

    /** Thursday 12 November 2026, 12:00 Dubai. */
    private const NOW = '2026-11-12 08:00:00';

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse(self::NOW, 'UTC');
        $this->travelTo($this->now);
    }

    private function kpi(array $filters = [], ?BusinessHours $hours = null): Kpi
    {
        return new Kpi($filters, $hours ?? new BusinessHours([1, 2, 3, 4, 5], '09:00', '18:00', ['2026-11-11']), $this->now);
    }

    /** A request received at a Dubai wall-clock time. */
    private function at(string $dubai, array $attributes = []): Enquiry
    {
        $received = CarbonImmutable::parse($dubai, 'Asia/Dubai')->utc();
        $e = new Enquiry;
        $e->forceFill($attributes + [
            'reference' => Enquiry::newReference(),
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
            'status' => Enquiry::STATUS_NEW,
            'name' => 'Fixture',
            'email' => 'f@example.test',
            'summary' => 'x',
            'consent_at' => $received,
            'consent_text' => 'x',
            'ip_hash' => str_repeat('a', 64),
            'received_at' => $received,
        ])->save();

        return $e;
    }

    private function dubai(string $at): CarbonImmutable
    {
        return CarbonImmutable::parse($at, 'Asia/Dubai')->utc();
    }

    // ------------------------------------------------------------------ periods

    public function test_periods_are_half_open_at_dubai_midnight(): void
    {
        $today = Period::make('today', now: $this->now);
        $this->assertSame('2026-11-11 20:00:00', $today->utcStart()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-12 20:00:00', $today->utcEnd()->format('Y-m-d H:i:s'));

        $this->at('2026-11-12 00:00:00');            // the first second of today, Dubai: in
        $this->at('2026-11-11 23:59:59');            // the last second of yesterday: out
        $this->at('2026-11-12 23:59:59');            // later today: in

        $this->assertSame(2, $this->kpi()->received($today));
        $this->assertSame(1, $this->kpi()->received($today->previous()));
    }

    public function test_presets_and_their_previous_periods(): void
    {
        $seven = Period::make('7d', now: $this->now);
        $this->assertSame('2026-11-06', $seven->start->format('Y-m-d'));
        $this->assertSame('2026-11-13', $seven->end->format('Y-m-d'));
        $this->assertSame('2026-10-30', $seven->previous()->start->format('Y-m-d'));
        $this->assertSame(7, $seven->days());
        $this->assertSame('day', Period::make('30d', now: $this->now)->bucket());
        $this->assertSame('week', Period::make('90d', now: $this->now)->bucket());

        $last = Period::make('last_month', now: $this->now);
        $this->assertSame(['2026-10-01', '2026-11-01'], [$last->start->format('Y-m-d'), $last->end->format('Y-m-d')]);
        $this->assertSame('2026-09-01', $last->previous()->start->format('Y-m-d'));
        $quarter = Period::make('this_quarter', now: $this->now);
        $this->assertSame(['2026-10-01', '2027-01-01'], [$quarter->start->format('Y-m-d'), $quarter->end->format('Y-m-d')]);
        $custom = Period::make('custom', '2026-11-01', '2026-11-03', $this->now);
        $this->assertSame(3, $custom->days());
    }

    // ------------------------------------------------------------------ received and change

    public function test_change_with_a_previous_period_of_zero_small_and_large_bases(): void
    {
        $this->assertSame(['current' => 3, 'previous' => 0, 'change' => 3, 'percent' => null, 'note' => 'no_earlier_data'], Kpi::compare(3, 0));
        $this->assertSame(['current' => 3, 'previous' => 9, 'change' => -6, 'percent' => null, 'note' => 'small_base'], Kpi::compare(3, 9));
        $this->assertSame(['current' => 15, 'previous' => 10, 'change' => 5, 'percent' => 50.0, 'note' => null], Kpi::compare(15, 10));

        $this->at('2026-11-10 10:00');
        $this->at('2026-11-11 10:00');
        $change = $this->kpi()->change(Period::make('7d', now: $this->now));
        $this->assertSame(2, $change['current']);
        $this->assertSame(0, $change['previous']);
        $this->assertSame('no_earlier_data', $change['note']);
    }

    public function test_spam_never_counts_and_demo_counts_only_outside_production(): void
    {
        $this->at('2026-11-10 10:00');
        $this->at('2026-11-10 11:00', ['status' => Enquiry::STATUS_SPAM]);
        $this->at('2026-11-10 12:00', ['is_demo' => true]);
        $p = Period::make('7d', now: $this->now);

        $this->assertSame(2, $this->kpi()->received($p));
        $this->assertSame(['rate' => 33.3, 'spam' => 1, 'all' => 3], $this->kpi()->spamRate($p));

        $this->app['env'] = 'production';
        $this->assertSame(1, $this->kpi()->received($p));
        $this->assertSame(['rate' => 50.0, 'spam' => 1, 'all' => 2], $this->kpi()->spamRate($p));
    }

    public function test_waiting_now_and_the_oldest_wait(): void
    {
        $this->at('2026-11-09 09:00');                                               // Monday
        $this->at('2026-11-10 09:00');
        $this->at('2026-11-08 09:00', ['status' => Enquiry::STATUS_IN_REVIEW]);
        $this->at('2026-11-01 09:00', ['status' => Enquiry::STATUS_SPAM]);

        $waiting = $this->kpi()->waitingNow();
        $this->assertSame(2, $waiting['count']);
        $this->assertSame('2026-11-09 05:00:00', $waiting['oldest_received_at']->format('Y-m-d H:i:s'));
        $this->assertSame(75.0, $waiting['oldest_hours']);                            // Mon 09:00 to Thu 12:00
        // Mon 9 + Tue 9 + (Wed closure) 0 + Thu 3 business hours
        $this->assertSame(21.0, $waiting['oldest_business_hours']);
    }

    // ------------------------------------------------------------------ time to first review

    public function test_ttfr_counts_business_hours_across_weekends_and_closures(): void
    {
        // Friday 17:00 to Monday 10:00: 1 + 1 = 2 business hours (62 calendar hours)
        $this->at('2026-11-06 17:00', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai('2026-11-09 10:00')]);
        // Tuesday 17:30 to Thursday 09:30, the closure on Wednesday 11 November skipped: 0.5 + 0.5 = 1
        $this->at('2026-11-10 17:30', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai('2026-11-12 09:30')]);

        $values = $this->kpi()->ttfrValues(Period::make('30d', now: $this->now));
        sort($values);
        $this->assertSame([1.0, 2.0], $values);
    }

    public function test_median_and_p90_with_odd_and_even_counts(): void
    {
        $this->assertNull(Kpi::percentile([], 50));
        $this->assertSame(3.0, Kpi::percentile([5, 1, 3], 50));                     // odd: the middle value
        $this->assertSame(4.6, Kpi::percentile([5, 1, 3], 90));                     // 3 + (5 - 3) × 0.8
        $this->assertSame(2.5, Kpi::percentile([4, 1, 2, 3], 50));                  // even: mean of the middle two
        $this->assertSame(3.7, Kpi::percentile([4, 1, 2, 3], 90));                  // 3 + (4 - 3) × 0.7
        $this->assertSame(7.0, Kpi::percentile([7], 90));

        // through the fixtures: reviews after 1, 2 and 6 business hours on Monday 9 November, one unreviewed
        foreach ([10, 11, 15] as $hour) {
            $this->at('2026-11-09 09:00', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai("2026-11-09 {$hour}:00")]);
        }
        $this->at('2026-11-09 09:00');
        $ttfr = $this->kpi()->ttfr(Period::make('7d', now: $this->now));
        $this->assertSame(['median' => 2.0, 'p90' => 5.2, 'n' => 3, 'unreviewed' => 1], $ttfr);
    }

    public function test_within_target_counts_unreviewed_requests_past_the_target_as_missed(): void
    {
        // target 4 business hours; now = Thursday 12 November 12:00 Dubai (3 business hours since opening)
        $this->at('2026-11-09 09:00', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai('2026-11-09 12:00')]); // 3 h: met
        $this->at('2026-11-09 09:00', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai('2026-11-09 13:00')]); // 4 h: met (within)
        $this->at('2026-11-09 09:00', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai('2026-11-09 17:00')]); // 8 h: missed
        $this->at('2026-11-10 09:00');                                                                                    // 9 + 3 = 12 h waiting: missed
        $this->at('2026-11-12 10:00');                                                                                    // 2 h waiting: left out

        $this->assertSame(
            ['share' => 50.0, 'met' => 2, 'missed' => 2, 'excluded' => 1, 'target' => 4],
            $this->kpi()->withinTarget(Period::make('7d', now: $this->now), 4),
        );
        $this->assertNull($this->kpi()->withinTarget(Period::make('today', now: $this->now)->previous(), 4)['share']);
    }

    public function test_the_ttfr_histogram_buckets(): void
    {
        foreach (['09:30' => 'lt1', '11:00' => '1to4', '15:00' => '4to8'] as $time => $bucket) {
            $this->at('2026-11-09 09:00', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai("2026-11-09 {$time}")]);
        }
        $this->at('2026-11-09 09:00', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai('2026-11-10 12:00')]);    // 12 h
        $this->at('2026-11-02 09:00', ['status' => 'in_review', 'first_reviewed_at' => $this->dubai('2026-11-06 12:00')]);    // 39 h

        $this->assertSame(['lt1' => 1, '1to4' => 1, '4to8' => 1, '1to2d' => 1, 'gt2d' => 1], $this->kpi()->ttfrHistogram(Period::make('30d', now: $this->now)));
    }

    // ------------------------------------------------------------------ closed and engagement

    public function test_closed_and_the_engagement_rate_hidden_below_five(): void
    {
        $p = Period::make('7d', now: $this->now);
        foreach (['engaged', 'engaged', 'not_engaged', 'no_reply'] as $outcome) {
            $this->at('2026-10-01 10:00', ['status' => 'closed', 'outcome' => $outcome, 'closed_at' => $this->dubai('2026-11-10 10:00')]);
        }
        // closed before the period: not counted
        $this->at('2026-10-01 10:00', ['status' => 'closed', 'outcome' => 'engaged', 'closed_at' => $this->dubai('2026-11-05 23:59:59')]);

        $this->assertSame(4, $this->kpi()->closed($p));
        $this->assertSame(['rate' => null, 'engaged' => 2, 'n' => 4, 'hidden' => true], $this->kpi()->engagement($p));

        $this->at('2026-10-01 10:00', ['status' => 'closed', 'outcome' => 'referred', 'closed_at' => $this->dubai('2026-11-06 00:00:00')]);
        $this->assertSame(['rate' => 40.0, 'engaged' => 2, 'n' => 5, 'hidden' => false], $this->kpi()->engagement($p));
        $this->assertSame(['engaged' => 2, 'not_engaged' => 1, 'referred' => 1, 'no_reply' => 1], $this->kpi()->outcomes($p));
    }

    // ------------------------------------------------------------------ mix, filters, series

    public function test_shares_count_empty_values_as_not_chosen(): void
    {
        $this->at('2026-11-10 10:00', ['preferred_language' => 'ar']);
        $this->at('2026-11-10 10:00', ['preferred_language' => 'ar']);
        $this->at('2026-11-10 10:00', ['preferred_language' => 'en']);
        $this->at('2026-11-10 10:00', ['preferred_language' => null]);

        $shares = $this->kpi()->shares(Period::make('7d', now: $this->now), 'preferred_language', ['en', 'ar', 'ru', '']);
        $this->assertSame([
            'en' => ['count' => 1, 'share' => 25.0],
            'ar' => ['count' => 2, 'share' => 50.0],
            'ru' => ['count' => 0, 'share' => 0.0],
            '' => ['count' => 1, 'share' => 25.0],
        ], $shares);
    }

    public function test_filters_and_the_contact_methods(): void
    {
        $user = User::factory()->create();
        $this->at('2026-11-10 10:00', ['matter_type' => 'S06', 'phone' => '+97150', 'email' => null, 'assignee_id' => $user->id]);
        $this->at('2026-11-10 10:00', ['matter_type' => 'S06', 'phone' => '+97150', 'email' => 'x@example.test']);
        $this->at('2026-11-10 10:00', ['matter_type' => null]);
        $p = Period::make('7d', now: $this->now);

        $this->assertSame(2, $this->kpi(['matter_type' => ['S06']])->received($p));
        $this->assertSame(1, $this->kpi(['matter_type' => ['']])->received($p));
        $this->assertSame(1, $this->kpi(['assignee_id' => [(string) $user->id]])->received($p));
        $this->assertSame(['phone_only' => 1, 'email_only' => 1, 'both' => 1], $this->kpi()->contactMethods($p));
    }

    public function test_the_series_buckets_by_dubai_day_and_by_week_beyond_31_days(): void
    {
        $this->at('2026-11-12 00:30');
        $this->at('2026-11-11 23:30');
        $daily = $this->kpi()->series(Period::make('7d', now: $this->now));
        $this->assertCount(7, $daily);
        $this->assertSame(1, $daily['2026-11-12']);
        $this->assertSame(1, $daily['2026-11-11']);

        $weekly = $this->kpi()->series(Period::make('90d', now: $this->now));
        $this->assertSame(2, $weekly['2026-11-09']);                                  // the Monday of that week
        $this->assertSame(2, array_sum($weekly));
    }

    public function test_the_heatmap_is_in_dubai_time(): void
    {
        $this->at('2026-11-09 09:15');                                                // Monday 09, Dubai (05 UTC)
        $grid = $this->kpi()->heatmap(Period::make('7d', now: $this->now));

        $this->assertSame(1, $grid[1][9]);
        $this->assertSame(0, $grid[1][5]);
    }

    public function test_top_sources_with_other_and_none(): void
    {
        foreach (range(1, 12) as $i) {
            $this->at('2026-11-10 10:00', ['referrer' => "site{$i}.example"]);
        }
        $this->at('2026-11-10 10:00', ['referrer' => 'site1.example']);
        $this->at('2026-11-10 10:00', ['referrer' => null]);

        $top = $this->kpi()->top(Period::make('7d', now: $this->now), 'referrer');
        $this->assertSame(2, $top['site1.example']);
        $this->assertSame(2, $top['__other']);
        $this->assertSame(1, $top['']);
        $this->assertCount(12, $top);
    }
}
