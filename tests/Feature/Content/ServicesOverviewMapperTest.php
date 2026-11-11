<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\HomepageMapper;
use App\Content\Import\Mappers\ServicesOverviewMapper;

final class ServicesOverviewMapperTest extends MapperTestCase
{
    private const SERVICES = ['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];

    private const LOCALES = ['en', 'ar', 'ru'];

    private function imported(): array
    {
        return $this->import([new ServicesOverviewMapper]);
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

    /** Includes every card, breadcrumb and resource hyperlink checked against the ID in its marker. */
    public function test_sources_agree_on_labels_links_and_the_whatsapp_draft(): void
    {
        $report = $this->imported()['report'];
        $this->assertSame([], $report->entries('label-mismatch'));
        $this->assertSame([], $report->entries('source-conflict'));
    }

    public function test_eleven_cards_with_exactly_four_situations_in_every_language(): void
    {
        $services = $this->imported()['datasets']['services'];
        $this->assertSame(self::SERVICES, array_keys($services));
        foreach ($services as $code => $service) {
            foreach (self::LOCALES as $locale) {
                $this->assertCount(4, $service['situations'][$locale], "{$code} {$locale}");
                foreach ($service['situations'][$locale] as $situation) {
                    $this->assertIsString($situation);
                    $this->assertNotSame('', $situation);
                }
                $this->assertNotSame('', $service['hub_title'][$locale]);
                $this->assertNotSame('', $service['hub_summary'][$locale]);
                $this->assertNotSame('', $service['hub_link_label'][$locale]);
            }
        }
    }

    public function test_situations_intro_is_one_shared_line_per_language(): void
    {
        $this->assertSame([
            'en' => 'This service may be relevant if:',
            'ar' => 'قد تناسبك هذه الخدمة إذا كنت:',
            'ru' => 'Эта услуга может быть полезна, если:',
        ], $this->imported()['datasets']['ui_strings']['service.situations_intro']);
    }

    public function test_card_copy_is_routed_to_the_matching_service(): void
    {
        $services = $this->imported()['datasets']['services'];
        $this->assertSame('Property and Tenancy Disputes', $services['S05']['hub_title']['en']);
        $this->assertSame('You want a sale or tenancy agreement reviewed before signing.', $services['S05']['situations']['en'][3]);
        $this->assertStringStartsWith('We review unpaid debts and invoices', $services['S01']['hub_summary']['en']);
        $this->assertSame('خدمات الأعمال', $services['S11']['hub_title']['ar']);
        $this->assertSame('Подробнее об услуге', $services['S10']['hub_link_label']['ru']);
    }

    public function test_hub_page_keeps_every_section_in_order(): void
    {
        $p02 = $this->imported()['datasets']['pages']['P02'];
        $this->assertSame('Our Legal Services and Practice Areas in the UAE', $p02['h1']['en']);
        $this->assertSame('خدماتنا القانونية ومناطق الممارسة في الإمارات', $p02['h1']['ar']);
        $this->assertSame('Наши юридические услуги и направления практики в ОАЭ', $p02['h1']['ru']);
        $this->assertSame(['Home', 'Practice Areas'], $p02['breadcrumb']['en']);
        $this->assertSame('Legal Services in the UAE for Individuals and Companies | Summit Legal Consultancy', $p02['seo_title']['en']);

        foreach (self::LOCALES as $locale) {
            $content = $p02['content'][$locale];
            $this->assertSame(['intro', 'start', 'choose', 'overlap', 'before', 'faq', 'resources', 'cta', 'scope'], array_keys($content), $locale);
            $this->assertCount(4, $content['start']['items'], $locale);
            $this->assertCount(2, $content['overlap']['paragraphs'], $locale);
            $this->assertCount(4, $content['before']['items'], $locale);
            $this->assertCount(4, $content['faq']['items'], $locale);
            $this->assertSame(['P03', 'P05'], array_column($content['resources']['links'], 'target'), $locale);
            $this->assertSame('+971 52 466 6191', $content['cta']['phone'], $locale);
            $this->assertSame('info@summitlegaluae.com', $content['cta']['email'], $locale);
            $this->assertCount(2, $content['scope']['paragraphs'], $locale);
        }
        $this->assertSame('We Start with the File', $p02['content']['en']['start']['heading']);
        $this->assertSame('Choose a Service Area', $p02['content']['en']['choose']['heading']);
        $this->assertSame('Legal Notice and Scope of Services', $p02['content']['en']['scope']['link_label']);
    }

    public function test_hub_titles_and_whatsapp_draft_match_the_homepage(): void
    {
        $d = $this->import([new HomepageMapper, new ServicesOverviewMapper])['datasets'];
        foreach ($d['services'] as $code => $service) {
            $this->assertSame($service['title'], $service['hub_title'], $code);
        }
        $this->assertSame($d['settings']['whatsapp_draft'], $d['pages']['P02']['whatsapp_draft']);
    }
}
