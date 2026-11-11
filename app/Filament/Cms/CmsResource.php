<?php

namespace App\Filament\Cms;

use App\Content\Cms\Editing\Registry;
use App\Filament\Cms\RelationManagers\RevisionsRelationManager;
use App\Filament\Cms\Support\CmsAccess;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Base of every CMS resource (docs/briefs/design-2.1-and-admin-cms.md §9): the sidebar group (the panel's §9.1 groups,
 * keyed overview, requests, content, site, settings, system), sentence-case labels, the revisions under the edit form,
 * the record title in the admin's language, and the super-admin-only permanent delete (CmsAccess, the Phase 7 hook).
 * Lists never show the Trash (the Trash screen does).
 */
abstract class CmsResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'content';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static bool $isGloballySearchable = false;

    /** The lang key of the plural label (cms_content.php …), e.g. "cms_content.guides.plural". */
    abstract protected static function labelKey(): string;

    public static function getModelLabel(): string
    {
        return (string) __(static::labelKey().'.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __(static::labelKey().'.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record === null ? static::getModelLabel() : Registry::label($record);
    }

    public static function canAccess(): bool
    {
        return CmsAccess::admin();
    }

    public static function canForceDelete(Model $record): bool
    {
        return CmsAccess::superAdmin();
    }

    public static function canForceDeleteAny(): bool
    {
        return CmsAccess::superAdmin();
    }

    /** Filament 5 actions ask the authorization responses, not canForceDelete(): permanent delete is super admin only. */
    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return CmsAccess::superAdmin() ? Response::allow() : Response::deny((string) __('cms.access.super_admin'));
    }

    public static function getForceDeleteAnyAuthorizationResponse(): Response
    {
        return CmsAccess::superAdmin() ? Response::allow() : Response::deny((string) __('cms.access.super_admin'));
    }

    public static function getRelations(): array
    {
        return [RevisionsRelationManager::class];
    }
}
