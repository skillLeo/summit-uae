<?php

namespace Tests\Feature;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The launch on summitlegaluae.com: every address of the old static site keeps working, so links and Google rankings
 * are kept (docs/launch/production-launch-checklist.md). The 60 pages of the old site's sitemap.xml (read on
 * 2026-11-26) are the new site's own addresses: each opens the same page (200), except the cancelled Legal Translation
 * service, a temporary 302 to Practice Areas (owner decision, PDF item 9). The old site's …/index.html copies and its
 * /favicon-192.png answer with a 301 to the folder address and the current icon.
 */
final class LegacyUrlsTest extends TestCase
{
    use RefreshDatabase;

    /** The old site's sitemap.xml, 2026-11-26 (20 pages × 3 languages). */
    public const OLD_PAGES = [
            '/ar/',
            '/ar/about/',
            '/ar/case-studies/',
            '/ar/faq/',
            '/ar/legal-disclaimer/',
            '/ar/practice-areas/',
            '/ar/practice-areas/business-services/',
            '/ar/practice-areas/compliance-regulatory/',
            '/ar/practice-areas/corporate-commercial-law/',
            '/ar/practice-areas/criminal-cases/',
            '/ar/practice-areas/crypto-digital-currency-cases/',
            '/ar/practice-areas/debt-recovery-cheque-cases/',
            '/ar/practice-areas/family-law-wills/',
            '/ar/practice-areas/labor-law/',
            '/ar/practice-areas/legal-translation/',
            '/ar/practice-areas/real-estate-rental-disputes/',
            '/ar/practice-areas/tax-disputes-compliance/',
            '/ar/privacy-policy/',
            '/ar/resources/',
            '/ar/terms-and-conditions/',
            '/en/',
            '/en/about/',
            '/en/case-studies/',
            '/en/faq/',
            '/en/legal-disclaimer/',
            '/en/practice-areas/',
            '/en/practice-areas/business-services/',
            '/en/practice-areas/compliance-regulatory/',
            '/en/practice-areas/corporate-commercial-law/',
            '/en/practice-areas/criminal-cases/',
            '/en/practice-areas/crypto-digital-currency-cases/',
            '/en/practice-areas/debt-recovery-cheque-cases/',
            '/en/practice-areas/family-law-wills/',
            '/en/practice-areas/labor-law/',
            '/en/practice-areas/legal-translation/',
            '/en/practice-areas/real-estate-rental-disputes/',
            '/en/practice-areas/tax-disputes-compliance/',
            '/en/privacy-policy/',
            '/en/resources/',
            '/en/terms-and-conditions/',
            '/ru/',
            '/ru/about/',
            '/ru/case-studies/',
            '/ru/faq/',
            '/ru/legal-disclaimer/',
            '/ru/practice-areas/',
            '/ru/practice-areas/business-services/',
            '/ru/practice-areas/compliance-regulatory/',
            '/ru/practice-areas/corporate-commercial-law/',
            '/ru/practice-areas/criminal-cases/',
            '/ru/practice-areas/crypto-digital-currency-cases/',
            '/ru/practice-areas/debt-recovery-cheque-cases/',
            '/ru/practice-areas/family-law-wills/',
            '/ru/practice-areas/labor-law/',
            '/ru/practice-areas/legal-translation/',
            '/ru/practice-areas/real-estate-rental-disputes/',
            '/ru/practice-areas/tax-disputes-compliance/',
            '/ru/privacy-policy/',
            '/ru/resources/',
            '/ru/terms-and-conditions/',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return array<string, array{string}> */
    public static function oldPages(): array
    {
        return array_combine(self::OLD_PAGES, array_map(fn (string $p) => [$p], self::OLD_PAGES));
    }

    public function test_the_old_sitemap_lists_sixty_pages(): void
    {
        $this->assertCount(60, self::OLD_PAGES);
        $this->assertCount(60, array_unique(self::OLD_PAGES));
    }

    #[DataProvider('oldPages')]
    public function test_every_old_page_address_still_opens_its_page(string $path): void
    {
        $response = $this->get($path);
        if (str_contains($path, '/practice-areas/legal-translation/')) {
            $response->assertStatus(302);
            $this->assertSame('/'.substr($path, 1, 2).'/practice-areas/', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));

            return;
        }
        $response->assertOk();
        // Its canonical address is the same path on the production domain (PDF item 21).
        $this->assertStringContainsString('<link rel="canonical" href="https://www.summitlegaluae.com'.$path.'"', $response->getContent(), $path);
    }

    #[DataProvider('oldPages')]
    public function test_the_old_index_html_copy_of_a_page_redirects_to_its_folder(string $path): void
    {
        $this->get($path.'index.html')->assertStatus(301)->assertHeader('Location', $path);
    }

    public function test_the_old_root_index_and_icon_redirect(): void
    {
        $this->get('/index.html')->assertStatus(301)->assertHeader('Location', '/en/');
        $this->get('/favicon-192.png')->assertStatus(301)->assertHeader('Location', '/icons/icon-192.png');
        $this->assertFileExists(public_path('icons/icon-192.png'));
        // Never built from the Host header.
        $this->get('http://evil.example/en/about/index.html')->assertHeader('Location', '/en/about/');
    }
}
