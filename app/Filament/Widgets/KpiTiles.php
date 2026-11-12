<?php

namespace App\Filament\Widgets;

use App\Filament\Cms\Support\CmsAccess;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\RequestFormat;
use App\Reports\Kpi;
use App\Reports\Period;
use Filament\Widgets\Widget;

/**
 * Row 1 of the dashboard (docs/briefs/design-2.1-and-admin-cms.md §11.1, §11.4 "KPI tile"): four tiles from
 * App\Reports\Kpi. Label 14/500 in ink-2 without an icon, value 32/600, a change line in ink-3 with a small up or down
 * arrow, colour only where the KPI has a good direction (time to first review: lower is better), a sparkline only
 * with 7 or more points (2px, one hue, no fill). Viewers get the same tiles without links.
 */
class KpiTiles extends Widget
{
    protected string $view = 'filament.widgets.kpi-tiles';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return CmsAccess::can('dashboard', 'view') && CmsAccess::can('requests', 'view');
    }

    /** @return list<array<string, mixed>> */
    public function tiles(): array
    {
        $kpi = new Kpi;
        $week = Period::make('7d');
        $month = Period::make('30d');
        $links = CmsAccess::can('requests', 'view') && ! CmsAccess::readOnly();

        $change = $kpi->change($week);
        $sign = fn (float|int $v): string => ($v > 0 ? '+' : ($v < 0 ? '−' : '')).RequestFormat::number(abs($v));
        $changeLine = match ($change['note']) {
            'no_earlier_data' => __('dashboard.line.no_earlier', ['change' => $sign($change['change'])]),
            'small_base' => __('dashboard.line.small_base', ['change' => $sign($change['change'])]),
            default => __('dashboard.line.change_percent', ['change' => $sign($change['change']), 'percent' => $sign((float) $change['percent']).'%']),
        };

        $waiting = $kpi->waitingNow();
        $ttfr = $kpi->ttfr($month);
        $prevTtfr = $kpi->ttfr($month->previous());
        $closed = $kpi->closed($month);
        $engagement = $kpi->engagement($month);
        $ttfrDelta = $ttfr['median'] !== null && $prevTtfr['median'] !== null ? round($ttfr['median'] - $prevTtfr['median'], 1) : null;

        return [
            [
                'key' => 'new',
                'label' => __('dashboard.tile.new'),
                'value' => RequestFormat::number($change['current'], 0),
                'line' => $changeLine,
                'trend' => $change['change'] <=> 0,
                'tone' => null,
                'spark' => array_values($kpi->series($week)),
                'url' => $links ? EnquiryResource::getUrl('index', ['activeTab' => 'all']) : null,
            ],
            [
                'key' => 'waiting',
                'label' => __('dashboard.tile.waiting'),
                'value' => RequestFormat::number($waiting['count'], 0),
                'line' => $waiting['count'] === 0 ? __('dashboard.line.none_waiting') : __('dashboard.line.oldest', ['hours' => RequestFormat::number((float) $waiting['oldest_business_hours'])]),
                'trend' => null,
                'tone' => null,
                'spark' => [],
                'url' => $links ? EnquiryResource::getUrl('index', ['activeTab' => 'new']) : null,
            ],
            [
                'key' => 'ttfr',
                'label' => __('dashboard.tile.ttfr'),
                'value' => $ttfr['median'] === null ? null : __('dashboard.line.hours', ['hours' => RequestFormat::number($ttfr['median'])]),
                'line' => $ttfr['median'] === null
                    ? __('dashboard.line.no_reviews', ['unreviewed' => $ttfr['unreviewed']])
                    : __('dashboard.line.p90', ['hours' => RequestFormat::number((float) $ttfr['p90']), 'n' => $ttfr['n'], 'unreviewed' => $ttfr['unreviewed']]),
                'sub' => $ttfrDelta === null || $ttfrDelta == 0.0 ? null : __('dashboard.line.ttfr_change', ['change' => $sign($ttfrDelta)]),
                'trend' => $ttfrDelta === null ? null : ($ttfrDelta <=> 0),
                // lower is better: down is good (success), up is bad (danger)
                'tone' => $ttfrDelta === null || $ttfrDelta == 0.0 ? null : ($ttfrDelta < 0 ? 'better' : 'worse'),
                'spark' => [],
                'url' => null,
            ],
            [
                'key' => 'closed',
                'label' => __('dashboard.tile.closed'),
                'value' => RequestFormat::number($closed, 0),
                'line' => $engagement['hidden']
                    ? __('dashboard.line.engagement_hidden', ['n' => $engagement['n']])
                    : __('dashboard.line.engagement', ['rate' => RequestFormat::number((float) $engagement['rate']), 'n' => $engagement['n']]),
                'trend' => null,
                'tone' => null,
                'spark' => [],
                'url' => $links ? EnquiryResource::getUrl('index', ['activeTab' => 'closed']) : null,
            ],
        ];
    }

    /** A 2px polyline in a 100 x 32 box; only with 7 or more points. */
    public static function sparkline(array $points): ?string
    {
        if (count($points) < 7) {
            return null;
        }
        $max = max(1, max($points));
        $step = 100 / (count($points) - 1);
        $coords = [];
        foreach (array_values($points) as $i => $v) {
            $coords[] = round($i * $step, 2).','.round(30 - ($v / $max) * 28, 2);
        }

        return implode(' ', $coords);
    }
}
