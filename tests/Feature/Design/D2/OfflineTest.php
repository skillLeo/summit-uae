<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 offline page /xx/offline/ (owner: system-pages): server-rendered, noindex, what still works
 * without a connection, the approved contact details (phone and e-mail isolated in <bdi>, WhatsApp with
 * the approved draft, no address or map) and a retry link that works without JavaScript.
 */
final class OfflineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function offline(string $locale): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/offline/")->assertOk()->getContent();
    }

    public function test_the_offline_page_renders_in_design_two_in_every_language(): void
    {
        foreach (['en' => 'ltr', 'ar' => 'rtl', 'ru' => 'ltr'] as $locale => $dir) {
            $html = $this->offline($locale);
            app()->setLocale($locale);

            $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.$dir.'" class="no-js" data-design="2" data-page="offline"', $html);
            $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
            $this->assertStringNotContainsString('rel="alternate"', $html);
            $this->assertSame(1, substr_count($html, '<h1'), 'one H1');
            $this->assertStringContainsString(e(proposed('offline.title')).'</h1>', $html);
            foreach (['lead', 'retry', 'list_title', 'call_title', 'call_text', 'email_title', 'email_text', 'docs_title', 'docs_text', 'later'] as $key) {
                $this->assertStringContainsString(e(d2p('offline.'.$key)), $html, "{$locale} offline.{$key}");
            }
        }
    }

    public function test_contact_details_and_retry(): void
    {
        $html = $this->offline('ar');

        $this->assertStringContainsString('href="tel:+971524666191"', $html);
        $this->assertStringContainsString('href="mailto:info@summitlegaluae.com"', $html);
        $this->assertStringContainsString('<bdi class="contact-card__value" dir="ltr">+971 52 466 6191</bdi>', $html);
        $this->assertStringContainsString('dir="ltr">info@summitlegaluae.com</bdi>', $html);
        $this->assertStringContainsString('href="'.e(setting('whatsapp_url', 'ar')).'"', $html);
        $this->assertStringContainsString('https://wa.me/971524666191?text=', $html);
        // Retry reloads the address in the location bar (a service worker shows this page in place of another);
        // Design 2.1: no refresh glyph on it, no glyph tiles on the tips (brief §3.6 rules 3 and 5).
        $this->assertMatchesRegularExpression('#<a class="btn btn--ink btn--\d+" href="\./">#', $html);
        $this->assertStringNotContainsString('data-icon="refresh"', $html);
        $this->assertStringNotContainsString('off-tip__icon', $html);
        // Heading order: h1, h2 "What you can still do", then the card's h3.
        $this->assertMatchesRegularExpression('#<h1[^>]*>.*?<h2[^>]*>.*?<h3 class="contact-card__title"#s', $html);
    }

    public function test_a_readable_fallback_ships_for_a_service_worker_that_serves_the_page_without_its_css(): void
    {
        $html = $this->offline('en');

        // Inline, with the CSP nonce, and only active when the Design 2 tokens are missing.
        $this->assertMatchesRegularExpression('#<style nonce="[^"]+">@container not style\(--d2-tokens: 1\)\{#', $html);
        $this->assertStringContainsString('--d2-tokens: 1;', (string) file_get_contents(resource_path('css/d2/tokens.css')));
    }

    public function test_compliance_no_address_map_or_banned_claims(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $main = preg_match('#<main\b.*?</main>#s', $this->offline($locale), $m) ? $m[0] : '';
            $this->assertNotSame('', $main);
            foreach (['24/7', 'free consultation', 'law firm', 'years of experience', 'google.com/maps', 'maps.app'] as $banned) {
                $this->assertStringNotContainsStringIgnoringCase($banned, $main, "{$locale}: {$banned}");
            }
        }
    }

    public function test_proposed_copy_exists_in_every_language(): void
    {
        $keys = array_keys(require lang_path('en/proposed-d2/offline.php'));
        foreach (['ar', 'ru'] as $locale) {
            $this->assertSame($keys, array_keys(require lang_path("{$locale}/proposed-d2/offline.php")), $locale);
            foreach ($keys as $key) {
                $this->assertNotSame(d2p('offline.'.$key, [], 'en'), d2p('offline.'.$key, [], $locale), "{$locale} offline.{$key} is translated");
            }
        }
    }

    public function test_design_one_keeps_its_own_offline_page(): void
    {
        $html = $this->get('/en/offline/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-design="2"', $html);
        $this->assertStringContainsString('class="has-bottombar', $html);
    }
}
