<?php

use App\Access\RoleSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users and security (docs/briefs/design-2.1-and-admin-cms.md §12.1, §12.2, §12.4, Phase 7):
 * - Filament's multi-factor authentication: the authenticator-app secret and recovery codes (both encrypted by the
 *   model) and the e-mail code switch;
 * - last_login_at (Users screen) and deactivated_at (a deactivated user cannot sign in);
 * - the roles and the permission matrix (App\Access\RoleSync, idempotent): every existing admin (users.is_admin)
 *   becomes super admin, so the owner's account on the server keeps every right after the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->text('app_authentication_secret')->nullable()->after('password');
            $t->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $t->boolean('has_email_authentication')->default(false)->after('app_authentication_recovery_codes');
            $t->timestamp('last_login_at')->nullable();
            $t->timestamp('deactivated_at')->nullable();
        });

        RoleSync::run(promoteAdmins: true);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'has_email_authentication', 'last_login_at', 'deactivated_at']);
        });
    }
};
