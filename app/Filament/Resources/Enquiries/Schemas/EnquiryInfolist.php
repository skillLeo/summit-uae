<?php

namespace App\Filament\Resources\Enquiries\Schemas;

use App\Filament\Resources\Enquiries\EnquiryActions;
use App\Filament\Support\AdminBidi;
use App\Filament\Support\AdminFormat;
use App\Filament\Support\EnquiryLabels;
use App\Filament\Support\EnquiryStats;
use App\Filament\Support\EnquiryStatus;
use App\Filament\Support\RequestFormat;
use App\Models\Enquiry;
use App\Models\Revision;
use App\Requests\ReviewTarget;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * One request (docs/briefs/design-2.1-and-admin-cms.md §10.4).
 *
 * Start side: the summary and matter, the deadline (highlighted when it is within 7 days), the documents (ticked
 * documents and the attachment), the calculator the request started from, the consent record and where it came from.
 * End side: status, assignee, outcome and the workflow times with their actions (S status, A assign), the sender with
 * the contact actions (Call, Email, Open WhatsApp: a chat with the number, nothing is sent), the internal notes
 * (N; edit or delete your own within 10 minutes) and the timeline of every change.
 *
 * Every value starts under its label in either interface direction (AdminBidi). Times are Dubai time.
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
                        Callout::make(__('requests.value.anonymised'))
                            ->icon(Heroicon::OutlinedEyeSlash)
                            ->color('gray')
                            ->visible(fn (Enquiry $record): bool => $record->isAnonymised()),
                        Callout::make(fn (Enquiry $record): string => (string) __('requests.field.spam_reasons').': '.self::spamReasons($record))
                            ->icon(Heroicon::OutlinedNoSymbol)
                            ->danger()
                            ->visible(fn (Enquiry $record): bool => $record->status === Enquiry::STATUS_SPAM),
                        Section::make(__('requests.section.summary'))
                            ->description(__('requests.section.summary_hint'))
                            ->icon(Heroicon::OutlinedLockClosed)
                            ->schema([
                                TextEntry::make('matter_type')
                                    ->label(__('requests.column.matter'))
                                    ->formatStateUsing(fn (?string $state): string => EnquiryLabels::matter($state))
                                    ->placeholder(__('admin.value.not_chosen')),
                                TextEntry::make('summary')
                                    ->label(__('requests.section.summary'))
                                    ->hiddenLabel()
                                    ->placeholder(__('requests.value.none'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminFormat::multiline($state))
                                    ->extraAttributes(fn (Enquiry $record): array => ['class' => 'slc-prose', 'lang' => $record->locale, 'dir' => 'auto']),
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    TextEntry::make('deadline_date')
                                        ->label(__('requests.field.deadline'))
                                        ->date(AdminFormat::date())
                                        ->placeholder(__('admin.value.not_given'))
                                        ->badge(fn (Enquiry $record): bool => RequestFormat::deadlineSoon($record))
                                        ->color(fn (Enquiry $record): ?string => RequestFormat::deadlineSoon($record) ? 'warning' : null)
                                        ->icon(fn (Enquiry $record): ?Heroicon => RequestFormat::deadlineSoon($record) ? Heroicon::OutlinedExclamationTriangle : null)
                                        ->helperText(fn (Enquiry $record): ?string => RequestFormat::deadlineRelative($record))
                                        ->extraAttributes(fn (Enquiry $record): array => RequestFormat::deadlineSoon($record) ? ['data-deadline' => 'soon'] : []),
                                    TextEntry::make('deadline_details')
                                        ->label(__('requests.field.deadline_details'))
                                        ->placeholder(__('admin.value.not_given'))
                                        ->formatStateUsing(fn (?string $state): HtmlString => AdminFormat::multiline($state))
                                        ->extraAttributes(fn (Enquiry $record): array => ['class' => 'slc-prose', 'lang' => $record->locale, 'dir' => 'auto']),
                                ]),
                            ]),
                        Section::make(__('requests.section.documents'))
                            ->icon(Heroicon::OutlinedPaperClip)
                            ->schema([
                                TextEntry::make('extras.documents')
                                    ->label(__('requests.field.ticked_documents'))
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->visible(fn (Enquiry $record): bool => filled($record->extras['documents'] ?? null)),
                                TextEntry::make('attachment_path')
                                    ->label(__('requests.field.attachment'))
                                    ->state(fn (Enquiry $record): string|HtmlString => self::attachmentState($record))
                                    ->helperText(fn (Enquiry $record): ?string => filled($record->attachment_path) ? __('admin.attachment.download_hint') : null)
                                    ->color('gray')
                                    ->suffixActions([EnquiryActions::downloadAttachment()->button()->outlined()]),
                            ]),
                        Section::make(__('requests.section.calculator'))
                            ->icon(Heroicon::OutlinedCalculator)
                            ->visible(fn (Enquiry $record): bool => filled($record->extras['tool'] ?? null) || filled($record->extras['audience'] ?? null) || filled($record->extras['situation'] ?? null))
                            ->columns(['default' => 1, 'md' => 3])
                            ->schema([
                                TextEntry::make('extras.tool')->label(__('requests.field.tool'))->placeholder(__('requests.value.none')),
                                TextEntry::make('extras.audience')->label(__('requests.field.audience'))->placeholder(__('requests.value.none')),
                                TextEntry::make('extras.situation')->label(__('requests.field.situation'))->placeholder(__('requests.value.none'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state)),
                            ]),
                        Section::make(__('requests.section.consent'))
                            ->icon(Heroicon::OutlinedShieldCheck)
                            ->collapsible()
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    TextEntry::make('consent_at')
                                        ->label(__('requests.field.consent_at'))
                                        ->dateTime(AdminFormat::dateTime(), $zone),
                                    TextEntry::make('privacy_revision_id')
                                        ->label(__('requests.field.privacy_revision'))
                                        ->state(fn (Enquiry $record): string => self::privacyRevision($record)),
                                ]),
                                TextEntry::make('consent_text')
                                    ->label(__('requests.field.consent_text'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminFormat::multiline($state))
                                    ->extraAttributes(fn (Enquiry $record): array => ['class' => 'slc-prose slc-quote', 'lang' => $record->locale, 'dir' => $record->locale === 'ar' ? 'rtl' : 'ltr']),
                            ]),
                        Section::make(__('requests.section.source'))
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->collapsible()
                            ->collapsed()
                            ->columns(['default' => 1, 'md' => 2])
                            ->schema([
                                TextEntry::make('source_page')->label(__('requests.field.source_page'))->placeholder(__('requests.value.none'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state)),
                                TextEntry::make('referrer')->label(__('requests.field.referrer'))->placeholder(__('requests.value.none'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state)),
                                TextEntry::make('utm')->label(__('requests.field.utm'))->placeholder(__('requests.value.none'))
                                    ->state(fn (Enquiry $record): ?HtmlString => self::utm($record)),
                                TextEntry::make('device_class')->label(__('requests.field.device'))->placeholder(__('requests.value.none'))
                                    ->formatStateUsing(fn (?string $state): string => (string) __('requests.value.device.'.$state)),
                                TextEntry::make('design')->label(__('requests.field.design'))->placeholder(__('requests.value.none'))
                                    ->formatStateUsing(fn ($state): string => (string) __('requests.value.design', ['design' => $state])),
                                TextEntry::make('locale')->label(__('requests.field.site_language'))
                                    ->formatStateUsing(fn (?string $state): string => EnquiryLabels::language($state)),
                            ]),
                    ])->columnSpan(['lg' => 2])->columnOrder(['default' => 2, 'lg' => 1]),

                    Group::make([
                        Section::make(__('requests.section.status'))
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->afterHeader([EnquiryActions::status()->button()->size('sm'), EnquiryActions::assign()->button()->size('sm')])
                            ->extraAttributes(['data-panel' => 'status'])
                            ->schema([
                                TextEntry::make('status')
                                    ->label(__('requests.field.status'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => EnquiryStatus::label($state))
                                    ->color(fn (?string $state): string => EnquiryStatus::color($state))
                                    ->icon(fn (?string $state) => EnquiryStatus::icon($state))
                                    ->helperText(fn (Enquiry $record): ?string => RequestFormat::outcome($record->outcome)),
                                TextEntry::make('assignee_id')
                                    ->label(__('requests.field.assignee'))
                                    ->state(fn (Enquiry $record): string => RequestFormat::assignee($record->assignee))
                                    ->color(fn (Enquiry $record): ?string => $record->assignee_id === null ? 'gray' : null)
                                    ->suffixActions([EnquiryActions::assignMe()->link()]),
                                TextEntry::make('received_at')
                                    ->label(__('requests.field.received'))
                                    ->state(fn (Enquiry $record): string => RequestFormat::dateTime($record->received_at ?? $record->created_at))
                                    ->helperText(fn (Enquiry $record): string => RequestFormat::received($record->received_at ?? $record->created_at)),
                                TextEntry::make('waiting')
                                    ->label(fn (Enquiry $record): string => $record->status === Enquiry::STATUS_NEW ? __('requests.field.waiting') : __('requests.field.ttfr'))
                                    ->state(fn (Enquiry $record): string => $record->status === Enquiry::STATUS_NEW || $record->first_reviewed_at !== null
                                        ? (string) __('requests.value.business_hours', ['hours' => RequestFormat::number(RequestFormat::waited($record))])
                                        : (string) __('requests.value.not_reviewed'))
                                    ->badge(fn (Enquiry $record): bool => in_array(RequestFormat::targetState($record), ['near', 'over'], true))
                                    ->color(fn (Enquiry $record): string => RequestFormat::waitingColor($record))
                                    ->icon(fn (Enquiry $record): ?string => RequestFormat::waitingIcon($record))
                                    ->helperText(fn (Enquiry $record): string => (string) __('requests.field.target', ['hours' => ReviewTarget::hours()])),
                                TextEntry::make('first_reviewed_at')
                                    ->label(__('requests.field.first_review'))
                                    ->state(fn (Enquiry $record): string => RequestFormat::dateTime($record->first_reviewed_at))
                                    ->visible(fn (Enquiry $record): bool => $record->first_reviewed_at !== null),
                                TextEntry::make('closed_at')
                                    ->label(__('requests.field.closed'))
                                    ->state(fn (Enquiry $record): string => RequestFormat::dateTime($record->closed_at))
                                    ->visible(fn (Enquiry $record): bool => $record->closed_at !== null),
                            ]),
                        Section::make(__('requests.section.person'))
                            ->icon(Heroicon::OutlinedUser)
                            ->footer(EnquiryActions::contact())
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('requests.field.name'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::auto($state)),
                                TextEntry::make('phone')
                                    ->label(__('requests.field.phone'))
                                    ->placeholder(__('admin.value.not_given'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state))
                                    ->copyable()
                                    ->copyableState(fn (?string $state): ?string => $state),
                                TextEntry::make('email')
                                    ->label(__('requests.field.email'))
                                    ->placeholder(__('admin.value.not_given'))
                                    ->formatStateUsing(fn (?string $state): HtmlString => AdminBidi::ltr($state))
                                    ->copyable()
                                    ->copyableState(fn (?string $state): ?string => $state),
                                TextEntry::make('preferred_language')
                                    ->label(__('requests.field.preferred_language'))
                                    ->formatStateUsing(fn (?string $state): string => EnquiryLabels::language($state))
                                    ->placeholder(__('admin.value.not_chosen')),
                            ]),
                        Section::make(__('requests.section.notes'))
                            ->description(__('requests.section.notes_hint'))
                            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                            ->afterHeader([EnquiryActions::note()->button()->size('sm')])
                            ->schema([
                                ViewEntry::make('notes')->hiddenLabel()->view('filament.requests.notes'),
                            ]),
                        Section::make(__('requests.section.timeline'))
                            ->icon(Heroicon::OutlinedQueueList)
                            ->collapsible()
                            ->schema([
                                ViewEntry::make('events')->hiddenLabel()->view('filament.requests.timeline'),
                            ]),
                    ])->columnSpan(['lg' => 1])->columnOrder(['default' => 1, 'lg' => 2]),
                ]),
        ]);
    }

    private static function spamReasons(Enquiry $record): string
    {
        $reasons = array_map(fn (string $r) => (string) __('requests.spam_reason.'.$r), (array) $record->spam_reasons);

        return $reasons === [] ? (string) __('requests.value.none') : implode(', ', $reasons);
    }

    private static function privacyRevision(Enquiry $record): string
    {
        if ($record->privacy_revision_id === null) {
            return (string) __('requests.field.privacy_revision_unknown');
        }
        $revision = Revision::query()->find($record->privacy_revision_id);

        return (string) __('requests.field.privacy_revision_value', [
            'id' => $record->privacy_revision_id,
            'date' => $revision?->created_at === null ? '' : RequestFormat::dateTime($revision->created_at),
        ]);
    }

    private static function utm(Enquiry $record): ?HtmlString
    {
        $parts = [];
        foreach (Enquiry::UTM as $key) {
            if (filled($record->getAttribute($key))) {
                $parts[] = e(substr($key, 4)).': '.AdminBidi::ltr((string) $record->getAttribute($key))->toHtml();
            }
        }

        return $parts === [] ? null : new HtmlString(implode('<br>', $parts));
    }

    /**
     * "PDF, 2.4 MB" (decimal units, as the form and its upload chip count them), or why there is no file. The stored
     * path itself is never shown.
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
