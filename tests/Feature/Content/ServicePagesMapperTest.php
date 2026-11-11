<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\HomepageMapper;
use App\Content\Import\Mappers\ServicePagesMapper;
use App\Content\Import\Mappers\ServicesOverviewMapper;

final class ServicePagesMapperTest extends MapperTestCase
{
    private const SERVICES = ['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];

    private const LOCALES = ['en', 'ar', 'ru'];

    private const SECTIONS = ['who_for', 'matters', 'practical', 'process', 'documents', 'next_step', 'faq', 'related', 'sources', 'specialist', 'cta', 'scope'];

    private function imported(): array
    {
        return $this->import([new ServicePagesMapper]);
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

    /** Covers every hyperlink against its marker ID, FAQ and workflow numbering, and the shared next-step H3s. */
    public function test_sources_agree_on_links_numbering_headings_and_the_whatsapp_draft(): void
    {
        $report = $this->imported()['report'];
        $this->assertSame([], $report->entries('source-conflict'));
        $this->assertSame([], $report->entries('label-mismatch'));
    }

    /** Parity only compares shapes; IDs and URLs kept inside the translatable sections must also be equal. */
    public function test_language_independent_ids_inside_sections_are_identical_in_every_language(): void
    {
        $ids = fn (array $s) => [
            'related' => array_column($s['related']['links'], 'target'),
            'faq' => array_column($s['faq']['items'], 'id'),
            'sources' => array_column($s['sources']['links'], 'url'),
            'specialist' => $s['specialist']['domain'] ?? null,
        ];
        foreach ($this->imported()['datasets']['services'] as $code => $service) {
            $en = $ids($service['sections']['en']);
            $this->assertSame(array_map(fn (int $i) => sprintf('%s-Q%02d', $code, $i + 1), array_keys($en['faq'])), $en['faq'], $code);
            foreach (['ar', 'ru'] as $locale) {
                $this->assertSame($en, $ids($service['sections'][$locale]), "{$code} {$locale}");
            }
        }
    }

    public function test_specialist_codes_match_the_homepage_specialist_sites(): void
    {
        $d = $this->import([new HomepageMapper, new ServicesOverviewMapper, new ServicePagesMapper])['datasets'];
        foreach ($d['services'] as $code => $service) {
            if ($service['specialist'] !== null) {
                $this->assertSame($d['specialist_sites'][$service['specialist']]['domain'], $service['sections']['en']['specialist']['domain'], $code);
            }
        }
    }

    public function test_eleven_service_pages_with_their_own_codes(): void
    {
        $services = $this->imported()['datasets']['services'];
        $this->assertSame(self::SERVICES, array_keys($services));
        foreach ($services as $code => $service) {
            $this->assertSame($code, $service['matter_value']);
        }
        $this->assertSame('Property and Tenancy Disputes in Dubai and the UAE', $services['S05']['h1']['en']);
        $this->assertSame('المنازعات العقارية والإيجارية في دبي والإمارات', $services['S05']['h1']['ar']);
        $this->assertSame('Споры по недвижимости и аренде в Дубае и ОАЭ', $services['S05']['h1']['ru']);
        $this->assertSame(['Home', 'Practice Areas', 'Property and Tenancy Disputes'], $services['S05']['breadcrumb']['en']);
    }

    public function test_every_documents_checklist_has_four_items_and_a_note(): void
    {
        foreach ($this->imported()['datasets']['services'] as $code => $service) {
            foreach (self::LOCALES as $locale) {
                $this->assertCount(4, $service['documents'][$locale], "{$code} {$locale}");
                $this->assertNotSame('', $service['documents_note'][$locale], "{$code} {$locale}");
                $this->assertNotEmpty($service['first_steps'][$locale], "{$code} {$locale}");
                $this->assertNotEmpty($service['mistakes'][$locale], "{$code} {$locale}");
            }
        }
    }

    public function test_property_documents_start_with_the_sale_or_tenancy_agreement(): void
    {
        $s05 = $this->imported()['datasets']['services']['S05'];
        $this->assertStringStartsWith('The sale or tenancy agreement', $s05['documents']['en'][0]);
        $this->assertCount(4, $s05['first_steps']['en']);
        $this->assertSame('Signing a settlement under pressure or out of exhaustion without reviewing it', $s05['mistakes']['en'][3]);
    }

    public function test_related_guides_are_only_guide_codes_in_link_order(): void
    {
        $services = $this->imported()['datasets']['services'];
        foreach ($services as $code => $service) {
            $this->assertIsArray($service['related_guides'], $code);
            foreach ($service['related_guides'] as $guide) {
                $this->assertMatchesRegularExpression('/^G0[1-9]$/', $guide, $code);
            }
        }
        $this->assertSame('G04', $services['S05']['related_guides'][0]);
        $this->assertSame(['G02', 'G03'], $services['S01']['related_guides']);
        $this->assertSame([], $services['S02']['related_guides']);
        $this->assertSame([], $services['S11']['related_guides']);
    }

    public function test_specialist_codes_come_from_the_link_notes(): void
    {
        $specialists = array_map(fn (array $s) => $s['specialist'], $this->imported()['datasets']['services']);
        $this->assertSame([
            'S01' => 'X02', 'S02' => null, 'S03' => 'X04', 'S04' => null, 'S05' => 'X03', 'S06' => 'X01',
            'S07' => 'X06', 'S08' => 'X05', 'S09' => null, 'S10' => null, 'S11' => null,
        ], $specialists);
        $s05 = $this->imported()['datasets']['services']['S05']['sections'];
        $this->assertSame('dubaipropertydispute.com', $s05['en']['specialist']['domain']);
        $this->assertSame('Summit Specialist Resources', $s05['en']['specialist']['label']);
        $this->assertNull($this->imported()['datasets']['services']['S02']['sections']['ru']['specialist']);
    }

    public function test_sections_follow_the_page_order_and_keep_h2_headings_verbatim(): void
    {
        $services = $this->imported()['datasets']['services'];
        foreach ($services as $code => $service) {
            foreach (self::LOCALES as $locale) {
                $this->assertSame(self::SECTIONS, array_keys($service['sections'][$locale]), "{$code} {$locale}");
            }
        }
        $s05 = $services['S05']['sections']['en'];
        $this->assertSame([
            'Who This Service Is For', 'Matters Covered by the Review', 'Practical Situations We Review with You',
            'How We Handle Your Matter', 'Documents That Help Us Get Started', 'Before Taking the Next Step', 'FAQs',
            'Related Topics and Services', 'Official Sources for Reference', 'Tell Us About Your Matter',
            'Scope of Service and Published Information',
        ], array_values(array_filter(array_map(fn ($s) => $s['heading'] ?? null, $s05))));
        $this->assertSame('Useful First Steps', $s05['next_step']['first_steps_heading']);
        $this->assertSame('Mistakes to Avoid', $s05['next_step']['mistakes_heading']);
        $this->assertCount(4, $s05['practical']['items']);
        $this->assertCount(5, $s05['process']['steps']);
        $this->assertSame(['S05-Q01', 'S05-Q02', 'S05-Q03', 'S05-Q04', 'S05-Q05'], array_column($s05['faq']['items'], 'id'));
        $this->assertSame('Request a Legal Consultation', $s05['cta']['request_label']);
        $this->assertSame('+971 52 466 6191', $s05['cta']['phone']);
    }

    public function test_internal_links_are_ids_and_sources_keep_their_real_urls(): void
    {
        $services = $this->imported()['datasets']['services'];
        foreach ($services as $code => $service) {
            foreach (self::LOCALES as $locale) {
                $sections = $service['sections'][$locale];
                foreach ($sections['related']['links'] as $link) {
                    $this->assertMatchesRegularExpression('/^[PSGA]\d{2}$/', $link['target'], "{$code} {$locale}");
                }
                $this->assertNotEmpty($sections['sources']['links'], "{$code} {$locale}");
                foreach ($sections['sources']['links'] as $link) {
                    $this->assertStringStartsWith('https://', $link['url']);
                    $this->assertStringNotContainsString('summitlegaluae.com', $link['url']);
                }
                foreach ($sections['faq']['items'] as $item) {
                    $this->assertMatchesRegularExpression('/^'.$code.'-Q\d{2}$/', $item['id']);
                }
            }
        }
        $this->assertSame(['G02', 'G03', 'S04', 'A03', 'P05', 'P02'], array_column($services['S01']['sections']['en']['related']['links'], 'target'));
        $this->assertSame('https://dubailand.gov.ae/ar/', $services['S05']['sections']['ru']['sources']['links'][0]['url']);
    }

    public function test_page_specific_extensions_are_kept_in_every_language(): void
    {
        $services = $this->imported()['datasets']['services'];
        foreach (self::LOCALES as $locale) {
            $item = $services['S01']['sections'][$locale]['practical']['items'][2];
            $this->assertSame([], $item['paragraphs'], $locale);
            $this->assertCount(3, $item['bullets'], $locale);
            $this->assertNotNull($services['S03']['sections'][$locale]['process']['intro'], $locale);
            $this->assertNull($services['S05']['sections'][$locale]['process']['intro'], $locale);
        }
        $this->assertStringStartsWith('For creditors:', $services['S01']['sections']['en']['practical']['items'][2]['bullets'][0]);
        $this->assertCount(4, $services['S10']['sections']['en']['process']['steps']);
    }

    public function test_titles_and_whatsapp_draft_match_the_homepage_without_merge_conflicts(): void
    {
        $d = $this->import([new HomepageMapper, new ServicesOverviewMapper, new ServicePagesMapper])['datasets'];
        foreach ($d['services'] as $code => $service) {
            foreach (self::LOCALES as $locale) {
                $this->assertSame($service['title'][$locale], $service['breadcrumb'][$locale][2], "{$code} {$locale}");
            }
            $this->assertSame($d['settings']['whatsapp_draft'], $service['whatsapp_draft'], $code);
        }
    }
}
