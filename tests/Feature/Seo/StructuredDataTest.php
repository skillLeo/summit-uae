<?php

namespace Tests\Feature\Seo;

use App\Support\SitePages;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Client PDF item 26: schema.org JSON-LD on every current page, matching the visible page: the organisation
 * (LegalService: brand, logo, telephone +971524666191, e-mail, country AE, languages; no address, rating, review or
 * opening hours), the website, the page, the visible breadcrumb trail, FAQPage only where the questions are shown,
 * Article for the Resources page's guides, Service on a practice-area page. The script carries the CSP nonce and cannot
 * be closed by a text.
 */
final class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://www.summitlegaluae.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return array{0: array<string, mixed>, 1: string} the JSON-LD and the page's visible text */
    private function ld(string $path): array
    {
        $html = (string) $this->get($path)->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<script type="application/ld\+json" nonce="([^"]+)">(.*?)</script>#s', $html, $m), "{$path}: one JSON-LD block with a nonce");
        $this->assertMatchesRegularExpression('#<meta property="csp-nonce" nonce="'.preg_quote($m[1][0], '#').'">#', $html, 'the page nonce');
        $data = json_decode($m[2][0], true, flags: JSON_THROW_ON_ERROR);
        $body = (string) preg_replace('#<(script|style|template)\b.*?</\1>#s', ' ', substr($html, (int) strpos($html, '<body')));
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5)));
        $text = (string) preg_replace('/[\x{2066}-\x{2069}\x{200E}\x{200F}]/u', '', $text);

        return [$data, $text];
    }

    /** @return array<string, mixed> */
    private function node(array $data, string $type): array
    {
        foreach ($data['@graph'] as $node) {
            if (in_array($type, (array) $node['@type'], true)) {
                return $node;
            }
        }
        $this->fail("no {$type} node");
    }

    private function has(array $data, string $type): bool
    {
        foreach ($data['@graph'] as $node) {
            if (in_array($type, (array) $node['@type'], true)) {
                return true;
            }
        }

        return false;
    }

    private function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    public function test_every_current_page_carries_the_organisation_website_and_page_without_forbidden_claims(): void
    {
        foreach (SitePages::all() as $page) {
            foreach ($page['locales'] as $locale) {
                $path = "/{$locale}/{$page['path']}";
                [$data] = $this->ld($path);
                $this->assertSame('https://schema.org', $data['@context']);
                $org = $this->node($data, 'LegalService');
                $this->assertSame(self::HOST.'/#organization', $org['@id']);
                $this->assertSame('Summit Legal Consultancy', $org['name']);
                $this->assertSame('سَمِت للاستشارات القانونية', $org['alternateName']);
                $this->assertSame('+971524666191', $org['telephone']);
                $this->assertSame('info@summitlegaluae.com', $org['email']);
                $this->assertSame('AE', $org['areaServed']['identifier']);
                $this->assertSame(['en', 'ar', 'ru'], $org['knowsLanguage']);
                $this->assertSame(self::HOST.'/icons/icon-512.png', $org['logo']['url']);
                $this->assertSame(self::HOST.'/'.$locale.'/', $org['url']);
                $this->assertSame(self::HOST.'/#website', $this->node($data, 'WebSite')['@id']);
                $webpage = collect($data['@graph'])->firstWhere('@id', self::HOST.$path.'#webpage');
                $this->assertNotNull($webpage, "{$path}: the #webpage node");
                $this->assertSame($locale, $webpage['inLanguage']);
                $this->assertSame(self::HOST.$path, $webpage['url']);

                $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                foreach (['"address"', 'PostalAddress', 'aggregateRating', '"review', 'openingHours', 'priceRange', 'foundingDate', '"award', '"geo"', 'Summit Legal Consultants', 'law firm'] as $forbidden) {
                    $this->assertStringNotContainsString($forbidden, $json, "{$path}: {$forbidden}");
                }
            }
        }
    }

    public function test_the_breadcrumb_list_is_the_visible_trail(): void
    {
        [$data, $text] = $this->ld('/ar/practice-areas/labor-law/');
        $list = $this->node($data, 'BreadcrumbList');
        $items = $list['itemListElement'];
        $this->assertCount(3, $items);
        $this->assertSame([1, 2, 3], array_column($items, 'position'));
        $this->assertSame([self::HOST.'/ar/', self::HOST.'/ar/practice-areas/', self::HOST.'/ar/practice-areas/labor-law/'], array_column($items, 'item'));
        foreach ($items as $item) {
            $this->assertStringContainsString($this->plain($item['name']), $text);
        }
        // The home page shows no breadcrumb: none in its data.
        [$home] = $this->ld('/en/');
        $this->assertFalse($this->has($home, 'BreadcrumbList'));
    }

    public function test_faq_page_only_where_the_questions_are_shown(): void
    {
        // The FAQ centre: every approved question, visible on the page.
        [$data, $text] = $this->ld('/en/faq/');
        $faq = $this->node($data, 'FAQPage');
        $this->assertCount(count(content()->faqs()), $faq['mainEntity']);
        foreach ($faq['mainEntity'] as $q) {
            $this->assertSame('Question', $q['@type']);
            $this->assertStringContainsString($this->plain($q['name']), $text);
            $this->assertStringContainsString($this->plain(strtok($q['acceptedAnswer']['text'], "\n")), $text);
        }

        // A practice area: its own questions (the FAQ centre's repeated ones are left to the FAQ page).
        [$data, $text] = $this->ld('/ru/practice-areas/labor-law/');
        $own = content()->service('S06', 'ru')['sections']['faq']['items'];
        $questions = array_column($this->node($data, 'FAQPage')['mainEntity'], 'name');
        $this->assertSame(array_map(fn ($i) => $i['question'], $own), $questions);
        foreach ($questions as $question) {
            $this->assertStringContainsString($this->plain($question), $text);
        }

        // The legal pages show no questions: no FAQPage.
        [$data] = $this->ld('/en/privacy-policy/');
        $this->assertFalse($this->has($data, 'FAQPage'));

        // The home page's questions follow the block: hidden block, no FAQPage.
        [$data] = $this->ld('/en/');
        $this->assertTrue($this->has($data, 'FAQPage'));
        $blocks = json_decode((string) DB::table('pages')->where('code', 'P01')->value('blocks'), true);
        foreach ($blocks as &$block) {
            if ($block['type'] === 'faq') {
                $block['enabled'] = false;
            }
        }
        unset($block);
        DB::table('pages')->where('code', 'P01')->update(['blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE)]);
        content()->flush();
        [$data, $text] = $this->ld('/en/');
        $this->assertFalse($this->has($data, 'FAQPage'));
    }

    public function test_the_resources_guides_are_articles_and_a_service_page_has_its_service(): void
    {
        [$data, $text] = $this->ld('/en/resources/');
        $articles = array_values(array_filter($data['@graph'], fn ($n) => in_array('Article', (array) $n['@type'], true)));
        $this->assertCount(count(content()->guides()), $articles);
        foreach ($articles as $article) {
            $this->assertStringStartsWith(self::HOST.'/en/resources/#', $article['url']);
            $this->assertStringContainsString($this->plain($article['headline']), $text);
            $this->assertSame(self::HOST.'/#organization', $article['author']['@id']);
            $this->assertArrayNotHasKey('datePublished', $article, 'no date is shown');
        }

        [$data, $text] = $this->ld('/en/practice-areas/labor-law/');
        $service = $this->node($data, 'Service');
        $this->assertSame(content()->service('S06', 'en')['title'], $service['name']);
        $this->assertSame(self::HOST.'/#organization', $service['provider']['@id']);
        $this->assertSame(self::HOST.'/en/practice-areas/labor-law/#service', $service['@id']);
    }

    public function test_the_practice_area_list_comes_from_the_published_content(): void
    {
        [$data] = $this->ld('/en/');
        $names = array_map(fn ($o) => $o['itemOffered']['name'], $this->node($data, 'LegalService')['hasOfferCatalog']['itemListElement']);
        $this->assertSame(array_column(content()->services('en'), 'title'), $names);
        // Legal Translation is unpublished (client PDF item 9, owner decision 6): gone from the data, nothing hard-coded.
        $this->assertNotContains('Legal Translation', $names);
        [$data] = $this->ld('/en/practice-areas/');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('legal-translation', $json);
        $this->assertCount(10, $this->node($data, 'LegalService')['hasOfferCatalog']['itemListElement']);
    }

    public function test_noindex_pages_carry_none(): void
    {
        foreach (['/en/styleguide/' => 200, '/en/no-such-page/' => 404] as $uri => $status) {
            $this->assertStringNotContainsString('application/ld+json', (string) $this->get($uri)->assertStatus($status)->getContent(), $uri);
        }
    }
}
