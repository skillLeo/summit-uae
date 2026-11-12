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

    /** The screen key of the permission matrix (App\Access\Screens). */
    protected static string $screen = 'pages';

    public static function screen(): string
    {
        return static::$screen;
    }

    /** Filament's policy actions → the abilities of the matrix (§12.1). */
    public const ABILITY = [
        'viewAny' => 'view', 'view' => 'view',
        'create' => 'create', 'replicate' => 'create',
        'update' => 'update', 'reorder' => 'update',
        'delete' => 'delete', 'deleteAny' => 'delete',
        'restore' => 'restore', 'restoreAny' => 'restore',
        'forceDelete' => 'force_delete', 'forceDeleteAny' => 'force_delete',
    ];

    /**
     * Every Filament authorization question (can*(), the actions' authorization responses) is answered by the permission
     * matrix: CmsAccess::can(screen, ability). Permanent delete is the super admin's by default (Screens::roles()).
     */
    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof \BackedEnum ? (string) $action->value : ($action instanceof \UnitEnum ? $action->name : $action);
        $ability = self::ABILITY[$action] ?? $action;

        return CmsAccess::can(static::screen(), $ability)
            ? Response::allow()
            : Response::deny((string) __($ability === 'force_delete' ? 'cms.access.super_admin' : 'cms.access.denied'));
    }

    public static function getRelations(): array
    {
        return [RevisionsRelationManager::class];
    }
}
