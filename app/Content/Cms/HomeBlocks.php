<?php

namespace App\Content\Cms;

/**
 * The home page's blocks and their defaults (docs/briefs/design-2.1-and-admin-cms.md §5.1, §8.4; until Phase 3 these
 * switches were config('design.d2.home.blocks')).
 *
 * The approved sections 01-09 are in the order of 01_Homepage_EN_Revised.docx (the same in Arabic and Russian). The
 * switchable blocks sit between approved sections, never inside one:
 *     01 hero
 *        [start]      "Start your matter" (pending copy)
 *     02 services
 *        [anatomy]    "Anatomy of a Document" (pending copy)
 *     03 who we help
 *     04 review       "What a Review of Your Matter Can Clarify"
 *        [navigator]  "Where Do You Stand?" (pending copy)
 *     05 about
 *        [team]       the "Our Team" grid: off by default (D24, client question 7)
 *     06 case studies
 *     07 guides
 *     08 questions
 *     09 contact      #consultation-form
 *
 * keys: the P01 content keys a block carries (the views read them as $c['hero'] …); a block without keys renders copy
 * from other records and the interface text. anchor: the section's id. background: white | band | dark (the brief's
 * three; rendered by the block's own classes until the Phase 4 builder applies it).
 */
final class HomeBlocks
{
    public const PAGE = 'P01';

    public const BLOCKS = [
        ['type' => 'hero', 'keys' => ['hero'], 'anchor' => null, 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
        ['type' => 'start', 'keys' => [], 'anchor' => 'start', 'background' => 'white', 'copy_source' => CopySource::PENDING, 'enabled' => true],
        ['type' => 'services', 'keys' => ['services'], 'anchor' => 'services', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
        ['type' => 'anatomy', 'keys' => [], 'anchor' => 'anatomy', 'background' => 'white', 'copy_source' => CopySource::PENDING, 'enabled' => true],
        ['type' => 'who', 'keys' => ['who_we_help'], 'anchor' => 'who-we-help', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
        ['type' => 'review', 'keys' => ['review'], 'anchor' => 'review', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
        ['type' => 'navigator', 'keys' => [], 'anchor' => 'navigator', 'background' => 'dark', 'copy_source' => CopySource::PENDING, 'enabled' => true],
        ['type' => 'about', 'keys' => ['about'], 'anchor' => 'about', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
        ['type' => 'team', 'keys' => [], 'anchor' => 'our-team', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => false],
        ['type' => 'cases', 'keys' => ['cases'], 'anchor' => 'case-studies', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
        ['type' => 'guides', 'keys' => ['guides'], 'anchor' => 'guides', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
        ['type' => 'faq', 'keys' => ['faq'], 'anchor' => 'questions', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
        ['type' => 'contact', 'keys' => ['contact', 'form_home_variant'], 'anchor' => 'consultation-form', 'background' => 'white', 'copy_source' => CopySource::DOCX, 'enabled' => true],
    ];

    /** The block types that have a component in resources/views/components/d2/blocks/. */
    public static function types(): array
    {
        return array_column(self::BLOCKS, 'type');
    }
}
