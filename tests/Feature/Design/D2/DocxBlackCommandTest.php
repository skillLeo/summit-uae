<?php

namespace Tests\Feature\Design\D2;

use App\Console\Commands\DocxBlack;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `php artisan summit:docx-black --json` (brief 2.1 §5.3): the approved black DOCX text per page and locale, in order,
 * that scripts/qa/docx-conformance.mjs compares with the rendered Design 2 pages. It reuses the importer (DocxReader +
 * the summit:import mappers), so its lines are exactly the imported copy, and every metadata line is named by an
 * explicit rule, never skipped by similarity.
 */
final class DocxBlackCommandTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $json = null;

    /** @return array<string, mixed> */
    private function blackJson(): array
    {
        if (self::$json === null) {
            $this->assertSame(0, Artisan::call('summit:docx-black', ['--json' => true]));
            self::$json = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        }

        return self::$json;
    }

    /** @return array<string, mixed> */
    private function page(string $slug, string $locale): array
    {
        foreach ($this->blackJson()['pages'] as $page) {
            if ($page['slug'] === $slug && $page['locale'] === $locale) {
                return $page;
            }
        }
        $this->fail("no page {$slug} ({$locale})");
    }

    public function test_every_docx_page_and_the_calculators_page_are_listed_in_every_locale(): void
    {
        $slugs = ['home', 'p02-practice-areas', 'p03-resources', 'p04-case-studies', 'p05-faq', 'p06-about', 'p07-contact', 'p08-legal-disclaimer',
            'l01-privacy-policy', 'l02-terms-and-conditions', '404', 'calculators'];
        foreach (config('summit.services') as $code => $slug) {
            $slugs[] = strtolower($code).'-'.$slug;
        }
        foreach (['en', 'ar', 'ru'] as $locale) {
            $listed = array_column(array_filter($this->blackJson()['pages'], fn ($p) => $p['locale'] === $locale), 'slug');
            sort($listed);
            $expected = $slugs;
            sort($expected);
            $this->assertSame($expected, $listed, "pages in {$locale}");
        }
        $this->assertSame('/ar/practice-areas/labor-law/', $this->page('s06-labor-law', 'ar')['url']);
        $this->assertSame(404, $this->page('404', 'ru')['status']);
        $this->assertNull($this->page('calculators', 'en')['file']);
    }

    public function test_the_home_lines_follow_the_docx_in_order_with_the_header_and_footer_marked(): void
    {
        $lines = $this->page('home', 'en')['lines'];
        $texts = array_column($lines, 'text');
        $h1 = array_search('Clear Legal Guidance. Practical Solutions.', $texts, true);
        $services = array_search('Our Legal Services in the UAE', $texts, true);
        $contact = array_search('Start with Clear Legal Advice', $texts, true);
        $this->assertIsInt($h1);
        $this->assertTrue($h1 < $services && $services < $contact, 'DOCX order');
        $this->assertSame('header', $lines[array_search('Practice Areas', $texts, true)]['part']);
        $this->assertSame('footer', $lines[array_search('Useful Links', $texts, true)]['part']);
        // Red designer notes are never lines.
        $this->assertEmpty(array_filter($texts, fn ($t) => str_contains($t, 'See the separate sitemap')));
    }

    public function test_metadata_lines_have_explicit_kinds(): void
    {
        $byField = [];
        foreach ($this->page('home', 'en')['lines'] as $line) {
            $byField[$line['field']] = $line;
        }
        $this->assertSame('seo-title', $byField['P01.seo_title']['kind']);
        $this->assertSame('meta-description', $byField['P01.meta_description']['kind']);
        $this->assertSame('wa-draft', $byField['settings.whatsapp_draft']['kind']);
        $this->assertSame('label', $byField['ui.a11y.open_menu']['kind']);
        $this->assertSame('label', $byField['ui.brand.logo_alt']['kind']);
        $this->assertSame('state', $byField['P01.form_home_variant.states.failure']['kind']);
        $this->assertSame('conditional', $byField['P01.form_home_variant.attachment_hint']['kind']);
        $this->assertSame(['group', 'home-form-labels'], [$byField['P01.form_home_variant.labels.name']['kind'], $byField['P01.form_home_variant.labels.name']['group']]);
        $this->assertSame('visible', $byField['P01.h1']['kind']);
        $this->assertSame('visible', $byField['P01.contact.languages']['kind']);

        $contact = [];
        foreach ($this->page('p07-contact', 'ru')['lines'] as $line) {
            $contact[$line['field']] = $line['kind'];
        }
        $this->assertSame('state', $contact['ui.form.name.error']);
        $this->assertSame('state', $contact['ui.form.state.busy']);
        $this->assertSame('control', $contact['ui.form.contact_group.help']);
        $this->assertSame('visible', $contact['ui.form.name.label']);
    }

    public function test_inner_pages_expect_the_shared_header_and_footer_of_the_homepage_docx(): void
    {
        $lines = $this->page('s01-debt-recovery-cheque-cases', 'ar')['lines'];
        $this->assertSame('shared-header', $lines[0]['part']);
        $this->assertStringStartsWith('01_Homepage', $lines[0]['file']);
        $this->assertSame('shared-footer', end($lines)['part']);
        $this->assertContains('page', array_column($lines, 'part'));
        $calculators = $this->page('calculators', 'en')['lines'];
        $this->assertSame([], array_values(array_filter($calculators, fn ($l) => ! in_array($l['part'], ['shared-header', 'shared-footer'], true))));
    }

    public function test_every_rule_names_at_least_one_field_and_every_field_is_mapped(): void
    {
        $fields = [];
        foreach ($this->blackJson()['pages'] as $page) {
            foreach ($page['lines'] as $line) {
                $this->assertNotNull($line['field'], "{$page['locale']} {$page['slug']} #{$line['i']} has no importer field");
                $fields[$line['field']] = true;
            }
        }
        foreach (DocxBlack::RULES as [$id, , $pattern]) {
            if ($id === 'registry-label') {
                continue; // site map only, and the site map is not a page
            }
            $this->assertNotEmpty(preg_grep($pattern, array_keys($fields)), "rule {$id} matches no field");
        }
    }

    public function test_the_registry_holds_each_locale_and_never_another_locales_copy(): void
    {
        $registry = $this->blackJson()['registry'];
        $this->assertSame(['en', 'ar', 'ru'], array_keys($registry));
        foreach (['approved', 'hosts', 'ui', 'content', 'lang', 'proposed'] as $category) {
            $this->assertNotEmpty($registry['ar'][$category], "ar {$category}");
        }
        $this->assertContains('Explore This Service', $registry['en']['approved']);
        $this->assertNotContains('Explore This Service', $registry['ar']['content']);
        $this->assertContains('uaelegislation.gov.ae', $registry['en']['hosts']);
    }
}
