<?php

namespace App\Filament\Widgets;

use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStats;
use App\Models\User;
use Filament\Support\ArrayRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Requests per language (spam excluded): the site version the form was sent from (enquiries.locale)
 * and the contact language the visitor preferred (enquiries.preferred_language, optional on the form).
 * Language names are the approved form options in the admin's language.
 */
class RequestsByLanguage extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.by_language.heading'))
            ->description(__('admin.by_language.description'))
            ->records(fn (): array => $this->rows())
            ->paginated(false)
            ->columns([
                TextColumn::make('label')
                    ->label(__('admin.by_language.language'))
                    ->weight(fn (array $record): FontWeight => $record[ArrayRecord::getKeyName()] === 'total' ? FontWeight::Bold : FontWeight::Medium),
                TextColumn::make('site')
                    ->label(__('admin.by_language.site'))
                    ->numeric()
                    ->alignEnd()
                    ->extraCellAttributes(['class' => 'slc-num']),
                TextColumn::make('preferred')
                    ->label(__('admin.by_language.preferred'))
                    ->numeric()
                    ->alignEnd()
                    ->extraCellAttributes(['class' => 'slc-num']),
            ]);
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $site = EnquiryStats::countBy('locale');
        $preferred = EnquiryStats::countBy('preferred_language');

        $rows = [];
        foreach (User::ADMIN_LOCALES as $code) {
            $rows[] = [
                ArrayRecord::getKeyName() => $code,
                'label' => EnquiryLabels::language($code),
                'site' => $site[$code] ?? 0,
                'preferred' => $preferred[$code] ?? 0,
            ];
        }
        $rows[] = [
            ArrayRecord::getKeyName() => 'none',
            'label' => __('admin.value.not_chosen'),
            'site' => null,
            'preferred' => $preferred[''] ?? 0,
        ];
        $rows[] = [
            ArrayRecord::getKeyName() => 'total',
            'label' => __('admin.by_language.total'),
            'site' => array_sum($site),
            'preferred' => array_sum($preferred),
        ];

        return $rows;
    }
}
