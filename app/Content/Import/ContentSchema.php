<?php

namespace App\Content\Import;

/**
 * Datasets produced by the import and the fields that are language-independent.
 * Every other field is stored translatable as {en, ar, ru}.
 */
final class ContentSchema
{
    /** dataset => non-translatable fields */
    public const DATASETS = [
        // P01–P08, L01, L02, U01: page-level copy (h1, seo_title, meta_description, content{…})
        'pages' => ['slug', 'position'],
        // S01–S11
        'services' => ['slug', 'position', 'related_guides', 'specialist', 'matter_value'],
        // G01–G09 (sections of P03)
        'guides' => ['anchor', 'category', 'position'],
        // Knowledge-centre categories (gc-01…gc-03) grouping the guides
        'guide_categories' => ['position', 'guides'],
        // case-01…case-06 (sections of P04)
        'case_studies' => ['anchor', 'position'],
        // cat-01…cat-12 (P05)
        'faq_categories' => ['anchor', 'position'],
        // faq-01…faq-74 (P05)
        'faqs' => ['anchor', 'category', 'position'],
        // team-ahmed, team-mohamed, team-sarah (P06)
        'team_members' => ['anchor', 'position', 'portrait'],
        // X01–X06
        'specialist_sites' => ['domain', 'url', 'position'],
        // key => text; shared interface strings (navigation, form, controls…)
        'ui_strings' => [],
        // key => value; WA01 draft and link, phone, email, hours…
        'settings' => [],
    ];

    /** Datasets stored as flat key => {en, ar, ru} maps instead of records. */
    public const FLAT = ['ui_strings', 'settings'];

    public static function isPlain(string $dataset, string $field): bool
    {
        return in_array($field, self::DATASETS[$dataset] ?? [], true);
    }
}
