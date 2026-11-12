<?php

namespace App\Access;

/**
 * The permission matrix (docs/briefs/design-2.1-and-admin-cms.md §12.1): every admin screen and the abilities that
 * make sense on it. A permission is named "screen.ability" (spatie/laravel-permission, guard "web"). The super admin
 * gets everything through Gate::before; the other built-in roles get the sets in roles(); the super admin may create
 * custom roles from the same matrix (System, Roles and Permissions).
 */
final class Screens
{
    public const ABILITIES = ['view', 'create', 'update', 'publish', 'delete', 'restore', 'force_delete', 'export'];

    private const CONTENT = ['view', 'create', 'update', 'publish', 'delete', 'restore', 'force_delete'];

    private const SETTINGS = ['view', 'update'];

    /** @return array<string, array{group: string, abilities: list<string>}> */
    public static function all(): array
    {
        return [
            // Overview
            'dashboard' => ['group' => 'overview', 'abilities' => ['view']],
            'reports' => ['group' => 'overview', 'abilities' => ['view', 'export']],
            // Requests
            'requests' => ['group' => 'requests', 'abilities' => ['view', 'update', 'delete', 'restore', 'force_delete', 'export']],
            // Content
            'pages' => ['group' => 'content', 'abilities' => self::CONTENT],
            'practice_areas' => ['group' => 'content', 'abilities' => self::CONTENT],
            'guides' => ['group' => 'content', 'abilities' => self::CONTENT],
            'guide_categories' => ['group' => 'content', 'abilities' => self::CONTENT],
            'case_studies' => ['group' => 'content', 'abilities' => self::CONTENT],
            'faqs' => ['group' => 'content', 'abilities' => self::CONTENT],
            'faq_groups' => ['group' => 'content', 'abilities' => self::CONTENT],
            'team' => ['group' => 'content', 'abilities' => self::CONTENT],
            'situations' => ['group' => 'content', 'abilities' => self::CONTENT],
            'document_samples' => ['group' => 'content', 'abilities' => self::CONTENT],
            'associated_sites' => ['group' => 'content', 'abilities' => self::CONTENT],
            'approval_report' => ['group' => 'content', 'abilities' => ['view', 'export']],
            // Site
            'navigation' => ['group' => 'site', 'abilities' => ['view', 'update', 'publish']],
            'media' => ['group' => 'site', 'abilities' => ['view', 'create', 'update', 'delete', 'restore', 'force_delete']],
            'interface_text' => ['group' => 'site', 'abilities' => ['view', 'update', 'publish', 'export']],
            'seo' => ['group' => 'site', 'abilities' => ['view', 'update']],
            'redirects' => ['group' => 'site', 'abilities' => ['view', 'create', 'update', 'delete']],
            'calculators' => ['group' => 'site', 'abilities' => ['view', 'create', 'update', 'publish']],
            // Settings
            'settings_general' => ['group' => 'settings', 'abilities' => self::SETTINGS],
            'settings_hours' => ['group' => 'settings', 'abilities' => self::SETTINGS],
            'settings_whatsapp' => ['group' => 'settings', 'abilities' => self::SETTINGS],
            'settings_languages' => ['group' => 'settings', 'abilities' => self::SETTINGS],
            'settings_forms' => ['group' => 'settings', 'abilities' => self::SETTINGS],
            'settings_notifications' => ['group' => 'settings', 'abilities' => self::SETTINGS],
            'settings_brand' => ['group' => 'settings', 'abilities' => self::SETTINGS],
            'settings_design' => ['group' => 'settings', 'abilities' => self::SETTINGS],
            // System
            'users' => ['group' => 'system', 'abilities' => ['view', 'create', 'update', 'delete']],
            'roles' => ['group' => 'system', 'abilities' => ['view', 'create', 'update', 'delete']],
            'activity' => ['group' => 'system', 'abilities' => ['view', 'export']],
            'trash' => ['group' => 'system', 'abilities' => ['view', 'restore', 'force_delete']],
            'backups' => ['group' => 'system', 'abilities' => ['view', 'create', 'delete', 'export']],
            'health' => ['group' => 'system', 'abilities' => ['view']],
        ];
    }

    /** @return list<string> every permission name */
    public static function permissions(): array
    {
        $out = [];
        foreach (self::all() as $screen => $def) {
            foreach ($def['abilities'] as $ability) {
                $out[] = $screen.'.'.$ability;
            }
        }

        return $out;
    }

    public static function permission(string $screen, string $ability): string
    {
        return $screen.'.'.$ability;
    }

    public static function exists(string $screen, string $ability): bool
    {
        return in_array($ability, self::all()[$screen]['abilities'] ?? [], true);
    }

    public const SUPER_ADMIN = 'super_admin';

    public const ADMIN = 'admin';

    public const EDITOR = 'editor';

    public const CASE_HANDLER = 'case_handler';

    public const VIEWER = 'viewer';

    /** The built-in roles; system roles cannot be renamed or deleted. */
    public const SYSTEM_ROLES = [self::SUPER_ADMIN, self::ADMIN, self::EDITOR, self::CASE_HANDLER, self::VIEWER];

    /** Roles that must use two-factor authentication (§12.2). */
    public const TWO_FACTOR_ROLES = [self::SUPER_ADMIN, self::ADMIN];

    /**
     * The permissions of the built-in roles (§12.1). The super admin is not listed: it may do everything.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        $content = ['pages', 'practice_areas', 'guides', 'guide_categories', 'case_studies', 'faqs', 'faq_groups', 'team', 'situations', 'document_samples', 'associated_sites'];
        $grant = function (array $screens, array $abilities): array {
            $out = [];
            foreach ($screens as $screen) {
                foreach ($abilities as $ability) {
                    if (self::exists($screen, $ability)) {
                        $out[] = self::permission($screen, $ability);
                    }
                }
            }

            return $out;
        };

        // Admin: all content including publishing, requests, reports, navigation, media, SEO.
        // No users, roles, settings, calculator rules, system screens or permanent delete.
        $admin = array_merge(
            $grant(['dashboard', 'reports', 'requests', 'approval_report', 'navigation', 'media', 'interface_text', 'seo', 'redirects'], array_diff(self::ABILITIES, ['force_delete'])),
            $grant($content, ['view', 'create', 'update', 'publish', 'delete', 'restore']),
            $grant(['trash'], ['view', 'restore']),
        );

        // Editor: edit and preview content, media and interface text; publish only if granted (not by default).
        $editor = array_merge(
            $grant(['dashboard'], ['view']),
            $grant($content, ['view', 'create', 'update']),
            $grant(['media'], ['view', 'create', 'update']),
            $grant(['interface_text', 'navigation'], ['view', 'update']),
            $grant(['approval_report', 'seo'], ['view']),
        );

        // Case handler: requests (all, or assigned only: a setting), notes, status; reports read-only.
        $caseHandler = array_merge(
            $grant(['dashboard'], ['view']),
            $grant(['requests'], ['view', 'update']),
            $grant(['reports'], ['view']),
        );

        // Viewer: dashboard and reports, read-only.
        $viewer = $grant(['dashboard', 'reports'], ['view']);

        return [
            self::ADMIN => array_values(array_unique($admin)),
            self::EDITOR => array_values(array_unique($editor)),
            self::CASE_HANDLER => array_values(array_unique($caseHandler)),
            self::VIEWER => array_values(array_unique($viewer)),
        ];
    }
}
