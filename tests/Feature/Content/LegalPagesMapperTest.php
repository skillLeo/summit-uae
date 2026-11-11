<?php

namespace Tests\Feature\Content;

use App\Content\Docx\Block;
use App\Content\Docx\DocxReader;
use App\Content\Import\Mappers\LegalPagesMapper;

final class LegalPagesMapperTest extends MapperTestCase
{
    private const FILES = ['P08' => '08_Legal_Notice', 'L01' => 'L01_Privacy_Policy', 'L02' => 'L02_Terms_and_Conditions'];

    private const LOCALES = ['en', 'ar', 'ru'];

    private function imported(): array
    {
        return $this->import([new LegalPagesMapper]);
    }

    /** @return array<string, mixed> */
    private function legalPages(): array
    {
        return $this->imported()['datasets']['pages'];
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
        $this->assertSame([], $this->imported()['report']->entries('source-conflict'));
    }

    public function test_section_count_and_headings_match_the_source_files(): void
    {
        $pages = $this->legalPages();
        $this->assertCount(9, $pages['P08']['content']['en']['sections']);
        $this->assertCount(14, $pages['L01']['content']['en']['sections']);
        $this->assertCount(14, $pages['L02']['content']['en']['sections']);

        $reader = new DocxReader;
        foreach (self::FILES as $code => $prefix) {
            foreach (self::LOCALES as $locale) {
                $files = glob(base_path("package/docx/{$locale}/{$prefix}*.docx")) ?: [];
                $this->assertCount(1, $files, "{$locale} {$prefix}");
                $h2 = array_values(array_filter($reader->read($files[0], $locale)->publicBlocks(), fn (Block $b) => $b->isHeading(2)));
                $sections = $pages[$code]['content'][$locale]['sections'];
                $this->assertSame(array_map(fn (Block $b) => $b->text, $h2), array_column($sections, 'heading'), "{$locale} {$code}");
            }
        }
    }

    public function test_heading_last_updated_breadcrumb_and_metadata(): void
    {
        $pages = $this->legalPages();
        $this->assertSame('Legal Notice and Scope of Services', $pages['P08']['h1']['en']);
        $this->assertSame('Privacy Policy', $pages['L01']['h1']['en']);
        $this->assertSame('Terms and Conditions', $pages['L02']['h1']['en']);
        foreach (self::FILES as $code => $prefix) {
            $this->assertSame('Last updated: 23 October 2026', $pages[$code]['last_updated']['en'], $code);
            $this->assertSame([
                ['label' => 'Home', 'target' => 'P01'],
                ['label' => $pages[$code]['h1']['en'], 'target' => null],
            ], $pages[$code]['breadcrumb']['en'], $code);
            $this->assertSame($pages[$code]['h1']['en'].' | Summit Legal Consultancy', $pages[$code]['seo_title']['en'], $code);
            $this->assertArrayNotHasKey('whatsapp_draft', $pages[$code], "{$code} supplies no page-specific WhatsApp draft");
            foreach (self::LOCALES as $locale) {
                $this->assertSame('P01', $pages[$code]['breadcrumb'][$locale][0]['target'], "{$locale} {$code}");
                $this->assertNotSame('', $pages[$code]['meta_description'][$locale]);
            }
        }
        $this->assertSame('Последнее обновление: 23 октября 2026 года', $pages['P08']['last_updated']['ru']);
        $this->assertStringStartsWith('The nature and limits of use', $pages['P08']['meta_description']['en']);
    }

    public function test_body_is_typed_blocks_and_stops_before_links_and_metadata(): void
    {
        foreach ($this->legalPages() as $code => $page) {
            foreach (self::LOCALES as $locale) {
                $content = $page['content'][$locale];
                $this->assertCount(1, $content['intro'], "{$locale} {$code} intro");
                $texts = $content['intro'];
                foreach ($content['sections'] as $section) {
                    $this->assertNotEmpty($section['blocks'], "{$locale} {$code} {$section['heading']}");
                    foreach ($section['blocks'] as $block) {
                        $this->assertContains($block['type'], ['p', 'ul', 'ol']);
                        $texts = array_merge($texts, $block['type'] === 'p' ? [$block['text']] : $block['items']);
                    }
                }
                $this->assertNotContains($page['seo_title'][$locale], $texts, "{$locale} {$code}");
                $this->assertNotContains($page['meta_description'][$locale], $texts, "{$locale} {$code}");
                $this->assertNotContains($page['last_updated'][$locale], $texts, "{$locale} {$code}");
                $this->assertEmpty(array_intersect(array_column($content['links'], 'label'), $texts), "{$locale} {$code}");
            }
        }
        $first = $this->legalPages()['P08']['content']['en']['sections'][0];
        $this->assertSame('1. General Information and Advice on Your Matter', $first['heading']);
        $this->assertCount(2, $first['blocks']);
    }

    public function test_verbatim_heading_numbers_and_arabic_digits_are_flagged_for_review(): void
    {
        $notes = array_map(fn ($e) => [$e['locale'], $e['subject']], $this->imported()['report']->entries('note'));
        $this->assertSame([
            ['en', 'P08, L01, L02 sections'],
            ['ar', 'P08, L01, L02 sections'],
            ['ar', 'P08, L01, L02 digits'],
            ['ru', 'P08, L01, L02 sections'],
        ], $notes);
        $this->assertSame('١. المعلومات العامة والاستشارة الخاصة', $this->legalPages()['P08']['content']['ar']['sections'][0]['heading']);
    }

    public function test_contact_and_cross_links_use_registry_ids(): void
    {
        $expected = [
            'P08' => ['EMAIL', 'TEL', 'P07', 'L01', 'L02'],
            'L01' => ['EMAIL', 'TEL', 'P07', 'P08', 'L02'],
            'L02' => ['EMAIL', 'TEL', 'P07', 'P08', 'L01'],
        ];
        $pages = $this->legalPages();
        foreach ($expected as $code => $targets) {
            foreach (self::LOCALES as $locale) {
                $this->assertSame($targets, array_column($pages[$code]['content'][$locale]['links'], 'target'), "{$locale} {$code}");
            }
        }
        $this->assertSame(
            ['info@summitlegaluae.com', '+971 52 466 6191', 'Contact Summit', 'Privacy Policy', 'Terms and Conditions'],
            array_column($pages['P08']['content']['en']['links'], 'label'),
        );
    }

    public function test_cookie_preference_strings_are_interface_copy_in_every_language(): void
    {
        $ui = $this->imported()['datasets']['ui_strings'];
        $this->assertCount(22, $ui);
        foreach ($ui as $key => $values) {
            $this->assertStringStartsWith('cookies.', $key);
            foreach (self::LOCALES as $locale) {
                $this->assertNotSame('', (string) $values[$locale], "{$locale} {$key}");
            }
        }
        $this->assertSame('Privacy Choices', $ui['cookies.banner_title']['en']);
        $this->assertSame('Accept Optional Tools', $ui['cookies.accept_optional']['en']);
        $this->assertSame('Reject Optional Tools', $ui['cookies.reject_optional']['en']);
        $this->assertSame('Customise Choices', $ui['cookies.customise']['en']);
        $this->assertSame('Cookie or Technology Name', $ui['cookies.column_name']['en']);
        $this->assertSame('Your privacy choices have been saved.', $ui['cookies.saved']['en']);
        $this->assertSame('Privacy Settings', $ui['cookies.footer_button']['en']);
        $this->assertSame('Privacy Policy', $ui['cookies.policy_link']['en']);
        $this->assertSame('سياسة الخصوصية', $ui['cookies.policy_link']['ar']);

        // Interface strings are not policy body text.
        $body = json_encode($this->legalPages()['L01']['content']['en'], JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($ui['cookies.banner_text']['en'], (string) $body);
    }
}
