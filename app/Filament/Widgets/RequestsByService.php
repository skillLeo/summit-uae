<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStats;
use Filament\Support\ArrayRecord;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\HtmlString;

/**
 * Requests per matter type chosen on the form (spam excluded): S01–S11 with their approved service
 * titles, then the approved "other" and "unsure" options and "Not chosen". Last 7 days, last 30 days,
 * all time, and the all-time share drawn as a <meter> (a real value with an accessible name, no chart
 * library, no inline styles).
 */
class RequestsByService extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.by_service.heading'))
            ->description(__('admin.by_service.description'))
            ->records(fn (): array => $this->rows())
            ->paginated(false)
            ->columns([
                TextColumn::make('code')
                    ->label('')
                    ->fontFamily(FontFamily::Mono)
                    ->color('gray')
                    ->extraCellAttributes(['class' => 'slc-code-cell'])
                    ->formatStateUsing(fn (?string $state): string => preg_match('/^S\d{2}$/', (string) $state) ? (string) $state : ''),
                TextColumn::make('label')
                    ->label(__('admin.by_service.service'))
                    ->wrap()
                    ->url(fn (array $record): ?string => $record['filter'] !== null
                        ? EnquiryResource::getUrl('index', ['filters' => ['matter_type' => ['values' => [$record['filter']]]]])
                        : null),
                TextColumn::make('week')
                    ->label(__('admin.period.week'))
                    ->numeric()
                    ->alignEnd()
                    ->extraCellAttributes(['class' => 'slc-num']),
                TextColumn::make('month')
                    ->label(__('admin.period.month'))
                    ->numeric()
                    ->alignEnd()
                    ->extraCellAttributes(['class' => 'slc-num']),
                TextColumn::make('all')
                    ->label(__('admin.period.all'))
                    ->numeric()
                    ->alignEnd()
                    ->extraCellAttributes(['class' => 'slc-num']),
                TextColumn::make('share')
                    ->label(__('admin.by_service.share'))
                    ->html()
                    ->formatStateUsing(fn (array $record): HtmlString => $this->meter($record))
                    ->extraCellAttributes(['class' => 'slc-share-cell']),
            ]);
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $week = EnquiryStats::countBy('matter_type', 7);
        $month = EnquiryStats::countBy('matter_type', 30);
        $all = EnquiryStats::countBy('matter_type');
        $total = array_sum($all);

        $rows = [];
        foreach ([...EnquiryLabels::serviceCodes(), ...EnquiryLabels::EXTRA_MATTERS, ''] as $code) {
            $count = $all[$code] ?? 0;
            $rows[] = [
                ArrayRecord::getKeyName() => $code !== '' ? $code : 'none',
                'code' => $code,
                'label' => EnquiryLabels::matter($code !== '' ? $code : null),
                'filter' => $code !== '' ? $code : null,
                'week' => $week[$code] ?? 0,
                'month' => $month[$code] ?? 0,
                'all' => $count,
                'share' => $total > 0 ? $count / $total : 0.0,
            ];
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $record */
    private function meter(array $record): HtmlString
    {
        $share = (float) $record['share'];
        $percent = (int) round($share * 100);
        $label = e($record['label'].': '.$percent.'%');

        return new HtmlString(
            '<span class="slc-share">'
            .'<meter class="slc-share__meter" min="0" max="100" value="'.$percent.'" aria-label="'.$label.'"></meter>'
            .'<span class="slc-share__value" aria-hidden="true"><bdi dir="ltr">'.$percent.'%</bdi></span>'
            .'</span>'
        );
    }
}
