<?php

namespace App\Filament\Resources\Enquiries;

use App\Filament\Resources\Enquiries\Pages\EditEnquiry;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Filament\Resources\Enquiries\Schemas\EnquiryForm;
use App\Filament\Resources\Enquiries\Schemas\EnquiryInfolist;
use App\Filament\Resources\Enquiries\Tables\EnquiriesTable;
use App\Models\Enquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Consultation requests from the public form (enquiries, docs/enquiries.md). Staff read a request,
 * change its status (new → in review → closed; spam is set by the form's honeypot) and keep an internal
 * note. Requests are never created or deleted here, never globally searched, and their personal data
 * never leaves the panel: the summary is decrypted only on the request's own page.
 */
class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::InboxArrowDown;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $slug = 'requests';

    protected static ?int $navigationSort = 1;

    /** Brief §9.1 group "Requests" (AdminPanelProvider::navigationGroups()). */
    protected static string|\UnitEnum|null $navigationGroup = 'requests';

    protected static bool $isGloballySearchable = false;

    /** Sentence case in every language (Filament would title-case "Заявки На Консультацию"). */
    protected static bool $hasTitleCaseModelLabel = false;

    /**
     * The panel's own casts for the admin-only columns (the shared Enquiry model is not changed):
     * internal_note is encrypted at rest, like the visitor's summary.
     */
    public const ADMIN_CASTS = [
        'internal_note' => 'encrypted',
        'status_changed_at' => 'datetime',
    ];

    public static function getModelLabel(): string
    {
        return (string) __('admin.nav.request');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('admin.nav.requests');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.nav.requests_short');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = Enquiry::query()->where('status', Enquiry::STATUS_NEW)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return (string) __('admin.stats.awaiting');
    }

    /**
     * Every record the panel loads (tables, record actions, the view and edit pages) carries
     * ADMIN_CASTS; the view and edit pages re-apply them after Livewire rehydrates the record.
     *
     * @return Builder<Enquiry>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCasts(self::ADMIN_CASTS);
    }

    public static function form(Schema $schema): Schema
    {
        return EnquiryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EnquiryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnquiriesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnquiries::route('/'),
            'view' => ViewEnquiry::route('/{record}'),
            'edit' => EditEnquiry::route('/{record}/edit'),
        ];
    }
}
