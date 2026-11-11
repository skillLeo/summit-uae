<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\AboutMapper;
use App\Content\Import\Mappers\HomepageMapper;

final class AboutMapperTest extends MapperTestCase
{
    private const LOCALES = ['en', 'ar', 'ru'];

    private const TEAM = ['team-ahmed', 'team-mohamed', 'team-sarah'];

    private function imported(): array
    {
        return $this->import([new AboutMapper]);
    }

    public function test_every_public_block_is_mapped_in_all_languages(): void
    {
        $this->assertFullCoverage($this->imported());
    }

    public function test_no_designer_note_reaches_public_output(): void
    {
        $this->assertNoDesignerNotes($this->imported());
    }

    public function test_languages_have_identical_structure(): void
    {
        $this->assertNoParityIssues($this->imported());
    }

    public function test_sources_agree_on_plain_fields_and_link_destinations(): void
    {
        $conflicts = array_map(
            fn ($e) => "{$e['locale']} {$e['subject']}: {$e['detail']}",
            $this->imported()['report']->entries('source-conflict'),
        );
        $this->assertSame([], $conflicts);
    }

    public function test_three_team_members_in_the_fixed_order(): void
    {
        $team = $this->imported()['datasets']['team_members'];
        $this->assertSame(self::TEAM, array_keys($team));

        $members = array_values($team);
        $this->assertSame(['Ahmed Mohammadi', 'Dr Mohamed Raouf', 'Ms Sarah Zaki'], array_map(fn ($m) => $m['name']['en'], $members));
        $this->assertSame([1, 2, 3], array_map(fn ($m) => $m['position'], $members));
        $this->assertSame(self::TEAM, array_map(fn ($m) => $m['anchor'], $members));
        $this->assertSame(['ahmed-mohammadi', 'dr-mohamed-raouf', null], array_map(fn ($m) => $m['portrait'], $members));
    }

    public function test_each_profile_is_complete_in_every_language(): void
    {
        $team = $this->imported()['datasets']['team_members'];
        foreach ($team as $code => $m) {
            foreach (self::LOCALES as $locale) {
                foreach (['name', 'role', 'focus', 'alt', 'languages'] as $field) {
                    $this->assertNotSame('', $m[$field][$locale] ?? '', "{$locale} {$code}.{$field}");
                }
                $this->assertNotSame($m['name'][$locale], $m['alt'][$locale], "{$locale} {$code}: alt text is not the visible name");
                $this->assertCount(4, $m['bio'][$locale], "{$locale} {$code}.bio");
                $this->assertNotSame('', $m['areas'][$locale]['heading']);
            }
        }

        $this->assertSame('Managing Director and Co-Founder', $team['team-ahmed']['role']['en']);
        $this->assertSame('Principal Legal Consultant', $team['team-mohamed']['role']['en']);
        $this->assertSame('Senior Legal Consultant', $team['team-sarah']['role']['en']);
        $this->assertSame('Doctorate in Law · Civil, Commercial and Criminal Legal Advice', $team['team-mohamed']['focus']['en']);
        $this->assertSame('Working Languages: Arabic · English · Russian', $team['team-ahmed']['languages']['en']);
        $this->assertSame('Working Languages: Arabic · English', $team['team-mohamed']['languages']['en']);
        $this->assertSame('Working Languages: Arabic · English', $team['team-sarah']['languages']['en']);
        $this->assertSame([7, 10, 8], array_map(fn ($code) => count($team[$code]['areas']['en']['items']), self::TEAM));
    }

    public function test_page_keeps_every_approved_section(): void
    {
        $p = $this->imported()['datasets']['pages']['P06'];
        $this->assertSame('About Summit Legal Consultancy and Our Team', $p['h1']['en']);
        $this->assertSame('من نحن وفريق سَمِت للاستشارات القانونية', $p['h1']['ar']);
        $this->assertSame('О Summit Legal Consultancy и нашей команде', $p['h1']['ru']);
        $this->assertSame('About Us and Our Team | Summit Legal Consultancy in the UAE', $p['seo_title']['en']);
        $this->assertSame([['label' => 'Home', 'target' => 'P01'], ['label' => 'About Us and Our Team', 'target' => null]], $p['breadcrumb']['en']);

        $en = $p['content']['en'];
        $this->assertSame(['hero', 'story', 'approach', 'process', 'team', 'scope', 'confidentiality', 'cta'], array_keys($en));
        $this->assertSame('A careful review of your matter. A clear view of the next step.', $en['hero']['lead']);
        $this->assertCount(3, $en['story']['paragraphs']);
        $this->assertCount(6, $en['approach']['principles']);
        $this->assertCount(4, $en['process']['steps']);
        // Approved step headings keep their own sequence number, verbatim.
        $this->assertSame('1. We Understand What Happened and What You Need', $en['process']['steps'][0]['title']);
        $this->assertSame(['anchor' => 'team', 'heading' => 'Our Team'], array_intersect_key($en['team'], ['anchor' => 0, 'heading' => 0]));
        $this->assertStringStartsWith('Summit is a legal consultancy.', $en['scope']['paragraphs'][1]);
        $this->assertCount(3, $en['confidentiality']['paragraphs']);
    }

    public function test_links_store_destination_ids_not_fallback_urls(): void
    {
        $d = $this->imported()['datasets'];
        $targets = fn (array $links) => array_map(fn ($l) => $l['target'] ?? null, $links);
        foreach (self::LOCALES as $locale) {
            $content = $d['pages']['P06']['content'][$locale];
            $this->assertSame(['team', 'P07'], $targets($content['hero']['links']), $locale);
            $this->assertSame(['P02', 'P04'], $targets($content['process']['links']), $locale);
            $this->assertSame(self::TEAM, $targets($content['team']['menu']), $locale);
            $this->assertSame(['P08'], $targets($content['confidentiality']['links']), $locale);
            $this->assertSame('P07', $content['cta']['consultation']['target'], $locale);
            $this->assertSame('WA01', $content['cta']['whatsapp']['target'], $locale);
            $this->assertSame(['P05', 'P03'], $targets($content['cta']['links']), $locale);
            $this->assertSame('tel:+971524666191', $content['cta']['phone']['href'], $locale);
            $this->assertSame('mailto:info@summitlegaluae.com', $content['cta']['email']['href'], $locale);

            $this->assertSame(['P07'], $targets($d['team_members']['team-ahmed']['links'][$locale]), $locale);
            $this->assertSame(['S02', 'S01', 'S03'], $targets($d['team_members']['team-mohamed']['links'][$locale]), $locale);
            $this->assertSame(['S04', 'S09'], $targets($d['team_members']['team-sarah']['links'][$locale]), $locale);
        }

        // Every target is an ID or page anchor; null only marks the current-page breadcrumb.
        array_walk_recursive($d, function ($value, $key) {
            if ($key === 'target' && $value !== null) {
                $this->assertMatchesRegularExpression('/^[A-Za-z0-9-]+$/', $value, 'A link target must be an ID or anchor, never a URL');
            }
        });
    }

    public function test_draft_matches_the_homepage_wa01_and_merges_without_conflict(): void
    {
        // Runs alongside the reference mapper: any write to a field it owns would raise an import error.
        $d = $this->import([new HomepageMapper, new AboutMapper])['datasets'];
        foreach (self::LOCALES as $locale) {
            $this->assertSame($d['settings']['whatsapp_draft'][$locale], $d['pages']['P06']['whatsapp_draft'][$locale], $locale);
        }
        $this->assertArrayHasKey('P01', $d['pages']);
    }
}
