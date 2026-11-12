<?php

namespace App\Filament\Reports;

use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\User;
use App\Reports\Kpi;
use App\Reports\Period;
use Carbon\CarbonImmutable;

/**
 * The filter row of the Reports page (docs/briefs/design-2.1-and-admin-cms.md §11.2) as one value: the date range
 * (an App\Reports\Period preset, or custom from / to Dubai dates), "Compare with previous period" and the six request
 * filters of App\Reports\Kpi. The page keeps the raw form state in the URL and the session (Filament's HasFilters);
 * every chart and table gets this normalised copy, so the chart, its table and its CSV read the same figures.
 *
 * In the form "Not chosen" is the option NOT_CHOSEN (a select cannot hold ""); Kpi reads it as "".
 */
final class ReportState
{
    public const DEFAULT_PRESET = '30d';

    public const NOT_CHOSEN = '__none';

    /** Form field => Kpi filter column. */
    public const FIELDS = [
        'matter_type' => 'matter_type',
        'locale' => 'locale',
        'preferred_language' => 'preferred_language',
        'status' => 'status',
        'assignee_id' => 'assignee_id',
        'source_page' => 'source_page',
    ];

    /** Statuses a report can be filtered by (spam is never counted). */
    public const STATUSES = [Enquiry::STATUS_NEW, Enquiry::STATUS_IN_REVIEW, Enquiry::STATUS_CLOSED];

    /** @param  array<string, string>  $filters  Kpi column => value ("" = Not chosen) */
    public function __construct(
        public readonly string $preset = self::DEFAULT_PRESET,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly bool $compare = false,
        public readonly array $filters = [],
    ) {}

    /** @param  array<string, mixed>|null  $state  the page's form state (or toArray()) */
    public static function from(?array $state): self
    {
        $state ??= [];
        $preset = (string) ($state['range'] ?? self::DEFAULT_PRESET);
        if (! in_array($preset, Period::PRESETS, true)) {
            $preset = self::DEFAULT_PRESET;
        }
        $date = static fn (mixed $v): ?string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : null;
        $from = $preset === 'custom' ? $date($state['from'] ?? null) : null;
        $to = $preset === 'custom' ? $date($state['to'] ?? null) : null;
        $compare = filter_var($state['compare'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $filters = [];
        foreach (self::FIELDS as $field => $column) {
            $value = $state[$field] ?? null;
            if (! is_scalar($value) || $value === '' || $value === null) {
                continue;
            }
            $value = (string) $value;
            if ($value === self::NOT_CHOSEN) {
                if ($field !== 'status') {
                    $filters[$column] = '';
                }

                continue;
            }
            $valid = match ($field) {
                'matter_type' => array_key_exists($value, EnquiryLabels::matterOptions()),
                'locale', 'preferred_language' => in_array($value, User::ADMIN_LOCALES, true),
                'status' => in_array($value, self::STATUSES, true),
                'assignee_id' => ctype_digit($value),
                default => mb_strlen($value) <= 2048,
            };
            if ($valid) {
                $filters[$column] = $value;
            }
        }

        return new self($preset, $from, $to, $compare, $filters);
    }

    /** @return array<string, mixed> the form state this value stands for (round-trips through from()) */
    public function toArray(): array
    {
        $out = ['range' => $this->preset, 'from' => $this->from, 'to' => $this->to, 'compare' => $this->compare];
        foreach (self::FIELDS as $field => $column) {
            if (array_key_exists($column, $this->filters)) {
                $out[$field] = $this->filters[$column] === '' ? self::NOT_CHOSEN : $this->filters[$column];
            }
        }

        return $out;
    }

    public function hash(): string
    {
        return substr(md5((string) json_encode($this->toArray())), 0, 12);
    }

    public function period(?CarbonImmutable $now = null): Period
    {
        return Period::make($this->preset, $this->from, $this->to, $now);
    }

    public function kpi(?CarbonImmutable $now = null): Kpi
    {
        return new Kpi($this->filters, now: $now);
    }

    // ------------------------------------------------------------------ the printed / on-screen summary

    /** "30 days: 14 October 2026 to 12 November 2026", the last day included. */
    public static function periodText(Period $p): string
    {
        $format = static fn (CarbonImmutable $d): string => $d->locale(app()->getLocale())->translatedFormat(AdminFormat::date());

        return (string) __('reports.summary.period', [
            'from' => $format($p->start),
            'to' => $format($p->end->subDay()),
        ]);
    }

    /** One line: the range, the comparison and every filter that is set. */
    public function summary(?CarbonImmutable $now = null): string
    {
        $period = $this->period($now);
        $parts = [__('reports.range.'.$this->preset).': '.self::periodText($period)];
        if ($this->compare) {
            $parts[] = (string) __('reports.summary.compare', ['period' => self::periodText($period->previous())]);
        }
        foreach ($this->filterLabels() as $label => $value) {
            $parts[] = $label.': '.$value;
        }
        if ($this->filters === []) {
            $parts[] = (string) __('reports.summary.no_filters');
        }

        return implode((string) __('reports.summary.separator'), $parts);
    }

    /** @return array<string, string> filter label => chosen value label */
    public function filterLabels(): array
    {
        $out = [];
        foreach ($this->filters as $column => $value) {
            $label = (string) __('reports.filter.'.$column);
            $out[$label] = match ($column) {
                'matter_type' => EnquiryLabels::matter($value),
                'locale', 'preferred_language' => EnquiryLabels::language($value),
                'status' => EnquiryStatus::label($value),
                'assignee_id' => $value === '' ? (string) __('reports.filter.unassigned') : (string) (User::query()->whereKey($value)->value('name') ?? $value),
                'source_page' => $value === '' ? (string) __('reports.value.none_recorded') : $value,
                default => $value,
            };
        }

        return $out;
    }
}
