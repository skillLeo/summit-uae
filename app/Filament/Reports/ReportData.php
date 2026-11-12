<?php

namespace App\Filament\Reports;

use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStatus;
use App\Filament\Support\RequestFormat;
use App\Models\Enquiry;
use App\Models\User;
use App\Reports\ChartTheme;
use App\Reports\Kpi;
use App\Reports\Period;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Every chart and table of the Reports page (docs/briefs/design-2.1-and-admin-cms.md §11.2, §11.4), built once per
 * report from App\Reports\Kpi: the same pass gives the Chart.js datasets, the table shown by "Table" and the CSV, so
 * the three never disagree. Table cells are display strings (Western digits in the admin language, Dubai dates); the
 * CSV writes exactly these strings (ReportCsv).
 *
 * A report: [
 *   'key', 'tab', 'kind' => chart | heatmap | bars (a table with bars in cells), 'wide' => bool,
 *   'columns' => list<string>, 'rows' => list<list<string>>, 'empty' => bool, 'empty_line' => lang key,
 *   chart: 'type', 'labels', 'datasets', 'options', 'height';
 *   bars: 'bar' => list<float> (inline size 0-100 per row), 'bar_column' => int;
 *   heatmap: 'grid' => [weekday => [hour => count]], 'levels' => [weekday => [hour => 0-4]], 'max' => int
 * ]
 */
final class ReportData
{
    /** Tab => reports in display order. */
    public const TABS = [
        'volume' => ['volume', 'heatmap'],
        'mix' => ['matter', 'languages', 'contact'],
        'workflow' => ['pipeline', 'ttfr', 'target', 'workload', 'outcomes'],
        'sources' => ['source_pages', 'referrers', 'campaigns'],
    ];

    /** Reports drawn in Blade (the heatmap, tables with bars in cells); every other one is a Chart.js chart. */
    public const BLADE = ['heatmap', 'workload', 'source_pages', 'referrers', 'campaigns'];

    /** Report => the Kpi::top() column of a source table. */
    private const SOURCES = ['source_pages' => 'source_page', 'referrers' => 'referrer', 'campaigns' => 'utm_campaign'];

    private readonly Kpi $kpi;

    private readonly Period $period;

    /** @var array<string, array<string, mixed>> */
    private array $built = [];

    public function __construct(public readonly ReportState $state, ?Kpi $kpi = null, ?CarbonImmutable $now = null)
    {
        $this->kpi = $kpi ?? $state->kpi($now);
        $this->period = $state->period($now ?? $this->kpi->now());
    }

    public static function exists(string $key): bool
    {
        foreach (self::TABS as $keys) {
            if (in_array($key, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isChart(string $key): bool
    {
        return self::exists($key) && ! in_array($key, self::BLADE, true);
    }

    public function period(): Period
    {
        return $this->period;
    }

    public function kpi(): Kpi
    {
        return $this->kpi;
    }

    /** @return array<string, mixed> */
    public function report(string $key): array
    {
        if (! self::exists($key)) {
            throw new InvalidArgumentException("Unknown report [{$key}].");
        }

        return $this->built[$key] ??= ['key' => $key] + match ($key) {
            'volume' => $this->volume(),
            'heatmap' => $this->heatmap(),
            'matter' => $this->matter(),
            'languages' => $this->languages(),
            'contact' => $this->contact(),
            'pipeline' => $this->pipeline(),
            'ttfr' => $this->ttfr(),
            'target' => $this->target(),
            'workload' => $this->workload(),
            'outcomes' => $this->outcomes(),
            default => $this->source($key),
        };
    }

    /** @return array{columns: list<string>, rows: list<list<string>>} what "Table" shows and the CSV holds */
    public function table(string $key): array
    {
        $r = $this->report($key);

        return ['columns' => $r['columns'], 'rows' => $r['rows']];
    }

    /** @return array{type: string, data: array<string, mixed>, options: array<string, mixed>, height: string} */
    public function chart(string $key): array
    {
        $r = $this->report($key);
        if (($r['kind'] ?? null) !== 'chart') {
            throw new InvalidArgumentException("[{$key}] is not a chart.");
        }

        return ['type' => $r['type'], 'data' => ['labels' => $r['labels'], 'datasets' => $r['datasets']], 'options' => $r['options'], 'height' => $r['height']];
    }

    public function heading(string $key): string
    {
        return (string) __('reports.chart.'.$key.'.heading');
    }

    public function description(string $key): string
    {
        $params = $key === 'target' ? ['hours' => self::n($this->withinTarget()['target'])] : [];
        $bucket = $this->period->bucket();

        return match ($key) {
            'volume', 'target' => (string) __('reports.chart.'.$key.'.description_'.$bucket, $params),
            'ttfr' => $this->ttfrSummary(),
            default => (string) __('reports.chart.'.$key.'.description', $params),
        };
    }

    // ------------------------------------------------------------------ formatting

    public static function n(int|float $value): string
    {
        return RequestFormat::number((float) $value, 0);
    }

    public static function pct(?float $value): string
    {
        return $value === null ? (string) __('reports.value.no_data') : (string) __('reports.value.percent', ['value' => RequestFormat::number($value)]);
    }

    /**
     * A category label as lines (Chart.js draws an array as a multi-line tick), so a few long labels show in full
     * instead of being skipped or clipped. The table keeps the label on one line.
     *
     * @return string|list<string>
     */
    private static function wrap(string $label, int $width): string|array
    {
        if (mb_strlen($label) <= $width) {
            return $label;
        }
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', $label) as $word) {
            if ($line !== '' && mb_strlen($line.' '.$word) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : $line.' '.$word;
            }
        }
        $lines[] = $line;

        return $lines;
    }

    /** @param  list<string>  $labels  @return list<string|list<string>> */
    private static function wrapAll(array $labels, int $width): array
    {
        return array_map(fn (string $l) => self::wrap($l, $width), $labels);
    }

    private function share(int $count, int $total): float
    {
        return $total === 0 ? 0.0 : round($count / $total * 100, 1);
    }

    private function bucketLabel(string $day): string
    {
        $date = CarbonImmutable::parse($day, Period::zone())->locale(app()->getLocale());

        return $this->period->bucket() === 'week'
            ? (string) __('reports.value.week_of', ['date' => $date->translatedFormat('j M')])
            : $date->translatedFormat('j M');
    }

    private function bucketColumn(): string
    {
        return (string) __('reports.column.'.($this->period->bucket() === 'week' ? 'week' : 'day'));
    }

    /**
     * ChartTheme::options() plus the right-to-left mirror for Arabic: the category (or value) axis runs from the
     * right, the y axis stands on the right, the legend and tooltip read right to left.
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    private function options(array $o): array
    {
        $options = ChartTheme::options($o);
        // room for the direct labels on the bar ends and the last point, and for the outer tick labels
        $options['layout'] = ['padding' => ['top' => 20, 'left' => 12, 'right' => ($o['horizontal'] ?? false) ? 32 : 28]];
        if (app()->getLocale() === 'ar') {
            $options['layout']['padding'] = ['top' => 20, 'left' => ($o['horizontal'] ?? false) ? 32 : 28, 'right' => 12];
        }
        if ($o['all_ticks'] ?? false) {
            $axis = ($o['horizontal'] ?? false) ? 'y' : 'x';
            $options['scales'][$axis]['ticks']['autoSkip'] = false;
        }
        if (($o['percent'] ?? false) && ($o['horizontal'] ?? false)) {
            $options['scales']['x']['max'] = 100;
        }
        if (app()->getLocale() === 'ar') {
            $options['scales']['x']['reverse'] = true;
            $options['scales']['y']['position'] = 'right';
            $options['plugins']['legend']['rtl'] = true;
            $options['plugins']['legend']['textDirection'] = 'rtl';
            $options['plugins']['tooltip']['rtl'] = true;
            $options['plugins']['tooltip']['textDirection'] = 'rtl';
        }

        return $options;
    }

    // ------------------------------------------------------------------ Volume

    private function volume(): array
    {
        $series = $this->kpi->series($this->period);
        $labels = array_map(fn (string $d): string => $this->bucketLabel($d), array_keys($series));
        $values = array_values($series);
        $datasets = [ChartTheme::bars((string) __('reports.series.requests'), $values)];
        $columns = [$this->bucketColumn(), (string) __('reports.column.requests')];
        $previous = [];
        if ($this->state->compare) {
            $prev = array_values($this->kpi->series($this->period->previous()));
            // aligned by position: bucket i of this period against bucket i of the one before
            for ($i = 0; $i < count($values); $i++) {
                $previous[$i] = $prev[$i] ?? null;
            }
            $datasets[] = ChartTheme::previous((string) __('reports.series.previous'), $previous);
            $columns[] = (string) __('reports.column.previous');
        }
        $rows = [];
        foreach ($labels as $i => $label) {
            $row = [$label, self::n($values[$i])];
            if ($this->state->compare) {
                $row[] = $previous[$i] === null ? (string) __('reports.value.no_data') : self::n($previous[$i]);
            }
            $rows[] = $row;
        }

        return [
            'tab' => 'volume', 'kind' => 'chart', 'wide' => true,
            'type' => 'bar', 'labels' => $labels, 'datasets' => $datasets,
            'options' => $this->options(['legend' => $this->state->compare, 'labels' => count($values) <= 12, 'line' => $this->state->compare]),
            'height' => '300px',
            'columns' => $columns, 'rows' => $rows,
            'empty' => array_sum($values) === 0 && array_sum(array_filter($previous)) === 0,
            'empty_line' => 'reports.empty.line',
        ];
    }

    /** ISO weekday (1 = Monday) => its name in the admin language. */
    public static function weekday(int $iso, string $format = 'l'): string
    {
        return CarbonImmutable::parse('2026-11-09')->addDays($iso - 1)->locale(app()->getLocale())->translatedFormat($format);
    }

    private function heatmap(): array
    {
        $grid = $this->kpi->heatmap($this->period);
        $max = 0;
        foreach ($grid as $hours) {
            $max = max($max, ...$hours);
        }
        $columns = [(string) __('reports.column.weekday')];
        foreach (range(0, 23) as $h) {
            $columns[] = sprintf('%02d', $h);
        }
        $rows = $levels = [];
        foreach ($grid as $day => $hours) {
            $row = [self::weekday($day)];
            foreach ($hours as $h => $count) {
                $row[] = self::n($count);
                // the ordinal ramp in four even steps of the busiest hour; 0 keeps the neutral cell
                $levels[$day][$h] = $count === 0 || $max === 0 ? 0 : max(1, (int) ceil($count / $max * 4));
            }
            $rows[] = $row;
        }

        return [
            'tab' => 'volume', 'kind' => 'heatmap', 'wide' => true,
            'grid' => $grid, 'levels' => $levels, 'max' => $max,
            'columns' => $columns, 'rows' => $rows,
            'empty' => $max === 0, 'empty_line' => 'reports.empty.line',
        ];
    }

    // ------------------------------------------------------------------ Mix

    private function matter(): array
    {
        $shares = $this->kpi->shares($this->period, 'matter_type');
        // most first; "Other Matter" and "Not chosen" last, in that order
        $keys = array_keys($shares);
        usort($keys, function (string $a, string $b) use ($shares): int {
            $rank = fn (string $k): int => match ($k) {
                '' => 2,
                'other' => 1,
                default => 0,
            };

            return [$rank($a), -$shares[$a]['count'], $a] <=> [$rank($b), -$shares[$b]['count'], $b];
        });
        $labels = $counts = $rows = $colors = [];
        foreach ($keys as $k) {
            $labels[] = $label = EnquiryLabels::matter($k);
            $counts[] = $shares[$k]['count'];
            $colors[] = $k === '' || $k === 'other' ? ChartTheme::LIGHT['none'] : ChartTheme::LIGHT['single'];
            $rows[] = [$label, self::n($shares[$k]['count']), self::pct($shares[$k]['share'])];
        }
        $dataset = ChartTheme::bars((string) __('reports.series.requests'), $counts);
        $dataset['backgroundColor'] = $colors;

        return [
            'tab' => 'mix', 'kind' => 'chart', 'wide' => true,
            'type' => 'bar', 'labels' => self::wrapAll($labels, 20), 'datasets' => [$dataset],
            'options' => $this->options(['horizontal' => true, 'labels' => count($counts) <= 12, 'all_ticks' => true]),
            'height' => max(160, 36 * count($counts) + 40).'px',
            'columns' => [(string) __('reports.column.matter'), (string) __('reports.column.requests'), (string) __('reports.column.share')],
            'rows' => $rows,
            'empty' => array_sum($counts) === 0, 'empty_line' => 'reports.empty.line',
        ];
    }

    private function languages(): array
    {
        $order = ['en', 'ar', 'ru', ''];
        $site = $this->kpi->shares($this->period, 'locale', $order);
        $preferred = $this->kpi->shares($this->period, 'preferred_language', $order);
        $datasets = $rows = [];
        foreach ($order as $code) {
            $name = EnquiryLabels::language($code);
            $datasets[] = ChartTheme::bars($name, [$site[$code]['share'] ?? 0.0, $preferred[$code]['share'] ?? 0.0], ChartTheme::categorical($code), $code === '' ? 'none' : 'categorical');
            $rows[] = [
                $name,
                self::n($site[$code]['count'] ?? 0), self::pct($site[$code]['share'] ?? 0.0),
                self::n($preferred[$code]['count'] ?? 0), self::pct($preferred[$code]['share'] ?? 0.0),
            ];
        }
        $total = array_sum(array_column($site, 'count'));

        return [
            'tab' => 'mix', 'kind' => 'chart', 'wide' => false,
            'type' => 'bar',
            'labels' => [(string) __('reports.column.site'), (string) __('reports.column.preferred')],
            'datasets' => $datasets,
            'options' => $this->options(['horizontal' => true, 'stacked' => true, 'percent' => true, 'legend' => true, 'labels' => false]),
            'height' => '200px',
            'columns' => [
                (string) __('reports.column.language'),
                (string) __('reports.column.site'), (string) __('reports.column.site_share'),
                (string) __('reports.column.preferred'), (string) __('reports.column.preferred_share'),
            ],
            'rows' => $rows,
            'empty' => $total === 0, 'empty_line' => 'reports.empty.line',
        ];
    }

    private function contact(): array
    {
        $methods = $this->kpi->contactMethods($this->period);
        $total = $this->kpi->received($this->period);
        $labels = $rows = [];
        foreach ($methods as $key => $count) {
            $labels[] = $label = (string) __('reports.value.'.$key);
            $rows[] = [$label, self::n($count), self::pct($this->share($count, $total))];
        }

        return [
            'tab' => 'mix', 'kind' => 'chart', 'wide' => false,
            'type' => 'bar', 'labels' => self::wrapAll($labels, 14),
            'datasets' => [ChartTheme::bars((string) __('reports.series.requests'), array_values($methods))],
            'options' => $this->options(['labels' => true, 'all_ticks' => true]),
            'height' => '240px',
            'columns' => [(string) __('reports.column.contact'), (string) __('reports.column.requests'), (string) __('reports.column.share')],
            'rows' => $rows,
            'empty' => array_sum($methods) === 0, 'empty_line' => 'reports.empty.line',
        ];
    }

    // ------------------------------------------------------------------ Workflow

    private function pipeline(): array
    {
        $pipeline = $this->kpi->pipeline($this->period);
        $total = array_sum($pipeline);
        // three statuses on the ordinal ramp, low (New) to high (Closed)
        $ramp = [Enquiry::STATUS_NEW => 1, Enquiry::STATUS_IN_REVIEW => 2, Enquiry::STATUS_CLOSED => 3];
        $datasets = $rows = [];
        foreach ($pipeline as $status => $count) {
            $label = EnquiryStatus::label($status);
            $datasets[] = ChartTheme::bars($label, [$this->share($count, $total)], ChartTheme::LIGHT['ordinal'][$ramp[$status]], 'ordinal');
            $rows[] = [$label, self::n($count), self::pct($this->share($count, $total))];
        }

        return [
            'tab' => 'workflow', 'kind' => 'chart', 'wide' => false,
            'type' => 'bar', 'labels' => [(string) __('reports.series.received')],
            'datasets' => $datasets,
            'options' => $this->options(['horizontal' => true, 'stacked' => true, 'percent' => true, 'legend' => true, 'labels' => false]),
            'height' => '150px',
            'columns' => [(string) __('reports.column.status'), (string) __('reports.column.requests'), (string) __('reports.column.share')],
            'rows' => $rows,
            'empty' => $total === 0, 'empty_line' => 'reports.empty.line',
        ];
    }

    private function ttfr(): array
    {
        $histogram = $this->kpi->ttfrHistogram($this->period);
        $labels = $rows = [];
        foreach ($histogram as $bucket => $count) {
            $labels[] = $label = (string) __('reports.histogram.'.$bucket);
            $rows[] = [$label, self::n($count)];
        }
        $received = $this->kpi->received($this->period);

        return [
            'tab' => 'workflow', 'kind' => 'chart', 'wide' => false,
            'type' => 'bar', 'labels' => self::wrapAll($labels, 12),
            'datasets' => [ChartTheme::bars((string) __('reports.series.reviewed'), array_values($histogram))],
            'options' => $this->options(['labels' => true, 'all_ticks' => true]),
            'height' => '240px',
            'columns' => [(string) __('reports.column.ttfr'), (string) __('reports.column.requests')],
            'rows' => $rows,
            'empty' => array_sum($histogram) === 0,
            'empty_line' => $received === 0 ? 'reports.empty.line' : 'reports.empty.no_reviews',
        ];
    }

    private function ttfrSummary(): string
    {
        $t = $this->kpi->ttfr($this->period);

        return $t['median'] === null
            ? (string) __('reports.chart.ttfr.none', ['unreviewed' => self::n($t['unreviewed'])])
            : (string) __('reports.chart.ttfr.description', [
                'median' => RequestFormat::number($t['median']),
                'p90' => RequestFormat::number((float) $t['p90']),
                'n' => self::n($t['n']),
                'unreviewed' => self::n($t['unreviewed']),
            ]);
    }

    /** @var array{share: float|null, met: int, missed: int, excluded: int, target: int}|null */
    private ?array $target = null;

    /** @return array{share: float|null, met: int, missed: int, excluded: int, target: int} the tile above the trend */
    public function withinTarget(): array
    {
        return $this->target ??= $this->kpi->withinTarget($this->period);
    }

    /** @return array{rate: float|null, spam: int, all: int} */
    public function spamRate(): array
    {
        return $this->kpi->spamRate($this->period);
    }

    private function target(): array
    {
        $trend = $this->kpi->withinTargetTrend($this->period);
        $labels = array_map(fn (string $d): string => $this->bucketLabel($d), array_keys($trend));
        $values = array_values($trend);
        $rows = [];
        foreach ($labels as $i => $label) {
            $rows[] = [$label, self::pct($values[$i])];
        }

        return [
            'tab' => 'workflow', 'kind' => 'chart', 'wide' => false,
            'type' => 'line', 'labels' => $labels,
            'datasets' => [ChartTheme::line((string) __('reports.series.within'), $values) + ['spanGaps' => true]],
            'options' => $this->options(['line' => true, 'percent' => true, 'labels' => true]),
            'height' => '240px',
            'columns' => [$this->bucketColumn(), (string) __('reports.column.within')],
            'rows' => $rows,
            'empty' => array_filter($values, fn ($v) => $v !== null) === [], 'empty_line' => 'reports.empty.line',
        ];
    }

    private function workload(): array
    {
        $workload = $this->kpi->workload($this->period);
        $names = User::query()->whereIn('id', $workload->pluck('assignee_id')->filter()->all())->get()->keyBy('id');
        $max = max(1, (int) $workload->max('received'));
        $rows = $bars = [];
        foreach ($workload as $w) {
            $rows[] = [
                RequestFormat::assignee($w['assignee_id'] === null ? null : $names->get($w['assignee_id'])),
                self::n($w['received']), self::n($w['open']), self::n($w['closed']),
            ];
            $bars[] = round($w['received'] / $max * 100, 1);
        }

        return [
            'tab' => 'workflow', 'kind' => 'bars', 'wide' => false,
            'columns' => [(string) __('reports.column.assignee'), (string) __('reports.column.received'), (string) __('reports.column.open'), (string) __('reports.column.closed')],
            'rows' => $rows, 'bar' => $bars, 'bar_column' => 1,
            'empty' => $rows === [], 'empty_line' => 'reports.empty.line',
        ];
    }

    private function outcomes(): array
    {
        $outcomes = $this->kpi->outcomes($this->period);
        $labels = $rows = [];
        foreach ($outcomes as $outcome => $count) {
            $labels[] = $label = (string) __('requests.outcome.'.$outcome);
            $rows[] = [$label, self::n($count)];
        }

        return [
            'tab' => 'workflow', 'kind' => 'chart', 'wide' => false,
            'type' => 'bar', 'labels' => self::wrapAll($labels, 12),
            'datasets' => [ChartTheme::bars((string) __('reports.series.closed'), array_values($outcomes))],
            'options' => $this->options(['labels' => true, 'all_ticks' => true]),
            'height' => '240px',
            'columns' => [(string) __('reports.column.outcome'), (string) __('reports.column.requests')],
            'rows' => $rows,
            'empty' => array_sum($outcomes) === 0,
            'empty_line' => $this->kpi->received($this->period) === 0 ? 'reports.empty.line' : 'reports.empty.no_closed',
        ];
    }

    // ------------------------------------------------------------------ Sources

    private function source(string $key): array
    {
        $top = $this->kpi->top($this->period, self::SOURCES[$key]);
        $total = $this->kpi->received($this->period);
        $max = max(1, $top === [] ? 1 : max($top));
        $rows = $bars = [];
        foreach ($top as $value => $count) {
            $label = match ((string) $value) {
                '__other' => (string) __('reports.value.other'),
                '' => (string) __('reports.value.none_recorded'),
                default => (string) $value,
            };
            $rows[] = [$label, self::n($count), self::pct($this->share($count, $total))];
            $bars[] = round($count / $max * 100, 1);
        }

        return [
            'tab' => 'sources', 'kind' => 'bars', 'wide' => false,
            'columns' => [(string) __('reports.column.'.$key), (string) __('reports.column.requests'), (string) __('reports.column.share')],
            'rows' => $rows, 'bar' => $bars, 'bar_column' => 1,
            'empty' => $rows === [], 'empty_line' => 'reports.empty.line',
        ];
    }
}
