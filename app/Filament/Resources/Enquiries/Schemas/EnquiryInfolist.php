<?php

namespace App\Filament\Resources\Enquiries\Schemas;

use App\Filament\Resources\Enquiries\EnquiryActions;
use App\Filament\Support\AdminBidi;
use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStats;
use App\Filament\Support\EnquiryStatus;
use App\Models\Enquiry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * One request, as staff read it: the decrypted summary and deadline (main column), the visitor and the
 * review state (side column), then the consent evidence and the attachment. Every value starts under its
 * label in either interface direction: short values are isolated inline (AdminBidi: phone, e-mail and the
 * reference left to right, the name in its own direction); the visitor's own longer text and the internal
 * note are `.slc-prose` blocks in their own direction (theme.css); the stored consent wording keeps the
 * visitor's language (lang attribute). Dates and sizes: AdminFormat (interface language, Dubai time).
 */
final class EnquiryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $zone = EnquiryStats::timezone();

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                    Group::make([
                        Callout::make(__('admin.spam.note'))
                            ->icon(Heroicon::OutlinedNoSymbol)
                            ->danger()
                            ->visible(fn (Enquiry $record): bool => $record->status === Enquiry::STATUS_SPAM),
                        Section::make(__('admin.section.request'))
                            ->description(__('admin.section.request_hint'))
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->schema([
                                TextEntry::make('matter_type')
                                    ->label(__('admin.field.service'))
                                    ->formatStateUsing(fn (?string $state): string => EnquiryLabels::matter($state))
                                    ->placeholder(__('admin.value.not_chosen')),
                                TextEntry::make('summary')
                                    ->label(__('admin.field.summary'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminFormat::multiline($state))
                                    ->extraAttributes(fn (Enquiry $record): array => ['class' => 'slc-prose', 'lang' => $record->locale, 'dir' => 'auto']),
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    TextEntry::make('deadline_date')
                                        ->label(__('admin.field.deadline_date'))
                                        ->date(AdminFormat::date())
                                        ->placeholder(__('admin.value.not_given')),
                                    TextEntry::make('deadline_details')
                                        ->label(__('admin.field.deadline_details'))
                                        ->placeholder(__('admin.value.not_given'))
                                        ->formatStateUsing(fn (?string $state): HtmlString => AdminFormat::multiline($state))
                                        ->extraAttributes(fn (Enquiry $record): array => ['class' => 'slc-prose', 'lang' => $record->locale, 'dir' => 'auto']),
                                ]),
                            ]),
                        Section::make(__('admin.section.record'))
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->collapsible()
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    TextEntry::make('consent_at')
                                        ->label(__('admin.field.consent_at'))
                                        ->dateTime(AdminFormat::dateTime(), $zone),
                                    TextEntry::make('updated_at')
                                        ->label(__('admin.field.updated_at'))
                                        ->dateTime(AdminFormat::dateTime(), $zone),
                                ]),
                                TextEntry::make('consent_text')
                                    ->label(__('admin.field.consent_text'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminFormat::multiline($state))
                                    ->extraAttributes(fn (Enquiry $record): array => ['class' => 'slc-prose slc-quote', 'lang' => $record->locale, 'dir' => $record->locale === 'ar' ? 'rtl' : 'ltr']),
                            ]),
                        Section::make(__('admin.section.attachment'))
                            ->icon(Heroicon::OutlinedPaperClip)
                            ->schema([
                                TextEntry::make('attachment_path')
                                    ->label(__('admin.field.attachment'))
                                    ->hiddenLabel()
                                    ->state(fn (Enquiry $record): string|HtmlString => self::attachmentState($record))
                                    ->helperText(fn (Enquiry $record): ?string => filled($record->attachment_path) ? __('admin.attachment.download_hint') : null)
                                    ->color('gray')
                                    ->suffixActions([EnquiryActions::downloadAttachment()->button()->outlined()]),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                    Group::make([
                        Section::make(__('admin.section.review'))
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->schema([
                                TextEntry::make('status')
                                    ->label(__('admin.field.status'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => EnquiryStatus::label($state))
                                    ->color(fn (?string $state): string => EnquiryStatus::color($state))
                                    ->icon(fn (?string $state) => EnquiryStatus::icon($state)),
                                TextEntry::make('status_changed_at')
                                    ->label(__('admin.field.status_changed_at'))
                                    ->dateTime(AdminFormat::dateTime(), $zone)
                                    ->placeholder(__('admin.value.none')),
                                TextEntry::make('internal_note')
                                    ->label(__('admin.field.internal_note'))
                                    ->placeholder(__('admin.value.none'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminFormat::multiline($state))
                                    ->extraAttributes(['class' => 'slc-prose', 'dir' => 'auto']),
                            ]),
                        Section::make(__('admin.section.visitor'))
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('admin.field.name'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::auto($state)),
                                TextEntry::make('phone')
                                    ->label(__('admin.field.phone'))
                                    ->placeholder(__('admin.value.not_given'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state))
                                    ->url(fn (?string $state): ?string => filled($state) ? 'tel:'.$state : null)
                                    ->copyable()
                                    ->copyableState(fn (?string $state): ?string => $state),
                                TextEntry::make('email')
                                    ->label(__('admin.field.email'))
                                    ->placeholder(__('admin.value.not_given'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state))
                                    ->url(fn (?string $state): ?string => filled($state) ? 'mailto:'.$state : null)
                                    ->copyable()
                                    ->copyableState(fn (?string $state): ?string => $state),
                                TextEntry::make('preferred_language')
                                    ->label(__('admin.field.preferred_language'))
                                    ->formatStateUsing(fn (?string $state): string => EnquiryLabels::language($state))
                                    ->placeholder(__('admin.value.not_chosen')),
                                TextEntry::make('locale')
                                    ->label(__('admin.field.site_language'))
                                    ->formatStateUsing(fn (?string $state): string => EnquiryLabels::language($state)),
                            ]),
                    ])->columnSpan(['lg' => 1]),
                ]),
        ]);
    }

    /**
     * "PDF · 2.4 MB" (decimal units, as the form and its upload chip count them; the type isolated left to
     * right for the Arabic interface), or why there is no file. Only the type and the size are inserted, both
     * escaped, into the panel's own label template; the stored path itself is never shown.
     */
    private static function attachmentState(Enquiry $record): string|HtmlString
    {
        if (filled($record->attachment_path)) {
            $type = strtoupper((string) pathinfo((string) $record->attachment_path, PATHINFO_EXTENSION));

            return new HtmlString((string) __('admin.attachment.meta', [
                'type' => AdminBidi::ltr($type)->toHtml(),
                'size' => e(AdminFormat::fileSize((int) $record->attachment_size)),
            ]));
        }

        return config('summit.features.attachments_enabled')
            ? __('admin.attachment.none')
            : __('admin.attachment.disabled');
    }
}
