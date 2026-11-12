<?php

namespace Tests\Feature\Reports;

use App\Filament\Widgets\KpiTiles;
use App\Filament\Widgets\NeedsAttention;
use App\Models\Enquiry;
use App\Models\User;
use App\Settings\OfficeHoursSettings;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard (docs/briefs/design-2.1-and-admin-cms.md §11.1, D50): four KPI tiles without icons or filler, the
 * 30-day column chart (or its empty state), Needs attention, the latest 8 requests and Content status.
 */
final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function enquiry(array $attributes = []): Enquiry
    {
        $e = new Enquiry;
        $e->forceFill($attributes + [
            'reference' => Enquiry::newReference(),
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
            'status' => Enquiry::STATUS_NEW,
            'name' => 'A',
            'email' => 'a@example.test',
            'summary' => 'x',
            'consent_at' => now(),
            'consent_text' => 'x',
            'ip_hash' => str_repeat('a', 64),
        ])->save();

        return $e;
    }

    public function test_the_tiles_follow_the_kpi_definitions_without_icons_or_filler(): void
    {
        $this->enquiry(['received_at' => now()->subDays(2)]);
        $this->enquiry(['received_at' => now()->subDays(3), 'status' => Enquiry::STATUS_SPAM]);
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        $tiles = collect((new KpiTiles)->tiles())->keyBy('key');
        $this->assertSame('1', $tiles['new']['value']);
        $this->assertStringContainsString('no earlier data', $tiles['new']['line']);
        $this->assertSame('1', $tiles['waiting']['value']);
        $this->assertNull($tiles['ttfr']['value']);
        $this->assertStringContainsString('(n = 0)', $tiles['closed']['line']);

        $html = $this->get('/admin')->assertOk()->getContent();
        preg_match_all('/<(?:a|div)[^>]+class="slc-kpi"[^>]*>(.*?)<\/(?:a|div)>\s*(?=<(?:a|div)[^>]+class="slc-kpi"|<\/div>)/s', $html, $m);
        $this->assertSame(4, substr_count($html, 'data-kpi="'));
        foreach (['new', 'waiting', 'ttfr', 'closed'] as $key) {
            $tile = substr($html, strpos($html, 'data-kpi="'.$key.'"'), 400);
            $this->assertStringNotContainsString('fi-icon', substr($tile, 0, strpos($tile, 'slc-kpi__value') ?: 200), $key);
        }
        $this->assertStringNotContainsString('Not tracked', $html);
    }

    public function test_the_sparkline_needs_seven_points(): void
    {
        $this->assertNull(KpiTiles::sparkline([1, 2, 3, 4, 5, 6]));
        $this->assertNotNull(KpiTiles::sparkline([0, 0, 0, 0, 0, 0, 1]));
    }

    public function test_an_empty_month_shows_one_line_instead_of_axes(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('data-chart-empty', $html);
        $this->assertStringContainsString(__('dashboard.chart.empty'), $html);
    }

    public function test_needs_attention_lists_over_target_unassigned_and_close_deadlines(): void
    {
        $hours = app(OfficeHoursSettings::class);
        $hours->review_target_hours = 1;
        $hours->save();
        $this->travelTo('2026-11-12 10:00:00');    // Thursday 14:00 Dubai
        $over = $this->enquiry(['received_at' => '2026-11-09 05:00:00']);
        $deadline = $this->enquiry(['received_at' => now(), 'deadline_date' => '2026-11-15', 'assignee_id' => $this->admin()->id]);
        $this->enquiry(['received_at' => '2026-11-01 05:00:00', 'status' => Enquiry::STATUS_CLOSED, 'outcome' => 'engaged', 'closed_at' => now()]);
        $this->actingAs($this->admin());

        $groups = (new NeedsAttention)->groups();
        $this->assertSame([$over->id], $groups['over_target']['items']->pluck('id')->all());
        $this->assertSame([$over->id], $groups['unassigned']['items']->pluck('id')->all());
        $this->assertSame([$deadline->id], $groups['deadline']['items']->pluck('id')->all());
    }

    public function test_the_latest_requests_show_eight_and_content_status_counts(): void
    {
        foreach (range(1, 10) as $i) {
            $this->enquiry(['received_at' => now()->subMinutes($i)]);
        }
        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        $this->assertSame(8, substr_count($html, '.table.records.'));
        $this->assertStringContainsString(e(__('dashboard.content.pending')), $html);
        $this->assertStringContainsString(e(__('dashboard.content.missing')), $html);
    }
}
