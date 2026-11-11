<?php

namespace Tests\Feature\Content;

use App\Content\Import\Mappers\NotFoundMapper;

final class NotFoundMapperTest extends MapperTestCase
{
    private function imported(): array
    {
        return $this->import([new NotFoundMapper]);
    }

    /** @return array<string, mixed> */
    private function notFoundPage(): array
    {
        return $this->imported()['datasets']['pages']['U01'];
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

    public function test_heading_is_the_approved_h1_in_each_language(): void
    {
        $h1 = $this->notFoundPage()['h1'];
        $this->assertSame('Page Not Found', $h1['en']);
        $this->assertSame('تعذّر العثور على الصفحة', $h1['ar']);
        $this->assertSame('Страница не найдена', $h1['ru']);
    }

    public function test_error_code_actions_lead_in_and_browser_title(): void
    {
        $page = $this->notFoundPage();
        foreach (['en', 'ar', 'ru'] as $locale) {
            $this->assertSame('404', $page['code_label'][$locale], $locale);
        }
        $this->assertSame(['home_label' => 'Back to Home', 'services_label' => 'Explore Our Legal Services'], $page['actions']['en']);
        $this->assertSame('Looking for specific information?', $page['more_heading']['en']);
        $this->assertStringStartsWith('The page you are looking for is unavailable.', $page['text']['en']);
        $this->assertSame('Page Not Found | Summit Legal Consultancy', $page['seo_title']['en']);
        $this->assertSame('الصفحة غير موجودة | سَمِت للاستشارات القانونية', $page['seo_title']['ar']);
    }

    public function test_recovery_links_target_page_ids_not_fallback_urls(): void
    {
        $links = $this->notFoundPage()['links'];
        foreach (['en', 'ar', 'ru'] as $locale) {
            $this->assertSame(['P03', 'P05', 'P07'], array_column($links[$locale], 'target'), $locale);
        }
        $this->assertSame(['Legal Knowledge Centre', 'Frequently Asked Questions', 'Contact Us'], array_column($links['en'], 'label'));
    }
}
