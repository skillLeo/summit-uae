<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\HomepageMapper;
use App\Content\Import\Mappers\SiteMapMapper;

final class SiteMapMapperTest extends MapperTestCase
{
    /** The Arabic folder's site map: an English, red-only governing map with no black labels. */
    private const AR_FILE = '00_Site_Map_and_Content_Rules_EN.docx';

    private const PAGES = ['P01', 'P02', 'P03', 'P04', 'P05', 'P06', 'P07', 'P08', 'L01', 'L02', 'U01'];

    private const SERVICES = ['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];

    private const GUIDES = ['G01', 'G02', 'G03', 'G04', 'G05', 'G06', 'G07', 'G08', 'G09'];

    /** Homepage link-registry entries that repeat a shared homepage interface string. */
    private const SHARED_LABELS = [
        'H-L001' => 'nav.home', 'H-L002' => 'nav.practice_areas', 'H-L003' => 'nav.resources',
        'H-L004' => 'nav.case_studies', 'H-L005' => 'nav.faq', 'H-L006' => 'nav.about', 'H-L007' => 'nav.contact',
        'H-L008' => 'lang.ar', 'H-L009' => 'lang.en', 'H-L010' => 'lang.ru',
        'H-L011' => 'cta.request_consultation', 'H-L012' => 'cta.whatsapp', 'H-L024' => 'cta.view_all_practice_areas',
        'H-L025' => 'cta.meet_team', 'H-L028' => 'cta.view_all_case_studies', 'H-L038' => 'cta.visit_resources',
        'H-L039' => 'cta.view_all_faqs', 'H-L041' => 'cta.whatsapp',
        'H-L043' => 'footer.resources_centre', 'H-L044' => 'footer.faq', 'H-L045' => 'footer.case_studies',
        'H-L046' => 'footer.about', 'H-L047' => 'footer.contact', 'H-L048' => 'footer.legal_notice',
        'H-L049' => 'footer.privacy', 'H-L050' => 'footer.terms',
    ];

    private function imported(): array
    {
        return $this->import([new SiteMapMapper]);
    }

    public function test_every_public_block_is_mapped_in_all_languages(): void
    {
        $this->assertFullCoverage($this->imported());
    }

    public function test_no_designer_note_reaches_public_output(): void
    {
        $this->assertNoDesignerNotes($this->imported());
    }

    public function test_english_and_russian_have_identical_structure(): void
    {
        $report = $this->imported()['report'];
        $outsideArabic = array_filter($report->entries('parity'), fn ($e) => $e['locale'] !== 'ar');
        $this->assertSame([], array_values($outsideArabic));
        $this->assertSame([], $report->entries('label-mismatch'));
        $this->assertSame([], $report->entries('source-conflict'));
    }

    /**
     * Genuine source difference: the Arabic folder ships an English, red-only site map, so the
     * Arabic registry fields are absent. The mapper reports it precisely; the import runner then
     * reports each record that exists only in EN and RU.
     */
    public function test_the_red_only_arabic_site_map_is_reported_exactly(): void
    {
        $label = fn (array $codes) => self::AR_FILE.' has no black public label for '.implode(', ', $codes).'; the field is absent in this language';
        $expected = [
            ['ar', self::AR_FILE, 'contains no black public copy: all 177 blocks are red designer notes'],
            ['ar', 'pages.*.registry_label', $label(self::PAGES)],
            ['ar', 'services.*.registry_label', $label(self::SERVICES)],
            ['ar', 'guides.*.title', $label(self::GUIDES)],
            ['ar', 'settings.link_registry', self::AR_FILE.' has no homepage link registry (H-L001 to H-L056); the field is absent in this language'],
        ];
        foreach (['pages' => self::PAGES, 'services' => self::SERVICES, 'guides' => self::GUIDES, 'settings' => ['link_registry']] as $dataset => $codes) {
            foreach ($codes as $code) {
                $expected[] = ['ar', "{$dataset}.{$code}", 'missing in this language'];
            }
        }

        $actual = array_map(fn ($e) => [$e['locale'], $e['subject'], $e['detail']], $this->imported()['report']->entries('parity'));
        $this->assertSame($expected, $actual);
    }

    public function test_page_service_and_guide_registry_labels(): void
    {
        $d = $this->imported()['datasets'];
        $this->assertSame(self::PAGES, array_keys($d['pages']));
        $this->assertSame(self::SERVICES, array_keys($d['services']));
        $this->assertSame(self::GUIDES, array_keys($d['guides']));

        $pages = array_map(fn ($p) => $p['registry_label'], $d['pages']);
        $this->assertSame(['en' => 'Home', 'ar' => null, 'ru' => 'Главная'], $pages['P01']);
        $this->assertSame('Legal Notice', $pages['P08']['en']);
        $this->assertSame('Правовая оговорка', $pages['P08']['ru']);
        $this->assertSame('Privacy Policy', $pages['L01']['en']);
        $this->assertSame('Terms and Conditions', $pages['L02']['en']);
        $this->assertSame('Page Not Found', $pages['U01']['en']);
        $this->assertSame('Cryptocurrency and Digital Currency Cases', $d['services']['S03']['registry_label']['en']);
        $this->assertSame('What Should You Do If Your Employer Does Not Pay Your Salary in the UAE?', $d['guides']['G01']['title']['en']);
        $this->assertSame('Как систематизировать налоговые документы компании?', $d['guides']['G09']['title']['ru']);
        foreach ([...array_values($pages), ...array_column($d['services'], 'registry_label'), ...array_column($d['guides'], 'title')] as $values) {
            $this->assertNull($values['ar']);
            $this->assertNotSame('', (string) $values['en']);
            $this->assertNotSame('', (string) $values['ru']);
        }
    }

    public function test_homepage_link_registry_has_56_entries_and_no_other_settings(): void
    {
        $d = $this->imported()['datasets'];
        $this->assertSame(['link_registry'], array_keys($d['settings']));
        $this->assertSame([], $d['ui_strings']);

        $registry = $d['settings']['link_registry'];
        $ids = array_map(fn (int $i) => sprintf('H-L%03d', $i), range(1, 56));
        $this->assertSame($ids, array_keys($registry['en']));
        $this->assertSame($ids, array_keys($registry['ru']));
        $this->assertNull($registry['ar']);
        $this->assertSame('Contact Us on WhatsApp', $registry['en']['H-L012']);
        $this->assertSame('+971 52 466 6191', $registry['en']['H-L040']);
        $this->assertSame('info@summitlegaluae.com', $registry['en']['H-L042']);
        $this->assertSame('Employment Law laborlawuae.com', $registry['en']['H-L051']);
        $this->assertSame('Налоговые споры uaetaxdisputes.com', $registry['ru']['H-L056']);
    }

    /** "Any repeated shared label must have one consistent source": the registry repeats the homepage. */
    public function test_registry_labels_repeat_the_homepage_labels_exactly(): void
    {
        $d = $this->import([new HomepageMapper, new SiteMapMapper])['datasets'];
        $ui = $d['ui_strings'];
        $settings = $d['settings'];
        foreach (['en', 'ru'] as $locale) {
            $registry = $settings['link_registry'][$locale];
            foreach (self::SHARED_LABELS as $id => $key) {
                $this->assertSame($ui[$key][$locale], $registry[$id], "{$locale} {$id} {$key}");
            }
            foreach (range(13, 23) as $i) {
                $this->assertSame($ui['cta.explore_service'][$locale], $registry[sprintf('H-L%03d', $i)], $locale);
            }
            foreach (range(29, 37) as $i) {
                $this->assertSame($ui['cta.read_guide'][$locale], $registry[sprintf('H-L%03d', $i)], $locale);
            }
            $this->assertSame($d['case_studies']['case-01']['home_link_label'][$locale], $registry['H-L026']);
            $this->assertSame($d['case_studies']['case-04']['home_link_label'][$locale], $registry['H-L027']);
            $this->assertSame($settings['phone_display'][$locale], $registry['H-L040']);
            $this->assertSame($settings['email'][$locale], $registry['H-L042']);
            foreach (['X01', 'X02', 'X03', 'X04', 'X05', 'X06'] as $n => $code) {
                $site = $d['specialist_sites'][$code];
                $this->assertSame($site['label'][$locale].' '.$site['domain'], $registry[sprintf('H-L%03d', 51 + $n)], "{$locale} {$code}");
            }
            foreach (self::SERVICES as $code) {
                $this->assertSame($d['services'][$code]['title'][$locale], $d['services'][$code]['registry_label'][$locale], "{$locale} {$code}");
            }
            foreach (self::GUIDES as $code) {
                $this->assertSame($d['guides'][$code]['home_title'][$locale], $d['guides'][$code]['title'][$locale], "{$locale} {$code}");
            }
        }
    }
}
