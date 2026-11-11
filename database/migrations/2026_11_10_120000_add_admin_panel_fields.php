<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal admin panel (Filament, /admin): who may sign in, the language each admin works in, and the
 * review fields staff keep on an enquiry.
 *
 * Every new column is nullable or has a default, so the public consultation form and its backend
 * (EnquiryController, StoreEnquiryRequest) keep writing exactly the columns they write today.
 * internal_note is stored encrypted by the admin panel (Eloquent "encrypted" cast, app key), like the
 * visitor's summary; it never leaves the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Only users with this flag may open the admin panel (User::canAccessPanel). Never mass assignable.
            $t->boolean('is_admin')->default(false)->after('password');
            // en | ar | ru: the admin interface language this user chose (null = browser / English).
            $t->string('admin_locale', 2)->nullable()->after('is_admin');
        });

        Schema::table('enquiries', function (Blueprint $t) {
            // Staff-only note (encrypted). The status column already exists: new | in_review | closed | spam.
            $t->text('internal_note')->nullable();
            // When the status last changed in the panel (null = untouched since it arrived).
            $t->timestamp('status_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $t) {
            $t->dropColumn(['internal_note', 'status_changed_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['is_admin', 'admin_locale']);
        });
    }
};
