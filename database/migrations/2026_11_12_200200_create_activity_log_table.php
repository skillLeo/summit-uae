<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-activitylog 5.1.3 (docs/briefs/design-2.1-and-admin-cms.md §12.2): who changed what, when, with a field
 * diff; every sign-in; every privacy action and export. The package's own table, with the JSON payloads as text
 * (longText, like every JSON column of this app). Read-only in the admin (System, Activity Log). App\Support\Audit
 * writes it; personal data of visitors never goes into it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            // string ids: content records are keyed by their site-map codes (P01, S06, G01 …)
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('event')->nullable()->index();
            $table->string('causer_type')->nullable();
            $table->string('causer_id', 64)->nullable();
            $table->longText('attribute_changes')->nullable();
            $table->longText('properties')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
