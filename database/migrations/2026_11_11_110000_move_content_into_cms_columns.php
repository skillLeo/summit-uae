<?php

// CMS foundation, step 2 (docs/briefs/design-2.1-and-admin-cms.md §8.6, §8.8, §8.11).
//
// 1. The content tables drop their catch-all per-language `data` and the `meta` column: every field now has its own
//    column (2026_11_11_100000_add_cms_columns_to_content_tables). Their rows are cleared here and written again by
//    `php artisan summit:content-import` (or `php artisan db:seed`, which runs it); until then the site reads the same
//    content from the files (App\Content\ContentRepository's fallback). Users and enquiries are not touched.
// 2. ui_strings becomes the interface-text table of §8.11 (group, key, text, copy_source, note), read by
//    spatie/laravel-translation-loader (model App\Models\Content\UiString): group "ui" holds the approved DOCX
//    strings that ui() reads, groups "proposed" and "switcher" the lang files' interface text (pending client approval).
// 3. settings becomes the table of spatie/laravel-settings (group, name, locked, payload).
//
// JSON payloads stay JSON text in longText columns (MySQL's JSON type re-sorts object keys).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONTENT = ['pages', 'services', 'guides', 'guide_categories', 'case_studies', 'faq_categories', 'faqs', 'team_members', 'specialist_sites'];

    public function up(): void
    {
        foreach (self::CONTENT as $table) {
            DB::table($table)->delete();
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['data', 'meta']));
        }

        Schema::drop('ui_strings');
        Schema::create('ui_strings', function (Blueprint $t) {
            $t->id();
            $t->string('group', 64);
            $t->string('key', 191);
            $t->longText('text');                       // {en, ar, ru}
            $t->text('note')->nullable();               // where the text comes from, or an editor's note
            $this->cmsColumns($t);
            $t->timestamps();
            $t->unique(['group', 'key']);
        });

        Schema::drop('settings');
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('group');
            $t->string('name');
            $t->boolean('locked')->default(false);
            $t->longText('payload');
            $t->timestamps();
            $t->unique(['group', 'name']);
        });
    }

    public function down(): void
    {
        Schema::drop('settings');
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->longText('value');
            $t->timestamps();
        });

        Schema::drop('ui_strings');
        Schema::create('ui_strings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->longText('value');
            $t->timestamps();
        });

        foreach (self::CONTENT as $table) {
            DB::table($table)->delete();
            Schema::table($table, function (Blueprint $t) {
                $t->longText('data')->nullable();
                $t->longText('meta')->nullable();
            });
        }
    }

    /** §8.5 status fields, §8.7 copy_source and soft deletes (as in 2026_11_11_100000_add_cms_columns_to_content_tables). */
    private function cmsColumns(Blueprint $t): void
    {
        $t->string('status', 16)->default('published')->index();
        $t->longText('draft')->nullable();
        $t->timestamp('published_at')->nullable();
        $t->timestamp('publish_at')->nullable();
        $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $t->string('copy_source', 16)->default('docx')->index();
        $t->softDeletes();
    }
};
