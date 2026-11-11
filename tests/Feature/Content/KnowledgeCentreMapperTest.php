<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\KnowledgeCentreMapper;

final class KnowledgeCentreMapperTest extends MapperTestCase
{
    private const LOCALES = ['en', 'ar', 'ru'];

    private const ANCHORS = [
        'G01' => 'unpaid-salary',
        'G02' => 'commercial-debt',
        'G03' => 'bounced-cheques',
        'G04' => 'property-delay',
        'G05' => 'non-muslim-wills',
        'G06' => 'digital-fraud',
        'G07' => 'compliance-review',
        'G08' => 'legal-translation',
        'G09' => 'tax-records',
    ];

    private function imported(): array
    {
        return $this->import([new KnowledgeCentreMapper]);
    }

    /** @return array<string, mixed> */
    private function guides(): array
    {
        return $this->imported()['datasets']['guides'];
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

    public function test_sources_raise_no_conflicts(): void
    {
        $conflicts = array_map(fn ($e) => "{$e['locale']} {$e['subject']}: {$e['detail']}", $this->imported()['report']->entries('source-conflict'));
        $this->assertSame([], $conflicts);
    }

    public function test_three_categories_group_the_guides_in_source_order(): void
    {
        $categories = $this->imported()['datasets']['guide_categories'];
        $this->assertSame(['gc-01', 'gc-02', 'gc-03'], array_keys($categories));
        $this->assertSame(['G01', 'G02', 'G03'], $categories['gc-01']['guides']);
        $this->assertSame(['G04', 'G05'], $categories['gc-02']['guides']);
        $this->assertSame(['G06', 'G07', 'G08', 'G09'], $categories['gc-03']['guides']);
        $this->assertSame([1, 2, 3], array_column($categories, 'position'));
        $this->assertSame('Employment and Financial Claims', $categories['gc-01']['name']['en']);
        $this->assertSame('العقارات والأسرة', $categories['gc-02']['name']['ar']);
        $this->assertSame('Цифровые доказательства и организация бизнеса', $categories['gc-03']['name']['ru']);
    }

    public function test_nine_guides_keep_their_registered_anchors_categories_and_positions(): void
    {
        $guides = $this->guides();
        $this->assertSame(array_keys(self::ANCHORS), array_keys($guides));
        $this->assertSame(self::ANCHORS, array_map(fn ($g) => $g['anchor'], $guides));
        $this->assertSame(config('summit.guides'), array_map(fn ($g) => $g['anchor'], $guides));
        $this->assertSame(range(1, 9), array_values(array_map(fn ($g) => $g['position'], $guides)));
        $this->assertSame(
            ['gc-01', 'gc-01', 'gc-01', 'gc-02', 'gc-02', 'gc-03', 'gc-03', 'gc-03', 'gc-03'],
            array_values(array_map(fn ($g) => $g['category'], $guides)),
        );
        $this->assertSame('Unpaid Salary and How to Document Your Claim', $guides['G01']['card_title']['en']);
        $this->assertSame('الراتب المتأخر وكيف توثق مطالبتك', $guides['G01']['card_title']['ar']);
        $this->assertSame('Как систематизировать налоговые документы компании', $guides['G09']['card_title']['ru']);
        foreach ($guides as $code => $guide) {
            foreach (self::LOCALES as $locale) {
                $this->assertNotSame('', $guide['card_summary'][$locale] ?? '', "{$code} {$locale} card summary");
            }
            $this->assertArrayNotHasKey('home_title', $guide, 'home_title is owned by the homepage mapper');
            $this->assertArrayNotHasKey('home_summary', $guide, 'home_summary is owned by the homepage mapper');
        }
    }

    public function test_every_guide_body_is_complete_in_each_language(): void
    {
        foreach ($this->guides() as $code => $guide) {
            foreach (self::LOCALES as $locale) {
                $body = $guide['body'][$locale];
                $where = "{$code} {$locale}";
                $this->assertNotSame('', $body['title'], $where);
                $this->assertCount(1, $body['intro'], $where);
                $this->assertNotSame('', $body['audience']['heading'], $where);
                $this->assertCount(3, $body['sections'], $where);
                foreach ([...$body['sections'], $body['audience'], $body['considerations']] as $section) {
                    $this->assertNotSame('', $section['heading'], $where);
                    $this->assertNotEmpty($section['blocks'], $where);
                }
                $this->assertCount(5, $body['steps']['items'], $where);
                $this->assertCount(3, $body['documents']['items'], $where);
                $this->assertCount(4, $body['mistakes']['items'], $where);
                $this->assertCount(2, $body['faqs']['items'], $where);
                foreach ($body['faqs']['items'] as $faq) {
                    $this->assertNotSame('', $faq['question'], $where);
                    $this->assertNotSame('', $faq['answer'], $where);
                }
                $this->assertCount(5, $body['related']['items'], $where);
            }
        }
    }

    public function test_guide_headings_are_kept_verbatim(): void
    {
        $body = $this->guides()['G01']['body'];
        $this->assertSame('Unpaid Salary in the UAE and How to Document Your Claim', $body['en']['title']);
        $this->assertSame('Who This Guide Is For', $body['en']['audience']['heading']);
        $this->assertSame('Gather Evidence of Your Salary and Non-Payment', $body['en']['sections'][0]['heading']);
        $this->assertSame('Other Points to Consider', $body['en']['considerations']['heading']);
        $this->assertSame('Steps to Prepare Your File', $body['en']['steps']['heading']);
        $this->assertSame('Documents to Help with the Review', $body['en']['documents']['heading']);
        $this->assertSame('Common Mistakes to Avoid', $body['en']['mistakes']['heading']);
        $this->assertSame('Questions About This Guide', $body['en']['faqs']['heading']);
        $this->assertSame('Is a Bank Statement Enough to Prove the Full Salary Due?', $body['en']['faqs']['items'][0]['question']);
        $this->assertSame('Official Sources for Reference', $body['en']['sources']['heading']);
        $this->assertSame('Related Services and Topics', $body['en']['related']['heading']);
        $this->assertSame('الراتب المتأخر في الإمارات وكيف توثق مطالبتك', $body['ar']['title']);
        $this->assertSame('Задержка зарплаты в ОАЭ и документальное подтверждение требований', $body['ru']['title']);
    }

    public function test_lists_and_paragraphs_keep_their_source_form(): void
    {
        $guides = $this->guides();
        foreach (self::LOCALES as $locale) {
            // G07's first subsection is a bullet list in every language, not prose.
            $signs = $guides['G07']['body'][$locale]['sections'][0]['blocks'];
            $this->assertSame(['ul'], array_column($signs, 'type'), $locale);
            $this->assertCount(5, $signs[0]['items'], $locale);
            // Two paragraphs stay two paragraphs.
            $this->assertSame(['p', 'p'], array_column($guides['G01']['body'][$locale]['sections'][2]['blocks'], 'type'), $locale);
        }
        $this->assertSame('Review the licence and authorised activities.', $guides['G07']['body']['en']['steps']['items'][0]);
    }

    public function test_official_sources_keep_their_real_urls(): void
    {
        $expected = [
            'G01' => ['R01'], 'G02' => ['R02'], 'G03' => ['R02'], 'G04' => ['R03', 'R04'], 'G05' => ['R05', 'R06'],
            'G06' => ['R07', 'R08', 'R09'], 'G07' => ['R10', 'R11', 'R12', 'R13', 'R14'], 'G08' => ['R15'], 'G09' => ['R16', 'R17', 'R18'],
        ];
        foreach ($this->guides() as $code => $guide) {
            foreach (self::LOCALES as $locale) {
                $items = $guide['body'][$locale]['sources']['items'];
                $this->assertSame($expected[$code], array_column($items, 'ref'), "{$code} {$locale}");
                foreach ($items as $item) {
                    $this->assertStringStartsWith('https://', $item['url'], "{$code} {$locale}");
                    $this->assertNotSame('', $item['label']);
                }
            }
        }
        $g05 = $this->guides()['G05']['body']['en']['sources'];
        $this->assertSame('https://www.difccourts.ae/difc-courts-wills/services', $g05['items'][0]['url']);
        $this->assertSame('DIFC Courts Wills Service', $g05['items'][0]['label']);
        $this->assertSame(['p'], array_column($g05['blocks'], 'type'));
        $this->assertSame([], $this->guides()['G01']['body']['en']['sources']['blocks']);
    }

    public function test_internal_links_are_stored_as_ids_never_as_fallback_urls(): void
    {
        $services = ['G01' => 'S06', 'G02' => 'S01', 'G03' => 'S01', 'G04' => 'S05', 'G05' => 'S08', 'G06' => 'S03', 'G07' => 'S09', 'G08' => 'S10', 'G09' => 'S07'];
        $readAlso = ['G01' => 'G02', 'G02' => 'G03', 'G03' => 'G02', 'G04' => 'G02', 'G05' => 'G08', 'G06' => 'G03', 'G07' => 'G09', 'G08' => 'G05', 'G09' => 'G07'];
        foreach ($this->guides() as $code => $guide) {
            foreach (self::LOCALES as $locale) {
                $this->assertSame(
                    [$services[$code], $readAlso[$code], 'P05', 'P07', 'knowledge-top'],
                    array_column($guide['body'][$locale]['related']['items'], 'target'),
                    "{$code} {$locale}",
                );
            }
        }

        $targets = [];
        $datasets = $this->imported()['datasets'];
        array_walk_recursive($datasets, function ($value, $key) use (&$targets) {
            if ($key === 'target' && $value !== null) {
                $targets[] = $value;
            }
            if (is_string($value)) {
                $this->assertStringNotContainsString('summitlegaluae.com/', $value, 'An internal fallback URL leaked into the output');
            }
        });
        $this->assertNotEmpty($targets);
        foreach ($targets as $target) {
            $this->assertMatchesRegularExpression('/^(P0\d|S\d{2}|G0\d|WA01|knowledge-top)$/', $target);
        }
    }

    public function test_landing_page_copy_in_each_language(): void
    {
        $p = $this->imported()['datasets']['pages']['P03'];
        $this->assertSame('Legal Knowledge Centre in the UAE', $p['h1']['en']);
        $this->assertSame('مركز المعرفة القانونية في الإمارات', $p['h1']['ar']);
        $this->assertSame('Центр правовых знаний в ОАЭ', $p['h1']['ru']);
        $this->assertSame('Legal Knowledge Centre in the UAE and Practical Guides | Summit', $p['seo_title']['en']);
        $this->assertStringStartsWith('Practical UAE legal guides on salaries', $p['meta_description']['en']);
        $this->assertSame([['label' => 'Home', 'target' => 'P01'], ['label' => 'Legal Knowledge Centre', 'target' => null]], $p['breadcrumb']['en']);
        $this->assertSame('الرئيسية', $p['breadcrumb']['ar'][0]['label']);
        $this->assertSame('Hello Summit Legal Consultancy, I would like to request a legal consultation and need help with my matter. Please let me know the steps to book a consultation. Thank you.', $p['whatsapp_draft']['en']);

        foreach (self::LOCALES as $locale) {
            $content = $p['content'][$locale];
            $this->assertSame(['lead', 'intro', 'how_to', 'find', 'directory', 'faq_services', 'consultation', 'scope'], array_keys($content), $locale);
            $this->assertCount(4, $content['how_to']['items'], $locale);
            $this->assertSame(['P05', 'P02'], array_column($content['faq_services']['links'], 'target'), $locale);
            $this->assertSame('P07', $content['consultation']['cta']['target'], $locale);
            $this->assertSame('WA01', $content['consultation']['whatsapp']['target'], $locale);
            $this->assertSame('tel:+971524666191', $content['consultation']['phone']['url'], $locale);
            $this->assertSame('+971 52 466 6191', $content['consultation']['phone']['label'], $locale);
            $this->assertSame('mailto:info@summitlegaluae.com', $content['consultation']['email']['url'], $locale);
            $this->assertCount(2, $content['scope']['paragraphs'], $locale);
            $this->assertSame('P08', $content['scope']['link']['target'], $locale);
        }
        $this->assertSame('Need a Specific Answer?', $p['content']['en']['faq_services']['heading']);
        $this->assertSame('Legal Guides', $p['content']['en']['directory']['heading']);
    }

    public function test_search_and_filter_strings_in_each_language(): void
    {
        $ui = $this->imported()['datasets']['ui_strings'];
        $keys = ['resources.search.label', 'resources.search.placeholder', 'resources.topic.label', 'resources.topic.all', 'resources.clear', 'resources.no_results'];
        $this->assertSame($keys, array_keys($ui));
        foreach ($keys as $key) {
            foreach (self::LOCALES as $locale) {
                $this->assertNotSame('', $ui[$key][$locale] ?? '', "{$key} {$locale}");
            }
        }
        $this->assertSame('Search the Guides', $ui['resources.search.label']['en']);
        $this->assertSame('Enter a word such as salary, cheque or will', $ui['resources.search.placeholder']['en']);
        $this->assertSame('الموضوع', $ui['resources.topic.label']['ar']);
        $this->assertSame('Все темы', $ui['resources.topic.all']['ru']);
        $this->assertSame('Clear Search and Filters', $ui['resources.clear']['en']);
    }

    public function test_only_its_own_datasets_are_written(): void
    {
        $datasets = array_keys(array_filter($this->imported()['datasets']));
        sort($datasets);
        $this->assertSame(['guide_categories', 'guides', 'pages', 'ui_strings'], $datasets);
        $this->assertSame(['P03'], array_keys($this->imported()['datasets']['pages']));
    }
}
