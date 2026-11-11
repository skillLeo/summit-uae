<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\FaqCentreMapper;
use App\Content\Import\Mappers\HomepageMapper;

final class FaqCentreMapperTest extends MapperTestCase
{
    private const LOCALES = ['en', 'ar', 'ru'];

    /** Question ranges stated in the red "Category NN | … Contains FAQ-AA through FAQ-BB" notes. */
    private const RANGES = [
        'cat-01' => [1, 6], 'cat-02' => [7, 13], 'cat-03' => [14, 20], 'cat-04' => [21, 28],
        'cat-05' => [29, 34], 'cat-06' => [35, 40], 'cat-07' => [41, 48], 'cat-08' => [49, 55],
        'cat-09' => [56, 61], 'cat-10' => [62, 66], 'cat-11' => [67, 70], 'cat-12' => [71, 74],
    ];

    private function imported(): array
    {
        return $this->import([new FaqCentreMapper]);
    }

    public function test_every_public_block_is_mapped_in_all_languages(): void
    {
        $this->assertFullCoverage($this->imported());
    }

    public function test_no_designer_note_reaches_public_output(): void
    {
        $result = $this->imported();
        $this->assertNoDesignerNotes($result);
        array_walk_recursive($result['datasets'], function ($value) {
            foreach (['Set id=', 'Stable link', 'P05-L0', 'Related links for this category', 'focus the section heading'] as $fragment) {
                $this->assertStringNotContainsString($fragment, (string) $value);
            }
        });
    }

    public function test_languages_have_identical_structure(): void
    {
        $this->assertNoParityIssues($this->imported());
    }

    public function test_the_handoff_is_internally_consistent(): void
    {
        $entries = array_filter($this->imported()['report']->entries(), fn ($e) => in_array($e['type'], ['source-conflict', 'parity', 'label-mismatch'], true));
        $this->assertSame([], array_values(array_map(fn ($e) => "{$e['type']} {$e['locale']} {$e['subject']}: {$e['detail']}", $entries)));
    }

    public function test_reports_official_references_that_open_pages_in_another_language(): void
    {
        $notes = [];
        foreach ($this->imported()['report']->entries('note') as $e) {
            if ($e['subject'] === 'P05 official references') {
                $notes[$e['locale']][] = $e['detail'];
            }
        }
        $arabic = 'AR-language pages (/ar/) in this language file and are kept as supplied: P05-L021, P05-L035, P05-L043, P05-L048, P05-L052, P05-L053';
        $english = 'EN-language pages (/en/) in this language file and are kept as supplied: P05-L029, P05-L039';
        $this->assertCount(1, $notes['en'] ?? []);
        $this->assertStringEndsWith($arabic, $notes['en'][0]);
        $this->assertCount(1, $notes['ar'] ?? []);
        $this->assertStringEndsWith($english, $notes['ar'][0]);
        $this->assertCount(2, $notes['ru'] ?? []);
        $this->assertStringEndsWith($arabic, $notes['ru'][0]);
        $this->assertStringEndsWith($english, $notes['ru'][1]);
    }

    public function test_twelve_categories_and_seventy_four_faqs_with_stable_anchors(): void
    {
        $d = $this->imported()['datasets'];
        $this->assertSame(array_keys(self::RANGES), array_keys($d['faq_categories']));
        $this->assertSame(array_map(fn ($n) => sprintf('faq-%02d', $n), range(1, 74)), array_keys($d['faqs']));
        foreach ($d['faq_categories'] as $code => $category) {
            $this->assertSame($code, $category['anchor']);
            $this->assertSame((int) substr($code, 4), $category['position']);
            foreach (self::LOCALES as $locale) {
                $this->assertNotSame('', $category['name'][$locale] ?? '', "{$locale} {$code}");
            }
        }
        foreach ($d['faqs'] as $code => $faq) {
            $this->assertSame($code, $faq['anchor']);
            $this->assertSame((int) substr($code, 4), $faq['position']);
            $this->assertArrayHasKey($faq['category'], $d['faq_categories'], "{$code} points to an unknown category");
        }
    }

    public function test_questions_are_grouped_as_the_category_notes_state(): void
    {
        $grouped = [];
        foreach ($this->imported()['datasets']['faqs'] as $faq) {
            $grouped[$faq['category']][] = $faq['position'];
        }
        foreach (self::RANGES as $category => [$from, $to]) {
            $this->assertSame(range($from, $to), $grouped[$category] ?? [], $category);
        }
    }

    public function test_every_answer_keeps_both_approved_paragraphs(): void
    {
        foreach ($this->imported()['datasets']['faqs'] as $code => $faq) {
            foreach (self::LOCALES as $locale) {
                $this->assertStringStartsWith(((int) substr($code, 4)).'. ', $faq['question'][$locale], "{$locale} {$code}");
                $this->assertCount(2, $faq['answer'][$locale], "{$locale} {$code}");
                foreach ($faq['answer'][$locale] as $block) {
                    $this->assertSame('p', $block['type']);
                    $this->assertNotSame('', $block['text']);
                }
            }
        }
    }

    public function test_copy_is_routed_verbatim(): void
    {
        $d = $this->imported()['datasets'];
        $faq = $d['faqs']['faq-01'];
        $this->assertSame('1. When do I need legal advice?', $faq['question']['en']);
        $this->assertSame('1. متى أحتاج إلى استشارة قانونية؟', $faq['question']['ar']);
        $this->assertSame('1. Когда мне нужна юридическая консультация?', $faq['question']['ru']);
        $this->assertSame('The clearer the facts and deadlines, the sooner the scope of the review can be identified. If you have received a court notice or official decision, state its date at the outset. Requesting a consultation does not, by itself, suspend any deadline.', $faq['answer']['en'][1]['text']);
        $this->assertSame('Debts and Cheques', $d['faq_categories']['cat-02']['name']['en']);
        $this->assertSame('العملات الرقمية', $d['faq_categories']['cat-04']['name']['ar']);
        $this->assertSame('Трудовые отношения и причитающиеся выплаты', $d['faq_categories']['cat-07']['name']['ru']);
    }

    public function test_links_store_ids_and_real_external_urls_never_fallback_routes(): void
    {
        $d = $this->imported()['datasets'];
        $targets = [];
        array_walk_recursive($d, function ($value, $key) use (&$targets) {
            if ($key === 'target') {
                $targets[] = $value;
            }
        });
        $this->assertNotEmpty($targets);
        foreach ($targets as $target) {
            $this->assertDoesNotMatchRegularExpression('~^https?://(www\.)?summitlegaluae\.com~', $target, 'site fallback URL stored as a target');
            $this->assertMatchesRegularExpression('~^(P0[1-8]|S(0[1-9]|1[01])|G0[1-9]|A0[1-4]|cat-(0[1-9]|1[0-2])|https://\S+|tel:\+\d+|mailto:\S+@\S+)$~', $target);
        }

        $links = $d['faq_categories'];
        foreach (self::LOCALES as $locale) {
            $this->assertSame(['P07', 'P06'], array_column($links['cat-01']['links'][$locale], 'target'));
            $this->assertSame(['S01', 'G02', 'G03', 'https://uaelegislation.gov.ae/ar/legislations/1610'], array_column($links['cat-02']['links'][$locale], 'target'));
            $this->assertSame(['S03', 'G06', 'A04', 'https://www.vara.ae/en/about-vara/'], array_column($links['cat-04']['links'][$locale], 'target'));
            $this->assertSame(['S11'], array_column($links['cat-12']['links'][$locale], 'target'));
        }

        $page = $d['pages']['P05'];
        foreach (self::LOCALES as $locale) {
            $this->assertSame('P01', $page['breadcrumb'][$locale][0]['target']);
            $this->assertArrayNotHasKey('target', $page['breadcrumb'][$locale][1]);
            $this->assertSame('P07', $page['content'][$locale]['hero']['cta']['target']);
            $this->assertSame(array_keys(self::RANGES), array_column($page['content'][$locale]['category_menu']['items'], 'target'));
            $this->assertSame(['P08', 'P03', 'P02'], array_column($page['content'][$locale]['scope']['links'], 'target'));
            $this->assertSame('tel:+971524666191', $page['content'][$locale]['consultation']['phone']['target']);
            $this->assertSame('mailto:info@summitlegaluae.com', $page['content'][$locale]['consultation']['email']['target']);
        }
    }

    public function test_faq_interface_strings_come_from_the_file(): void
    {
        $ui = $this->imported()['datasets']['ui_strings'];
        $this->assertSame([
            'faq.search_label', 'faq.search_placeholder', 'faq.search_submit', 'faq.search_reset', 'faq.no_results',
            'faq.expand_all', 'faq.collapse_all', 'faq.copy_link', 'faq.link_copied', 'faq.copy_failed', 'faq.back_to_categories',
        ], array_keys($ui));
        foreach ($ui as $key => $values) {
            foreach (self::LOCALES as $locale) {
                $this->assertNotSame('', $values[$locale] ?? '', "{$locale} {$key}");
            }
        }
        $this->assertSame('Enter a word such as: cheque, salary, will, tax', $ui['faq.search_placeholder']['en']);
        $this->assertSame('تم نسخ رابط السؤال.', $ui['faq.link_copied']['ar']);
        $this->assertSame('Показать все ответы', $ui['faq.expand_all']['ru']);
        $this->assertSame('Back to FAQ categories', $ui['faq.back_to_categories']['en']);
    }

    public function test_page_metadata_and_whatsapp_draft(): void
    {
        $page = $this->imported()['datasets']['pages']['P05'];
        $this->assertSame('Frequently Asked Legal Questions in the UAE', $page['h1']['en']);
        $this->assertSame('الأسئلة القانونية الشائعة في الإمارات', $page['h1']['ar']);
        $this->assertSame('Часто задаваемые юридические вопросы в ОАЭ', $page['h1']['ru']);
        $this->assertSame('Frequently Asked Legal Questions in the UAE | Summit Legal Consultancy', $page['seo_title']['en']);
        $this->assertSame('Frequently Asked Questions', $page['breadcrumb']['en'][1]['label']);
        foreach (self::LOCALES as $locale) {
            $this->assertNotSame('', $page['meta_description'][$locale]);
            $href = $page['content'][$locale]['consultation']['whatsapp']['target'];
            $this->assertStringStartsWith('https://wa.me/971524666191?text=', $href);
            parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
            $this->assertSame($page['whatsapp_draft'][$locale], $query['text'], $locale);
        }
    }

    public function test_merges_with_the_homepage_and_matches_its_contact_settings(): void
    {
        $d = $this->import([new HomepageMapper, new FaqCentreMapper])['datasets'];
        $settings = $d['settings'];
        $page = $d['pages']['P05'];
        foreach (self::LOCALES as $locale) {
            $contact = $page['content'][$locale]['consultation'];
            $this->assertSame($settings['whatsapp_url'][$locale], $contact['whatsapp']['target'], $locale);
            $this->assertSame($settings['whatsapp_draft'][$locale], $page['whatsapp_draft'][$locale], $locale);
            $this->assertSame($settings['phone_display'][$locale], $contact['phone']['label'], $locale);
            $this->assertSame($settings['phone_href'][$locale], $contact['phone']['target'], $locale);
            $this->assertSame($settings['email'][$locale], $contact['email']['label'], $locale);
            $this->assertSame($settings['email_href'][$locale], $contact['email']['target'], $locale);
        }
    }
}
