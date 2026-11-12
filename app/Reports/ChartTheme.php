<?php

namespace App\Reports;

/**
 * The one chart theme of the admin (docs/briefs/design-2.1-and-admin-cms.md §11.4), shared by every Chart.js chart
 * (Filament ChartWidget). PHP side: colours, datasets and options; JS side: resources/views/filament/reports/chart-theme
 * .blade.php (window.filamentChartJsPlugins): direct labels, the dark-mode palette swap, keyboard focus between marks,
 * no animation with reduced motion.
 *
 * Colours (checked with the palette validator of the brief): categorical by entity and never cycled (EN blue, AR
 * bronze, RU teal; "Not chosen" and "Other" a deliberate dark grey); one series bronze; ordinal ramps low to high;
 * the previous period a dashed neutral line. Text is ink, never the series colour.
 * Marks: bars at most 24px wide, 4px corners on the data end only, a 2px surface gap; lines 2px; points hidden except
 * on hover and on the last value. One y-axis from 0, hairline grid, no axis border, 12px ink-3 ticks, whole numbers.
 */
final class ChartTheme
{
    public const LIGHT = [
        'surface' => '#FFFFFF',
        'categorical' => ['en' => '#3D6FB6', 'ar' => '#B07D2B', 'ru' => '#1E9C82'],
        'none' => '#3F3F46',
        'single' => '#8A6A2E',
        'ordinal' => ['#C29B5C', '#A9813F', '#8A6A2E', '#5E4718'],
        'zero' => '#F4F4F5',
        'previous' => '#8A8A93',
        'grid' => '#E4E4E7',
        'tick' => '#6B6B73',
        'ink' => '#0B0B0C',
        'line' => '#E4E4E7',
    ];

    public const DARK = [
        'surface' => '#151517',
        'categorical' => ['en' => '#4F82CB', 'ar' => '#B07D2B', 'ru' => '#1E9C82'],
        'none' => '#C4C4CC',
        'single' => '#B07D2B',
        'ordinal' => ['#5E4718', '#8A6A2E', '#B07D2B', '#D9B26A'],
        'zero' => '#1F1F23',
        'previous' => '#71717A',
        'grid' => '#27272A',
        'tick' => '#A1A1AA',
        'ink' => '#F4F4F5',
        'line' => '#27272A',
    ];

    /** Categorical colour of an entity key (en, ar, ru; individual, business, investor); grey for "" and other. */
    public static function categorical(string $key, bool $dark = false): string
    {
        $p = $dark ? self::DARK : self::LIGHT;
        $map = ['en' => 'en', 'ar' => 'ar', 'ru' => 'ru', 'individual' => 'en', 'business' => 'ar', 'investor' => 'ru'];

        return isset($map[$key]) ? $p['categorical'][$map[$key]] : $p['none'];
    }

    /** @param  list<int|float|null>  $data */
    public static function bars(string $label, array $data, ?string $color = null, string $role = 'single'): array
    {
        return [
            'label' => $label,
            'data' => array_values($data),
            'backgroundColor' => $color ?? self::LIGHT['single'],
            'borderColor' => self::LIGHT['surface'],
            'borderWidth' => 2,
            'borderRadius' => 4,
            'borderSkipped' => 'start',
            'maxBarThickness' => 24,
            'slcRole' => $role,
        ];
    }

    /** @param  list<int|float|null>  $data */
    public static function line(string $label, array $data, ?string $color = null, string $role = 'single'): array
    {
        $points = array_fill(0, count($data), 0);
        if ($points !== []) {
            $points[count($points) - 1] = 4;    // the last value: an 8px point
        }

        return [
            'label' => $label,
            'data' => array_values($data),
            'borderColor' => $color ?? self::LIGHT['single'],
            'backgroundColor' => $color ?? self::LIGHT['single'],
            'borderWidth' => 2,
            'fill' => false,
            'tension' => 0,
            'pointRadius' => $points,
            'pointHoverRadius' => 4,
            'pointHitRadius' => 12,
            'slcRole' => $role,
        ];
    }

    /** The previous period: a dashed 1.5px neutral line. @param  list<int|float|null>  $data */
    public static function previous(string $label, array $data): array
    {
        return [
            'type' => 'line',
            'label' => $label,
            'data' => array_values($data),
            'borderColor' => self::LIGHT['previous'],
            'backgroundColor' => self::LIGHT['previous'],
            'borderWidth' => 1.5,
            'borderDash' => [4, 4],
            'fill' => false,
            'tension' => 0,
            'pointRadius' => 0,
            'pointHoverRadius' => 4,
            'pointHitRadius' => 12,
            'order' => 0,
            'slcRole' => 'previous',
        ];
    }

    /**
     * @param  array{legend?: bool, stacked?: bool, percent?: bool, horizontal?: bool, labels?: bool, line?: bool, unit?: string}  $o
     * @return array<string, mixed>
     */
    public static function options(array $o = []): array
    {
        $p = self::LIGHT;
        $horizontal = (bool) ($o['horizontal'] ?? false);
        $stacked = (bool) ($o['stacked'] ?? false);
        $percent = (bool) ($o['percent'] ?? false);
        $value = [
            'beginAtZero' => true,
            'stacked' => $stacked,
            'grid' => ['color' => $p['grid'], 'drawTicks' => false],
            'border' => ['display' => false],
            'ticks' => ['color' => $p['tick'], 'font' => ['size' => 12], 'precision' => 0, 'padding' => 8],
        ];
        if ($percent) {
            $value['max'] = 100;
            $value['ticks']['stepSize'] = 25;
        }
        $category = [
            'stacked' => $stacked,
            'grid' => ['display' => false, 'drawTicks' => false],
            'border' => ['display' => false],
            'ticks' => ['color' => $p['tick'], 'font' => ['size' => 12], 'autoSkip' => true, 'maxRotation' => 0, 'padding' => 8],
        ];

        $options = [
            'indexAxis' => $horizontal ? 'y' : 'x',
            'maintainAspectRatio' => false,
            'animation' => ['duration' => 250],
            'interaction' => ($o['line'] ?? false) ? ['mode' => 'index', 'intersect' => false] : ['mode' => 'nearest', 'intersect' => true],
            'scales' => $horizontal ? ['x' => $value, 'y' => $category] : ['x' => $category, 'y' => $value],
            'plugins' => [
                'legend' => [
                    'display' => (bool) ($o['legend'] ?? false),
                    'position' => 'bottom',
                    'align' => 'start',
                    'labels' => ['color' => $p['ink'], 'boxWidth' => 12, 'boxHeight' => 12, 'useBorderRadius' => true, 'borderRadius' => 2, 'font' => ['size' => 13]],
                ],
                'tooltip' => [
                    'backgroundColor' => $p['surface'],
                    'borderColor' => $p['line'],
                    'borderWidth' => 1,
                    'titleColor' => $p['ink'],
                    'bodyColor' => $p['ink'],
                    'padding' => 12,
                    'cornerRadius' => 8,
                    'boxPadding' => 4,
                    'usePointStyle' => true,
                    'titleFont' => ['weight' => '500'],
                ],
                'slcTheme' => [
                    'directLabels' => (bool) ($o['labels'] ?? true),
                    'percent' => $percent,
                    'unit' => $o['unit'] ?? '',
                ],
            ],
        ];

        // the Arabic admin reads right to left: the x axis runs from the right, the y axis stands on the right
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
}
