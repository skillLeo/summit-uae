<?php

/*
| Admin CMS switches (docs/briefs/design-2.1-and-admin-cms.md §8, §12.1).
*/
return [
    // Until the roles of Phase 7: every admin user counts as the super admin (permanent delete, calculator rules).
    // Set to false to forbid those actions to everyone (App\Filament\Cms\Support\CmsAccess::superAdmin()).
    'super_admin_is_admin' => (bool) env('CMS_SUPER_ADMIN_IS_ADMIN', true),

    // Rebuild the command-palette search index (public/search/*.json) after a publish.
    'search_index_on_publish' => (bool) env('CMS_SEARCH_INDEX_ON_PUBLISH', false),
];
