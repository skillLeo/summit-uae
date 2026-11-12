<?php

namespace App\Filament\Resources\Enquiries;

use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Filament\Resources\Enquiries\Schemas\EnquiryInfolist;
use App\Filament\Resources\Enquiries\Tables\EnquiriesTable;
use App\Filament\Cms\Support\CmsAccess;
use App\Models\Enquiry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The requests inbox (docs/briefs/design-2.1-and-admin-cms.md §10; the public form's enquiries, docs/enquiries.md).
 * Staff triage a request on its page: status (New, In review, Closed with an outcome, Spam), assignee, notes, contact
 * actions and the privacy tools (App\Filament\Resources\Enquiries\EnquiryActions). Requests are never created here;
 * deleting moves one to the Trash. Their personal data never leaves the panel: the summary is decrypted only on the
 * request's own page. Access: CmsAccess::can('requests', …).
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
        return (string) __('requests.nav.inbox');
    }

    public static function canAccess(): bool
    {
        return CmsAccess::can('requests', 'view');
    }

    public static function canViewAny(): bool
    {
        return CmsAccess::can('requests', 'view');
    }

    public static function canView(Model $record): bool
    {
        return CmsAccess::can('requests', 'view');
    }

    public static function canEdit(Model $record): bool
    {
        return false;
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
     * The requests this user may see (Phase 7: a case handler limited to "assigned only" sees their own).
     *
     * @return Builder<Enquiry>
     */
    public static function getEloquentQuery(): Builder
    {
        return CmsAccess::scopeRequests(parent::getEloquentQuery());
    }

    /** A request in the Trash can still be opened (restore, permanent delete). */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return CmsAccess::scopeRequests(parent::getEloquentQuery()->withTrashed());
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
        ];
    }
}
