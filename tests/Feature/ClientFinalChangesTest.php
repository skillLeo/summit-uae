<?php

namespace Tests\Feature;

use App\Content\Cms\CopySource;
use App\Models\Content\Page;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client's "Final Website Changes Required" (docs/briefs/client-final-changes-2026-11-25.md), the content and
 * contact items (Track 1): each test names its PDF item. The texts come through the client-approved layer
 * (database/content/client/2026-11-25.php) and the settings migrations; item 7 is tests/Feature/WhatsAppOnlyTest.php.
 */
final class ClientFinalChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function html(string $path): string
    {
        return (string) $this->get($path)->assertOk()->getContent();
    }

    /** PDF item 4: the Russian homepage hero, client approved; English and Arabic unchanged. */
    public function test_item_4_the_russian_hero(): void
    {
        $ru = $this->html('/ru/');
        $this->assertStringContainsString('<h1 class="hero__title" id="hero-title"><span class="hero__line">Понятная юридическая поддержка.</span><br><span class="hero__line">Практичные решения.</span></h1>', $ru);
        $this->assertStringContainsString('<p>Понятная юридическая поддержка для частных лиц, компаний, инвесторов и резидентов — основанная на точном понимании фактов, документов и юридических процедур в Объединённых Арабских Эмиратах.</p>', $ru);
        $this->assertStringNotContainsString('Чёткие правовые рекомендации', $ru);
        $this->assertStringContainsString('<span class="hero__line">Clear Legal Guidance.</span>', $this->html('/en/'));
        $this->assertStringContainsString('<span class="hero__line">توجيه قانوني واضح.</span>', $this->html('/ar/'));

        $p01 = Page::query()->findOrFail('P01');
        $this->assertSame(CopySource::CLIENT_APPROVED, $p01->copy_source);
        $this->assertSame(CopySource::CLIENT_APPROVED, collect($p01->blocks)->firstWhere('type', 'hero')['copy_source']);
    }

    /** PDF item 5 and owner decision 4: +971524666191 exactly, everywhere; tel:+971524666191; left to right in Arabic. */
    public function test_item_5_the_phone_number_has_no_spaces(): void
    {
        $this->assertSame('+971524666191', setting('phone_display'));
        $this->assertSame('tel:+971524666191', setting('phone_href'));
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach (['', 'contact/', 'about/', 'faq/', 'resources/', 'case-studies/', 'practice-areas/', 'practice-areas/labor-law/', 'privacy-policy/', 'offline/'] as $path) {
                $html = $this->html("/{$locale}/{$path}");
                $this->assertDoesNotMatchRegularExpression('/\+?971[\s\-\x{00A0}()]+52|52[\s\-]466|466[\s\-]6191/u', $html, "/{$locale}/{$path}");
                $this->assertStringContainsString('href="tel:+971524666191"', $html, "/{$locale}/{$path}");
                if ($locale === 'ar') {
                    preg_match_all('#<(bdi|span|a)\b[^>]*>\s*\+971524666191\s*</\1>#u', $html, $shown);
                    $this->assertNotEmpty($shown[0], "/{$locale}/{$path}");
                    foreach ($shown[0] as $tag) {
                        $this->assertMatchesRegularExpression('#^<bdi\b[^>]*dir="ltr"#', $tag, "/ar/{$path}: the number stays left to right");
                    }
                }
            }
        }
    }

    /** The settings migration of item 5 changes a stored value (db:seed keeps stored settings). */
    public function test_item_5_the_settings_migration_updates_the_stored_number(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->where('group', 'contact')->where('name', 'phone_display')->update(['payload' => json_encode('+971 52 466 6191')]);
        $migration = require database_path('settings/2026_11_25_200000_phone_display_without_spaces.php');
        $migration->up();
        $this->assertSame('"+971524666191"', \Illuminate\Support\Facades\DB::table('settings')->where('group', 'contact')->where('name', 'phone_display')->value('payload'));
        $this->assertStringNotContainsString('466 6191', (string) \Illuminate\Support\Facades\DB::table('settings')->where('name', 'link_registry')->value('payload'));
    }

    /**
     * PDF item 6: one pre-filled WhatsApp message, always in English, on every WhatsApp link of every page in every
     * language; opened in a new tab, never sent; the button in the official green with the white mark.
     */
    public function test_item_6_one_english_whatsapp_message_everywhere(): void
    {
        $message = 'Hello Summit Legal Consultancy, I would like to request a legal consultation. Please contact me.';
        $expected = 'https://wa.me/971524666191?text='.rawurlencode($message);
        foreach (['en', 'ar', 'ru'] as $locale) {
            $this->assertSame($expected, setting('whatsapp_url', $locale));
            foreach (['', 'contact/', 'about/', 'faq/', 'resources/', 'case-studies/', 'calculators/', 'practice-areas/', 'practice-areas/labor-law/',
                'practice-areas/real-estate-rental-disputes/', 'privacy-policy/', 'offline/'] as $path) {
                $html = $this->html("/{$locale}/{$path}");
                preg_match_all('#<a\b[^>]*href="(https://(?:wa\.me|api\.whatsapp\.com)/[^"]*)"[^>]*>#', $html, $links, PREG_SET_ORDER);
                $this->assertNotEmpty($links, "/{$locale}/{$path}");
                foreach ($links as [$tag, $href]) {
                    $this->assertSame($expected, html_entity_decode($href), "/{$locale}/{$path}: the one English message");
                    $this->assertStringContainsString('target="_blank"', $tag, "/{$locale}/{$path}");
                    $this->assertStringContainsString('rel="noopener noreferrer"', $tag, "/{$locale}/{$path}");
                }
            }
        }
        // The floating button and the WhatsApp buttons use the WhatsApp look.
        $home = $this->html('/ar/');
        $this->assertMatchesRegularExpression('#<a class="btn btn--whatsapp btn--48 icon-btn wa-fab[^"]*"#', $home);
        $css = (string) file_get_contents(resource_path('css/d2/components/button.css'));
        $this->assertMatchesRegularExpression('/\.btn\.btn--whatsapp \{[^}]*--btn-bg: var\(--whatsapp-fill\);[^}]*--btn-fg: var\(--ink\);[^}]*--wa-light: none;\s*--wa-dark: block;[^}]*box-shadow: var\(--shadow-whatsapp\);/s', $css);
        $this->assertStringContainsString('--whatsapp-fill: #25D366;', (string) file_get_contents(resource_path('css/d2/tokens.css')));
    }

    /** The settings migration of item 6 sets the stored message for every language. */
    public function test_item_6_the_settings_migration_stores_the_english_message(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->where('group', 'whatsapp')->where('name', 'draft')->update(['payload' => json_encode(['en' => 'Old', 'ar' => 'قديم', 'ru' => 'Старый'])]);
        (require database_path('settings/2026_11_25_210000_one_english_whatsapp_message.php'))->up();
        $stored = json_decode((string) \Illuminate\Support\Facades\DB::table('settings')->where('group', 'whatsapp')->where('name', 'draft')->value('payload'), true);
        $this->assertSame(array_fill_keys(['en', 'ar', 'ru'], 'Hello Summit Legal Consultancy, I would like to request a legal consultation. Please contact me.'), $stored);
    }

    /** PDF item 8: "Case Studies" / «نماذج من ملفات قانونية» / «Юридические кейсы» in the navigation, footer, homepage, title, breadcrumb and SEO. */
    public function test_item_8_the_case_studies_label(): void
    {
        $labels = ['en' => 'Case Studies', 'ar' => 'نماذج من ملفات قانونية', 'ru' => 'Юридические кейсы'];
        foreach ($labels as $locale => $label) {
            $this->assertSame($label, ui('nav.case_studies', $locale));
            $this->assertSame($label, ui('footer.case_studies', $locale));
            $home = $this->html("/{$locale}/");
            $this->assertStringContainsString('>'.e($label).'</h2>', $home, "{$locale}: the homepage section");
            $page = $this->html("/{$locale}/case-studies/");
            $this->assertMatchesRegularExpression('#<title>[^<|]*'.preg_quote(e($label), '#').'[^<|]*\|#u', $page, "{$locale}: the SEO title");
            $this->assertStringContainsString(e($label), content()->page('P04', $locale)['h1'], "{$locale}: the page title");
            $this->assertSame($label, last(content()->page('P04', $locale)['breadcrumb'])['label'], "{$locale}: the breadcrumb");
            foreach ([$home, $page] as $html) {
                $this->assertStringNotContainsString('File Examples', $html);
                $this->assertStringNotContainsString('Примеры из практики', $html);
                $this->assertStringNotContainsString('Примеры из юридической практики', $html);
            }
        }
        $this->assertSame('Все юридические кейсы', ui('cta.view_all_case_studies', 'ru'));
    }

    /**
     * PDF item 9 and owner decision 6: Legal Translation is gone from the site in every language (10 practice areas),
     * its old addresses answer a temporary 302 to Practice Areas, and "Preparation and Drafting of Powers of Attorney" is
     * a hidden draft with the approved titles only.
     */
    public function test_item_9_legal_translation_is_removed_and_powers_of_attorney_is_a_hidden_draft(): void
    {
        $this->assertArrayNotHasKey('S10', config('summit.services'));
        $this->assertCount(10, config('summit.services'));
        foreach (['en', 'ar', 'ru'] as $locale) {
            $this->get("/{$locale}/practice-areas/legal-translation/")->assertStatus(302)->assertRedirect(\App\Support\SiteUrl::page('P02', $locale));
            $this->assertCount(10, content()->services($locale));
            $this->assertNull(content()->service('S10', $locale));
            $this->assertNull(content()->service('S12', $locale));
            foreach (['', 'practice-areas/', 'practice-areas/labor-law/', 'resources/', 'faq/', 'case-studies/', 'about/', 'contact/'] as $path) {
                $html = $this->html("/{$locale}/{$path}");
                $this->assertStringNotContainsString('/practice-areas/legal-translation/', $html, "/{$locale}/{$path}");
                $this->assertStringNotContainsString('Powers of Attorney', $html, "/{$locale}/{$path}: the draft stays hidden");
            }
            $sitemap = $this->get('/sitemap.xml')->getContent();
            $this->assertStringNotContainsString('legal-translation', $sitemap);
        }
        $titles = ['en' => 'Legal Translation', 'ar' => 'الترجمة القانونية', 'ru' => 'Юридический перевод'];
        foreach ($titles as $locale => $title) {
            foreach (['', 'practice-areas/'] as $path) {
                $this->assertStringNotContainsString('>'.e($title).'<', $this->html("/{$locale}/{$path}"), "/{$locale}/{$path}: no Legal Translation card or menu item");
            }
        }

        $s10 = \App\Models\Content\Service::query()->findOrFail('S10');
        $this->assertSame(\App\Content\Cms\Status::DRAFT, $s10->status, 'kept in the admin, off the site');
        $s12 = \App\Models\Content\Service::query()->findOrFail('S12');
        $this->assertSame(\App\Content\Cms\Status::DRAFT, $s12->status);
        $this->assertSame(CopySource::CLIENT_APPROVED, $s12->copy_source);
        $this->assertSame('Preparation and Drafting of Powers of Attorney', $s12->getTranslation('title', 'en'));
        $this->assertSame('إعداد وصياغة الوكالات القانونية', $s12->getTranslation('title', 'ar'));
        $this->assertSame('', $s12->getTranslation('title', 'ru', false), 'the Russian title comes from the client');
        $this->assertNull($s12->getTranslation('intro', 'en', false) ?: null, 'no invented content');
    }

    /**
     * PDF item 13: the home keeps one interactive document section (the hero's sample documents); "Anatomy of a
     * Document" repeated the concept and is off (its block and code are kept, an admin can switch it back on).
     */
    public function test_item_13_one_interactive_document_section_on_the_home(): void
    {
        $this->assertFalse(collect(\App\Content\Cms\HomeBlocks::BLOCKS)->firstWhere('type', 'anatomy')['enabled']);
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->html("/{$locale}/");
            $this->assertStringNotContainsString('id="anatomy"', $html, $locale);
            $this->assertStringNotContainsString('x-data="d2Anatomy"', $html, $locale);
            $this->assertStringContainsString('data-hero', $html, "{$locale}: the hero's sample documents stay");
            foreach (['services', 'who-we-help', 'review', 'navigator', 'about', 'case-studies', 'guides', 'questions', 'consultation-form'] as $id) {
                $this->assertStringContainsString('id="'.$id.'"', $html, "{$locale}: #{$id} stays");
            }
        }
    }
}
