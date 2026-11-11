<?php

namespace App\Filament\Resources\Enquiries\Schemas;

use App\Filament\Resources\Enquiries\EnquiryActions;
use App\Filament\Support\AdminBidi;
use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStats;
use App\Models\Enquiry;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Edit = review: only the status and the internal note are editable. The visitor's own submission is
 * shown read-only beside the form for context and can never be changed from the panel. Values line up
 * under their labels as on the request page (EnquiryInfolist: AdminBidi, `.slc-prose`, AdminFormat).
 */
final class EnquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                    Section::make(__('admin.section.review'))
                        ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                        ->schema([
                            EnquiryActions::statusField(),
                            Textarea::make('internal_note')
                                ->label(__('admin.field.internal_note'))
                                ->helperText(__('admin.field.internal_note_hint'))
                                ->rows(8)
                                ->autosize()
                                ->maxLength(5000)
                                ->extraInputAttributes(['dir' => 'auto']),
                        ])
                        ->columnSpan(['lg' => 2]),
                    Section::make(__('admin.section.request'))
                        ->description(__('admin.section.request_hint'))
                        ->icon(Heroicon::OutlinedLockClosed)
                        ->schema([
                            TextEntry::make('reference')
                                ->label(__('admin.field.reference'))
                                ->fontFamily(FontFamily::Mono)
                                ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state)),
                            TextEntry::make('created_at')
                                ->label(__('admin.field.received'))
                                ->dateTime(AdminFormat::dateTime(), EnquiryStats::timezone()),
                            TextEntry::make('name')
                                ->label(__('admin.field.name'))
                                ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::auto($state)),
                            TextEntry::make('matter_type')
                                ->label(__('admin.field.service'))
                                ->formatStateUsing(fn (?string $state): string => EnquiryLabels::matter($state))
                                ->placeholder(__('admin.value.not_chosen')),
                            TextEntry::make('summary')
                                ->label(__('admin.field.summary'))
                                ->formatStateUsing(fn (?string $state): HtmlString => AdminFormat::multiline($state))
                                ->lineClamp(8)
                                ->extraAttributes(fn (Enquiry $record): array => ['class' => 'slc-prose', 'lang' => $record->locale, 'dir' => 'auto']),
                        ])
                        ->columnSpan(['lg' => 1]),
                ]),
        ]);
    }
}
