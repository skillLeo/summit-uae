<?php

// CMS foundation (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.4, §8.5, §8.7, §8.11). Additive: the existing content
// tables keep their names and rows; their per-language catch-all `data` and `meta` columns are replaced by these explicit
// columns in 2026_11_11_110000_move_content_into_cms_columns.
//
// Every JSON payload (translatable {en, ar, ru} fields, blocks, drafts) is JSON text in a longText column, never the
// JSON type: MySQL's JSON type re-sorts object keys, and the approved field order must survive (see
// 2026_11_09_000100_create_content_tables).
//
// Table names stay; the brief's names (§8.11) map to them as: services = practice_areas, faq_categories = faq_groups,
// specialist_sites = associated_sites.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** table => [translatable longText columns, other columns (name => callback)] */
    private function columns(): array
    {
        return [
            'pages' => [
                ['breadcrumb', 'whatsapp_draft', 'registry_label', 'last_updated', 'blocks'],
                [
                    'key' => fn (Blueprint $t) => $t->string('key', 32)->nullable()->unique(),
                    'og_image' => fn (Blueprint $t) => $t->string('og_image')->nullable(),
                ],
            ],
            'services' => [
                ['home_summary', 'h1', 'intro', 'breadcrumb', 'documents', 'documents_note', 'first_steps', 'mistakes',
                    'seo_title', 'meta_description', 'whatsapp_draft', 'hub_title', 'hub_summary', 'hub_link_label',
                    'registry_label', 'related_guides', 'blocks'],
                [
                    'matter_value' => fn (Blueprint $t) => $t->string('matter_value', 8)->nullable(),
                    'specialist' => fn (Blueprint $t) => $t->string('specialist', 4)->nullable(),
                    'og_image' => fn (Blueprint $t) => $t->string('og_image')->nullable(),
                ],
            ],
            'guides' => [
                ['home_title', 'home_summary', 'card_title', 'card_summary', 'seo_title', 'meta_description', 'blocks'],
                [
                    'standalone' => fn (Blueprint $t) => $t->boolean('standalone')->default(false),
                    'og_image' => fn (Blueprint $t) => $t->string('og_image')->nullable(),
                ],
            ],
            'guide_categories' => [['guides'], []],
            'case_studies' => [
                ['card_title', 'card_summary', 'matter', 'home_title', 'home_summary', 'home_link_label', 'seo_title',
                    'meta_description', 'blocks'],
                [
                    'category' => fn (Blueprint $t) => $t->string('category', 16)->nullable(),
                    'og_image' => fn (Blueprint $t) => $t->string('og_image')->nullable(),
                ],
            ],
            'faq_categories' => [['links'], []],
            'faqs' => [['answer', 'placements'], []],
            'team_members' => [
                ['role', 'focus', 'bio', 'alt', 'areas', 'languages', 'links', 'focal_point'],
                ['photo_pending' => fn (Blueprint $t) => $t->boolean('photo_pending')->default(false)],
            ],
            'specialist_sites' => [
                [],
                [
                    'practice_area' => fn (Blueprint $t) => $t->string('practice_area', 4)->nullable(),
                    'enabled' => fn (Blueprint $t) => $t->boolean('enabled')->default(true),
                ],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as $table => [$longText, $other]) {
            Schema::table($table, function (Blueprint $t) use ($longText, $other) {
                foreach ($longText as $column) {
                    $t->longText($column)->nullable();
                }
                foreach ($other as $add) {
                    $add($t);
                }
                $this->cmsColumns($t);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns() as $table => [$longText, $other]) {
            Schema::table($table, function (Blueprint $t) use ($longText, $other) {
                $t->dropForeign(['updated_by']);
                if (isset($other['key'])) {
                    $t->dropUnique(['key']);
                }
                $t->dropIndex(['status']);
                $t->dropIndex(['copy_source']);
                $t->dropColumn([...$longText, ...array_keys($other), 'status', 'draft', 'published_at', 'publish_at', 'updated_by', 'copy_source', 'deleted_at']);
            });
        }
    }

    /**
     * The status fields of §8.5 and the approval flag of §8.7, on every content table: status (draft | published),
     * draft (JSON text of unpublished changes), published_at, publish_at (scheduled), updated_by, copy_source
     * (docx | pending | client_approved) and soft deletes (the Trash).
     */
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
