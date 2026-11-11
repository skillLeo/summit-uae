<?php

namespace Tests\Feature\Content;

use App\Content\Docx\DocxReader;
use App\Content\Import\Mappers\CaseStudiesMapper;
use App\Content\Import\Mappers\HomepageMapper;

final class CaseStudiesMapperTest extends MapperTestCase
{
    private const CASES = ['case-01', 'case-02', 'case-03', 'case-04', 'case-05', 'case-06'];

    private function imported(): array
    {
        return $this->import([new CaseStudiesMapper]);
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

    public function test_six_case_studies_in_order_with_stable_anchors(): void
    {
        $cases = $this->imported()['datasets']['case_studies'];
        $this->assertSame(self::CASES, array_keys($cases));
        foreach (self::CASES as $i => $code) {
            $this->assertSame($code, $cases[$code]['anchor']);
            $this->assertSame($i + 1, $cases[$code]['position']);
        }
    }

    public function test_matter_line_is_kept_verbatim_in_each_language(): void
    {
        $matter = $this->imported()['datasets']['case_studies']['case-01']['matter'];
        $this->assertSame('Matter: cheques, a financial claim and earlier judgments', $matter['en']);
        $this->assertSame('موضوع الملف: شيكات ومطالبة مالية وأحكام سابقة', $matter['ar']);
        $this->assertSame('Предмет дела: чеки, денежное требование и ранее вынесенные судебные решения', $matter['ru']);
    }

    public function test_story_keeps_its_sections_outcome_with_limits_takeaway_and_links(): void
    {
        $cases = $this->imported()['datasets']['case_studies'];
        $body = $cases['case-01']['body']['en'];

        $this->assertSame('A signed blank cheque did not tell the whole story', $cases['case-01']['title']['en']);
        $this->assertSame($cases['case-01']['title']['en'], $cases['case-01']['card_title']['en']);
        $this->assertCount(2, $body['narrative']);
        $this->assertSame(['How the cheque became a claim', 'What changed in the presentation of the case'], array_column($body['sections'], 'heading'));
        $this->assertSame('The outcome and its limits', $body['outcome']['heading']);
        $this->assertCount(2, $body['outcome']['paragraphs']);
        $this->assertStringStartsWith('Recovery of the funds, however, remained dependent on enforcement proceedings.', $body['outcome']['paragraphs'][1]);
        $this->assertSame('What you can learn from this story', $body['takeaway']['heading']);
        $this->assertSame('Related services and reading', $body['related']['heading']);
        $this->assertSame(['S01', 'G03'], array_column($body['related']['links'], 'target'));
        $this->assertSame(['label' => 'Back to the case study directory', 'target' => 'cases-top'], $body['back_link']);

        // Advisory and claim-preparation stories have one substantive section before the outcome.
        $five = $cases['case-05']['body']['en'];
        $this->assertCount(1, $five['sections']);
        $this->assertSame('What the client received', $five['outcome']['heading']);
        $this->assertSame(['P07', 'S04'], array_column($cases['case-02']['body']['ar']['related']['links'], 'target'));
    }

    public function test_page_copy_for_p04(): void
    {
        $page = $this->imported()['datasets']['pages']['P04'];
        $this->assertSame('Legal Case Studies in the UAE', $page['h1']['en']);
        $this->assertSame('نماذج من ملفات قانونية في الإمارات', $page['h1']['ar']);
        $this->assertSame('Примеры из юридической практики в ОАЭ', $page['h1']['ru']);
        $this->assertSame('Legal Case Studies in the UAE | Summit Legal Consultancy', $page['seo_title']['en']);
        $this->assertSame([['label' => 'Home', 'target' => 'P01'], ['label' => 'Case Studies', 'target' => null]], $page['breadcrumb']['en']);

        $content = $page['content']['ru'];
        $this->assertSame(['intro', 'directory', 'faq', 'cta', 'scope'], array_keys($content));
        $this->assertCount(4, $content['faq']['items']);
        $this->assertSame('P05', $content['faq']['link']['target']);
        $this->assertSame(['P07', 'WA01', 'TEL', 'EMAIL'], [
            $content['cta']['consultation']['target'], $content['cta']['whatsapp']['target'],
            $content['cta']['phone']['target'], $content['cta']['email']['target'],
        ]);
        $this->assertSame('P08', $content['scope']['link']['target']);
    }

    public function test_link_targets_are_ids_never_fallback_urls(): void
    {
        $d = $this->imported()['datasets'];
        $targets = [];
        array_walk_recursive($d, function ($value, $key) use (&$targets) {
            if ($key === 'target' && $value !== null) {
                $targets[] = $value;
            }
        });
        $this->assertNotEmpty($targets);
        foreach ($targets as $target) {
            $this->assertMatchesRegularExpression('/^(P0\d|S\d{2}|G0\d|case-0\d|cases-top|WA01|TEL|EMAIL)$/', $target);
        }
    }

    public function test_cards_links_and_whatsapp_draft_agree_within_the_file(): void
    {
        $report = $this->imported()['report'];
        $this->assertSame([], array_merge($report->entries('label-mismatch'), $report->entries('source-conflict')));
    }

    public function test_homepage_owned_fields_are_left_to_the_homepage_mapper(): void
    {
        $d = $this->imported()['datasets'];
        $this->assertSame(['pages', 'case_studies'], array_keys(array_filter($d)));
        $this->assertSame(['P04'], array_keys($d['pages']));
        foreach (['case-01', 'case-04'] as $code) {
            $this->assertSame([], preg_grep('/^home_/', array_keys($d['case_studies'][$code])));
        }
    }

    public function test_every_published_string_is_verbatim_black_copy(): void
    {
        $d = $this->imported()['datasets'];
        foreach (['en', 'ar', 'ru'] as $locale) {
            $file = glob(base_path("package/docx/{$locale}/04_Case_Studies*.docx"))[0];
            $black = array_map(fn ($b) => $b->text, (new DocxReader)->read($file, $locale)->publicBlocks());
            $strings = [];
            foreach ([$d['pages']['P04'], ...array_values($d['case_studies'])] as $record) {
                foreach ($record as $value) {
                    if (is_array($value) && array_key_exists($locale, $value)) {
                        $leaf = [$value[$locale]];
                        array_walk_recursive($leaf, function ($v, $k) use (&$strings) {
                            if (is_string($v) && $k !== 'target') {
                                $strings[] = $v;
                            }
                        });
                    }
                }
            }
            $this->assertGreaterThan(100, count($strings));
            $this->assertSame([], array_values(array_diff($strings, $black)), "{$locale}: output text not found verbatim in the DOCX");
        }
    }

    public function test_shared_contact_targets_match_the_homepage_values(): void
    {
        $d = $this->import([new HomepageMapper, new CaseStudiesMapper])['datasets'];
        foreach (['en', 'ar', 'ru'] as $locale) {
            $cta = $d['pages']['P04']['content'][$locale]['cta'];
            $this->assertSame($d['settings']['whatsapp_draft'][$locale], $d['pages']['P04']['whatsapp_draft'][$locale], $locale);
            $this->assertSame($d['settings']['phone_display'][$locale], $cta['phone']['label'], $locale);
            $this->assertSame($d['settings']['email'][$locale], $cta['email']['label'], $locale);
        }
    }
}
