<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\Reports;
use App\Filament\Reports\ReportCsv;
use App\Filament\Reports\ReportData;
use App\Filament\Reports\ReportState;
use App\Filament\Reports\Widgets\ReportChart;
use App\Filament\Reports\Widgets\ReportTable;
use App\Models\Enquiry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ContentSeeder;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 6 Reports page (docs/briefs/design-2.1-and-admin-cms.md §11.2 to §11.4, §14.2 "Reports"): the page and every
 * tab render for an admin in English and Arabic; the filters change the figures; compare adds the previous period;
 * the empty state; the CSV equals the filtered table on screen (volume, matter type, sources); spam stays out except
 * in the spam rate; non-admins are refused.
 */
final class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    /** Thursday 12 November 2026, 12:00 Dubai. */
    private const NOW = '2026-11-12 08:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse(self::NOW, 'UTC'));
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function admin(string $locale = 'en'): User
    {
        $user = User::factory()->create(['name' => 'Report Admin']);
        $user->forceFill(['is_admin' => true, 'admin_locale' => $locale])->save();
        $this->grantRole($user);

        return $user;
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
            'matter_type' => 'S06',
            'summary' => 'x',
            'consent_at' => $received,
            'consent_text' => 'x',
            'ip_hash' => str_repeat('a', 64),
            'received_at' => $received,
        ])->save();

        return $e;
    }

    private function fixtures(): void
    {
        $this->at('2026-11-10 10:15', ['matter_type' => 'S06', 'locale' => 'ar', 'preferred_language' => 'ar', 'source_page' => '/ar/contact-us/', 'referrer' => 'google.com', 'utm_campaign' => 'employment-rights']);
        $this->at('2026-11-10 11:40', ['matter_type' => 'S06', 'source_page' => '/en/', 'referrer' => 'google.com']);
        $this->at('2026-11-05 09:05', ['matter_type' => 'S04', 'phone' => '+971500000001', 'source_page' => '/en/', 'referrer' => '=HYPERLINK("x")']);
        $this->at('2026-11-01 16:30', ['matter_type' => 'other', 'locale' => 'ru', 'source_page' => '/ru/']);
        $this->at('2026-10-20 13:00', ['matter_type' => null, 'source_page' => '/en/practice-areas/']);
        // spam: never in the figures, only in the spam rate
        $this->at('2026-11-09 08:00', ['status' => Enquiry::STATUS_SPAM, 'matter_type' => 'S06', 'source_page' => '/en/']);
        // the previous 30 days
        $this->at('2026-10-05 10:00', ['matter_type' => 'S06']);
        $this->at('2026-09-20 10:00', ['matter_type' => 'S01']);
    }

    /** @param  array<string, mixed>  $filters */
    private function state(array $filters = []): array
    {
        return ReportState::from($filters + ['range' => '30d'])->toArray();
    }

    /** The table a card shows (data-report-table), as header and rows of trimmed cell text. */
    private function tableFromHtml(string $html, string $report): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR);
        $xpath = new DOMXPath($dom);
        $table = $xpath->query("//table[@data-report-table='{$report}']")->item(0);
        $this->assertNotNull($table, "no table for [{$report}]");
        $text = fn ($node): string => trim(preg_replace('/\s+/u', ' ', $node->textContent));
        $columns = [];
        foreach ($xpath->query('.//thead/tr/th', $table) as $th) {
            $columns[] = $text($th);
        }
        $rows = [];
        foreach ($xpath->query('.//tbody/tr', $table) as $tr) {
            $row = [];
            foreach ($xpath->query('./th|./td', $tr) as $cell) {
                $row[] = $text($cell);
            }
            $rows[] = $row;
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    private function csvOf($component): string
    {
        $download = data_get($component->effects, 'download');
        $this->assertNotNull($download, 'no download');

        return base64_decode($download['content']);
    }

    // ------------------------------------------------------------------ the page

    public function test_the_page_renders_every_tab_in_english(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin('en'));

        $this->get('/admin/reports')->assertOk()
            ->assertSee('Reports')
            ->assertSee('Date range')
            ->assertSee('Compare with previous period')
            ->assertSee('Requests over time')
            ->assertSee('Weekday and hour')
            ->assertSee('data-report-table="volume"', false)
            ->assertSee('data-heatmap', false);

        $this->get('/admin/reports?tab=mix')->assertOk()
            ->assertSee('By matter type')->assertSee('Site language and preferred language')->assertSee('Contact method given');
        $this->get('/admin/reports?tab=workflow')->assertOk()
            ->assertSee('Status pipeline')->assertSee('Time to first review')->assertSee('Reviewed within the internal target')
            ->assertSee('Workload per assignee')->assertSee('Outcomes')->assertSee('Spam rate');
        $this->get('/admin/reports?tab=sources')->assertOk()
            ->assertSee('Top source pages')->assertSee('Top referrers')->assertSee('Top UTM campaigns');
        // an unknown tab falls back to Volume
        $this->get('/admin/reports?tab=nope')->assertOk()->assertSee('Requests over time');
    }

    public function test_the_page_renders_every_tab_in_arabic_right_to_left(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin('ar'));

        foreach (['volume' => 'الطلبات عبر الزمن', 'mix' => 'حسب نوع المسألة', 'workflow' => 'مراحل الحالة', 'sources' => 'أهم صفحات المصدر'] as $tab => $heading) {
            $this->get('/admin/reports?tab='.$tab)->assertOk()
                ->assertSee('dir="rtl"', false)
                ->assertSee('التقارير')
                ->assertSee($heading);
        }

        // charts mirror: the x axis runs from the right, the y axis stands on the right
        app()->setLocale('ar');
        $options = (new ReportData(ReportState::from($this->state())))->chart('volume')['options'];
        $this->assertTrue($options['scales']['x']['reverse']);
        $this->assertSame('right', $options['scales']['y']['position']);
        $this->assertTrue($options['plugins']['tooltip']['rtl']);
    }

    public function test_the_navigation_lists_reports_after_the_dashboard(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin')->assertOk()->assertSeeInOrder(['Dashboard', 'Reports']);
        $this->assertSame('overview', Reports::getNavigationGroup());
        $this->assertSame(url('/admin/reports'), Reports::getUrl());
    }

    public function test_non_admins_are_refused(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->save();

        $this->actingAs($user)->get('/admin/reports')->assertForbidden();
        $this->assertFalse(Reports::canAccess());
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/admin/reports')->assertRedirect();
    }

    // ------------------------------------------------------------------ filters

    public function test_a_matter_type_filter_changes_the_figures(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin());

        $all = new ReportData(ReportState::from($this->state()));
        $this->assertSame(5, $all->kpi()->received($all->period()));

        $s06 = new ReportData(ReportState::from($this->state(['matter_type' => 'S06'])));
        $this->assertSame(2, $s06->kpi()->received($s06->period()));
        $this->assertSame(['Employment Law', '2', '100%'], $s06->table('matter')['rows'][0]);
        $this->assertCount(1, $s06->table('matter')['rows']);

        $none = new ReportData(ReportState::from($this->state(['matter_type' => ReportState::NOT_CHOSEN])));
        $this->assertSame(1, $none->kpi()->received($none->period()));

        // the page reads the filter from the URL and prints it in the summary
        $this->get('/admin/reports?tab=mix&filters[range]=30d&filters[matter_type]=S06')->assertOk()
            ->assertSee('Matter type: Employment Law')
            ->assertDontSee('Not sure which category applies</bdi>', false);
    }

    public function test_filters_are_kept_in_the_session_across_a_reload(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin());

        $this->get('/admin/reports?filters[range]=90d&filters[locale]=ar')->assertOk()->assertSee('Site language: ');
        $this->get('/admin/reports')->assertOk()->assertSee('90 days: ')->assertSee('Site language: ');
    }

    public function test_the_livewire_filter_form_updates_the_cards(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Reports::class)
            ->assertSet('filters.range', '30d')
            ->set('filters.range', '90d')
            ->assertSee('90 days: ')
            ->set('filters.matter_type', 'S04')
            ->assertSee('Matter type: ')
            ->set('tab', 'sources')
            ->assertSet('tab', 'sources')
            ->call('resetFilters')
            ->assertSet('filters.range', '30d');
    }

    public function test_a_custom_range_uses_the_from_and_to_dates(): void
    {
        $this->fixtures();
        $state = ReportState::from(['range' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-10']);
        $data = new ReportData($state);

        $this->assertSame('2026-11-01', $data->period()->start->format('Y-m-d'));
        $this->assertSame('2026-11-11', $data->period()->end->format('Y-m-d'));
        $this->assertSame(4, $data->kpi()->received($data->period()));
        $this->assertCount(10, $data->table('volume')['rows']);
    }

    public function test_beyond_31_days_the_volume_is_per_week(): void
    {
        $this->fixtures();
        $data = new ReportData(ReportState::from(['range' => '90d']));
        $this->assertSame('Week starting', $data->table('volume')['columns'][0]);
        $this->assertStringStartsWith('Week of ', $data->table('volume')['rows'][0][0]);
        $this->assertSame(7, array_sum(array_map(fn ($r) => (int) $r[1], $data->table('volume')['rows'])));
    }

    // ------------------------------------------------------------------ compare, empty, spam

    public function test_compare_adds_the_previous_period_as_a_dashed_neutral_line(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin());

        $plain = (new ReportData(ReportState::from($this->state())))->chart('volume');
        $this->assertCount(1, $plain['data']['datasets']);

        $data = new ReportData(ReportState::from($this->state(['compare' => true])));
        $chart = $data->chart('volume');
        $this->assertCount(2, $chart['data']['datasets']);
        $previous = $chart['data']['datasets'][1];
        $this->assertSame('previous', $previous['slcRole']);
        $this->assertSame('line', $previous['type']);
        $this->assertSame([4, 4], $previous['borderDash']);
        $this->assertSame(2, array_sum(array_filter($previous['data'])));
        $this->assertTrue($chart['options']['plugins']['legend']['display']);
        $this->assertSame('Previous period', $data->table('volume')['columns'][2]);

        Livewire::test(ReportChart::class, ['report' => 'volume', 'state' => $this->state(['compare' => true])])
            ->assertSee('Previous period');
    }

    public function test_the_empty_state_has_one_line_and_change_range(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/reports')->assertOk()
            ->assertSee('No requests in this period')
            ->assertSee('Change range')
            ->assertSee('data-chart-empty', false)
            ->assertDontSee('data-report-csv', false)
            ->assertDontSee('<canvas', false);

        Livewire::test(ReportTable::class, ['report' => 'source_pages', 'state' => $this->state()])
            ->assertSee('No requests in this period');
    }

    public function test_spam_is_excluded_except_in_the_spam_rate(): void
    {
        $this->fixtures();
        $data = new ReportData(ReportState::from($this->state()));

        $sources = $data->table('source_pages')['rows'];
        $this->assertSame(['/en/', '2', '40%'], $sources[0]);
        $spam = $data->spamRate();
        $this->assertSame(1, $spam['spam']);
        $this->assertSame(6, $spam['all']);
    }

    // ------------------------------------------------------------------ CSV = the table on screen

    public function test_the_volume_csv_equals_the_filtered_table(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin());
        $state = $this->state(['locale' => 'en', 'compare' => true]);

        $component = Livewire::test(ReportChart::class, ['report' => 'volume', 'state' => $state]);
        $shown = $this->tableFromHtml($component->html(), 'volume');
        $csv = $this->csvOf($component->call('export'));

        $this->assertStringStartsWith(ReportCsv::BOM, $csv);
        $this->assertSame($shown, ReportCsv::parse($csv));
        $this->assertCount(30, $shown['rows']);
        $this->assertSame(3, array_sum(array_map(fn ($r) => (int) $r[1], $shown['rows'])));
        $component->assertFileDownloaded('summit-report-volume-2026-10-14-to-2026-11-12.csv');
    }

    public function test_the_matter_type_csv_equals_the_filtered_table(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin());
        $state = $this->state(['preferred_language' => ReportState::NOT_CHOSEN]);

        $component = Livewire::test(ReportChart::class, ['report' => 'matter', 'state' => $state]);
        $shown = $this->tableFromHtml($component->html(), 'matter');
        $csv = ReportCsv::parse($this->csvOf($component->call('export')));

        $this->assertSame($shown, $csv);
        $this->assertSame(['Matter type', 'Requests', 'Share'], $shown['columns']);
        // most first; Other matter, then Not chosen, last
        $names = array_column($shown['rows'], 0);
        $this->assertCount(4, $names);
        $this->assertSame(['Other matter', 'Not chosen'], array_slice($names, -2));
    }

    public function test_the_sources_csv_equals_the_filtered_table_and_guards_formulas(): void
    {
        $this->fixtures();
        $this->actingAs($this->admin());
        $state = $this->state(['source_page' => '/en/']);

        $pages = Livewire::test(ReportTable::class, ['report' => 'source_pages', 'state' => $state]);
        $this->assertSame($this->tableFromHtml($pages->html(), 'source_pages'), ReportCsv::parse($this->csvOf($pages->call('export'))));
        $this->assertSame([['/en/', '2', '100%']], $this->tableFromHtml($pages->html(), 'source_pages')['rows']);

        $referrers = Livewire::test(ReportTable::class, ['report' => 'referrers', 'state' => $state]);
        $shown = $this->tableFromHtml($referrers->html(), 'referrers');
        $csv = ReportCsv::parse($this->csvOf($referrers->call('export')));
        // the table shows the referrer as recorded; the CSV cell starts with an apostrophe so no spreadsheet runs it
        $formula = array_values(array_filter($shown['rows'], fn ($r) => str_starts_with($r[0], '=')))[0];
        $this->assertContains("'".$formula[0], array_column($csv['rows'], 0));
        $guarded = $csv;
        foreach ($guarded['rows'] as &$row) {
            $row[0] = ltrim($row[0], "'");
        }
        $this->assertSame($shown, $guarded);
    }

    public function test_csv_export_needs_the_export_permission(): void
    {
        $this->fixtures();
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->save();
        $this->actingAs($user);

        Livewire::test(ReportChart::class, ['report' => 'volume', 'state' => $this->state()])->assertForbidden();
    }

    public function test_every_report_has_a_table_with_the_charts_numbers(): void
    {
        $this->fixtures();
        $data = new ReportData(ReportState::from($this->state()));
        foreach (ReportData::TABS as $reports) {
            foreach ($reports as $key) {
                $table = $data->table($key);
                $this->assertNotEmpty($table['columns'], $key);
                foreach ($table['rows'] as $row) {
                    $this->assertCount(count($table['columns']), $row, $key);
                }
                if (ReportData::isChart($key)) {
                    $chart = $data->chart($key);
                    $this->assertNotContains($chart['type'], ['pie', 'doughnut', 'polarArea', 'radar'], $key);
                    foreach ($chart['data']['datasets'] as $ds) {
                        if (($ds['type'] ?? $chart['type']) === 'bar') {
                            $this->assertSame(24, $ds['maxBarThickness'], $key);
                            $this->assertSame('start', $ds['borderSkipped'], $key);
                        }
                    }
                }
            }
        }
        // the matter chart's numbers are the table's
        $this->assertSame(
            array_map(fn ($r) => (int) $r[1], $data->table('matter')['rows']),
            $data->chart('matter')['data']['datasets'][0]['data'],
        );
    }

    public function test_csv_guard(): void
    {
        $this->assertSame("'=1+1", ReportCsv::guard('=1+1'));
        $this->assertSame("'@SUM(A1)", ReportCsv::guard('@SUM(A1)'));
        $this->assertSame("'+x", ReportCsv::guard('+x'));
        $this->assertSame('-3', ReportCsv::guard('-3'));
        $this->assertSame('12.5%', ReportCsv::guard('12.5%'));
        $this->assertSame('/en/', ReportCsv::guard('/en/'));
    }
}
