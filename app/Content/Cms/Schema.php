<?php

namespace App\Content\Cms;

use App\Models\Content\CaseStudy;
use App\Models\Content\Faq;
use App\Models\Content\FaqCategory;
use App\Models\Content\Guide;
use App\Models\Content\GuideCategory;
use App\Models\Content\Page;
use App\Models\Content\Service;
use App\Models\Content\SpecialistSite;
use App\Models\Content\TeamMember;

/**
 * The content datasets and their columns (docs/briefs/design-2.1-and-admin-cms.md §8.11).
 *
 * A dataset is what the DOCX import writes (database/content/{dataset}.json) and what the views read through
 * App\Content\ContentRepository (content()->page('P01'), ->services(), ->record('specialist_sites', 'X02') …). Each
 * one is a table whose columns are the import's fields:
 *   plain         language-independent columns, as the records carry them (slug, position, anchor …)
 *   json          language-independent JSON text columns (a list of codes)
 *   translatable  {en, ar, ru} columns (spatie/laravel-translatable); their value may be a text or a structure
 *   always        translatable columns every record carries (null in a language without a value); the others are left
 *                 out of a record in a language without a value (the behaviour of the content tables before Phase 3)
 *   blocks        the record field rebuilt from the blocks column (one structure for every language, §8.3), or null
 *   extra         columns the CMS adds that records do not carry (read through their own repository methods)
 *
 * The brief's names map to the tables as: services = practice_areas, faq_categories = faq_groups, specialist_sites =
 * associated_sites; card_summary = home_summary, overview_summary = hub_summary, documents_to_start = documents,
 * associated_site_id = specialist (a site code).
 */
final class Schema
{
    public const DATASETS = [
        'pages' => [
            'model' => Page::class,
            'plain' => ['slug', 'position'],
            'json' => [],
            'translatable' => ['h1', 'seo_title', 'meta_description', 'breadcrumb', 'whatsapp_draft', 'registry_label', 'last_updated'],
            'always' => ['h1', 'seo_title', 'meta_description'],
            'blocks' => 'content',
            'extra' => ['key', 'og_image'],
        ],
        'services' => [
            'model' => Service::class,
            'plain' => ['slug', 'position', 'matter_value', 'specialist'],
            'json' => ['related_guides'],
            'translatable' => ['title', 'home_summary', 'h1', 'intro', 'breadcrumb', 'documents', 'documents_note', 'first_steps', 'mistakes',
                'seo_title', 'meta_description', 'whatsapp_draft', 'hub_title', 'hub_summary', 'hub_link_label', 'registry_label'],
            'always' => ['title'],
            'blocks' => 'sections',
            'extra' => ['og_image'],
        ],
        'guides' => [
            'model' => Guide::class,
            'plain' => ['anchor', 'category', 'position'],
            'json' => [],
            'translatable' => ['title', 'home_title', 'home_summary', 'card_title', 'card_summary'],
            'always' => ['title'],
            'blocks' => 'body',
            'extra' => ['seo_title', 'meta_description', 'standalone', 'og_image'],
        ],
        'guide_categories' => [
            'model' => GuideCategory::class,
            'plain' => ['position'],
            'json' => ['guides'],
            'translatable' => ['name'],
            'always' => ['name'],
            'blocks' => null,
            'extra' => [],
        ],
        'case_studies' => [
            'model' => CaseStudy::class,
            'plain' => ['anchor', 'position'],
            'json' => [],
            'translatable' => ['title', 'card_title', 'card_summary', 'matter', 'home_title', 'home_summary', 'home_link_label'],
            'always' => ['title'],
            'blocks' => 'body',
            'extra' => ['category', 'seo_title', 'meta_description', 'og_image'],
        ],
        'faq_categories' => [
            'model' => FaqCategory::class,
            'plain' => ['anchor', 'position'],
            'json' => [],
            'translatable' => ['name', 'links'],
            'always' => ['name'],
            'blocks' => null,
            'extra' => [],
        ],
        'faqs' => [
            'model' => Faq::class,
            'plain' => ['anchor', 'category', 'position'],
            'json' => [],
            'translatable' => ['question', 'answer'],
            'always' => ['question'],
            'blocks' => null,
            'extra' => ['placements'],
        ],
        'team_members' => [
            'model' => TeamMember::class,
            'plain' => ['anchor', 'position', 'portrait'],
            'json' => [],
            'translatable' => ['name', 'role', 'focus', 'bio', 'alt', 'areas', 'languages', 'links'],
            'always' => ['name'],
            'blocks' => null,
            'extra' => ['focal_point', 'photo_pending'],
        ],
        'specialist_sites' => [
            'model' => SpecialistSite::class,
            'plain' => ['position', 'domain', 'url'],
            'json' => [],
            'translatable' => ['label'],
            'always' => ['label'],
            'blocks' => null,
            'extra' => ['practice_area', 'enabled'],
        ],
    ];

    /** Fields of the import that are not columns of their dataset: services.situations lives in the situations table. */
    public const DERIVED = ['services' => ['situations']];

    /** The table of a dataset (the dataset names are the table names). */
    public static function table(string $dataset): string
    {
        return (new (self::DATASETS[$dataset]['model']))->getTable();
    }

    /** @return list<string> every JSON text column of a dataset (translatable, json, blocks, draft) */
    public static function jsonColumns(string $dataset): array
    {
        $d = self::DATASETS[$dataset];

        return [...$d['translatable'], ...$d['json'], ...($d['blocks'] !== null ? ['blocks'] : []), ...($dataset === 'team_members' ? ['focal_point'] : []), ...($dataset === 'faqs' ? ['placements'] : [])];
    }
}
