<?php

// CMS foundation, new tables (docs/briefs/design-2.1-and-admin-cms.md §8.2, §8.5, §8.11). JSON payloads are JSON text in
// longText columns (MySQL's JSON type re-sorts object keys; see 2026_11_09_000100_create_content_tables).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Situations ("Start your matter", "Where Do You Stand?"): the approved P02 lines "This service may be relevant
        // if:", grouped by practice area. code = practice area + 1-based position (S05-2, App\Content\NavigatorData).
        // documents / first_steps / mistakes are per situation when set; null = the practice area's own lists.
        // featured = a quick pick of "Start your matter", in featured_position order.
        Schema::create('situations', function (Blueprint $t) {
            $t->string('code', 8)->primary();
            $t->string('practice_area', 4)->index();
            $t->unsignedSmallInteger('position');
            $t->longText('title')->nullable();
            $t->longText('documents')->nullable();
            $t->longText('first_steps')->nullable();
            $t->longText('mistakes')->nullable();
            $t->boolean('featured')->default(false);
            $t->unsignedSmallInteger('featured_position')->nullable();
            $this->cmsColumns($t, 'docx');
            $t->timestamps();
        });

        // Anatomy of a Document: the sample document types and their parts. parts is one structure shared by every
        // language: [{key, label: {en, ar, ru}, can_show: {…}, we_check: {…}}]. The illustrations stay in code.
        Schema::create('document_samples', function (Blueprint $t) {
            $t->string('code', 16)->primary();
            $t->unsignedSmallInteger('position');
            $t->boolean('enabled')->default(true);
            $t->string('practice_area', 4)->nullable();
            $t->longText('title')->nullable();
            $t->longText('parts')->nullable();
            $this->cmsColumns($t, 'pending');
            $t->timestamps();
        });

        // Navigation (header, mega menu, footer groups, mobile bar). link_key keeps the client's site-map key
        // (H-L001 …, WA01, TEL, EMAIL): read-only in the admin, so every link stays traceable.
        Schema::create('navigation_menus', function (Blueprint $t) {
            $t->id();
            $t->string('key', 32)->unique();
            $t->string('name');
            $t->string('description')->nullable();
            $this->cmsColumns($t, 'docx');
            $t->timestamps();
        });

        Schema::create('navigation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_id')->constrained('navigation_menus')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('navigation_items')->cascadeOnDelete();
            $t->string('link_key', 32)->nullable()->index();
            // {en, ar, ru}; null = the label of the target record (a practice area's title, a site's name).
            $t->longText('label')->nullable();
            // The label the client's site map gives this link key ({en, ar, ru}), for traceability.
            $t->longText('registry_label')->nullable();
            // page | record | anchor | url | tel | email | whatsapp
            $t->string('target_type', 16);
            $t->string('target')->nullable();
            $t->boolean('new_tab')->default(false);
            $t->unsignedSmallInteger('position');
            $t->boolean('enabled')->default(true);
            // Presentation hints the views need, e.g. {"nav": "practice", "mega": true, "sheet": "services"}.
            $t->longText('options')->nullable();
            $this->cmsColumns($t, 'docx');
            $t->timestamps();
            $t->index(['menu_id', 'position']);
        });

        Schema::create('redirects', function (Blueprint $t) {
            $t->id();
            $t->string('from_path')->unique();
            $t->string('to_path');
            $t->unsignedSmallInteger('code')->default(301);
            $t->unsignedInteger('hits')->default(0);
            $t->timestamp('last_hit_at')->nullable();
            $t->boolean('enabled')->default(true);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Revisions of any content record (polymorphic; content keys are strings: P01, S06, G01, faq-12 …).
        Schema::create('revisions', function (Blueprint $t) {
            $t->id();
            $t->string('revisionable_type');
            $t->string('revisionable_id', 64);
            $t->longText('snapshot');
            $t->string('note')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->index(['revisionable_type', 'revisionable_id']);
        });

        // Calculator rules (gratuity, rent): versioned, with an effective date. rules is JSON text; legal_basis {en, ar, ru}.
        Schema::create('calculator_rule_sets', function (Blueprint $t) {
            $t->id();
            $t->string('calculator', 16);
            $t->unsignedInteger('version');
            $t->date('effective_from');
            $t->longText('rules');
            $t->longText('legal_basis')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->cmsColumns($t, 'pending');
            $t->timestamps();
            $t->unique(['calculator', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['calculator_rule_sets', 'revisions', 'redirects', 'navigation_items', 'navigation_menus', 'document_samples', 'situations'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /** §8.5 status fields, §8.7 copy_source and soft deletes (as in 2026_11_11_100000_add_cms_columns_to_content_tables). */
    private function cmsColumns(Blueprint $t, string $copySource): void
    {
        $t->string('status', 16)->default('published')->index();
        $t->longText('draft')->nullable();
        $t->timestamp('published_at')->nullable();
        $t->timestamp('publish_at')->nullable();
        $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $t->string('copy_source', 16)->default($copySource)->index();
        $t->softDeletes();
    }
};
