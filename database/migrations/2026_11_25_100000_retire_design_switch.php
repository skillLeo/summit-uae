<?php

use App\Content\Cms\ContentCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Design 2 is the only design (owner, 2026-11-25), so the design switch is retired:
 *  - the two permissions of the Design Preview settings screen, which no longer exists (App\Access\Screens no longer
 *    lists it, so App\Access\RoleSync would remove them as well; the roles lose them through the pivot's cascade);
 *  - the switch's interface text (group "switcher" of ui_strings, imported from the removed lang/{locale}/switcher.php),
 *    so the Interface text screen no longer lists labels nobody sees.
 * The Design Preview settings themselves: database/settings/2026_11_25_100000_remove_design_settings.php. The
 * "design" column of older requests is kept. down() restores nothing: the screen and the switch no longer exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            Permission::query()->where('guard_name', 'web')->whereIn('name', ['settings_design.view', 'settings_design.update'])->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        if (Schema::hasTable('ui_strings')) {
            DB::table('ui_strings')->where('group', 'switcher')->delete();
            ContentCache::bump();
        }
    }

    public function down(): void {}
};
