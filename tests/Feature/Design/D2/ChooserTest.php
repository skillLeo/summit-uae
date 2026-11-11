<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 root language chooser "/" (owner: system-pages). A neutral page with one link per language, in
 * its own language and direction, in the order Arabic, English, Russian; the summit_lang cookie still sends
 * "/" straight to the remembered language (SiteController@root); Design 1 keeps its own chooser.
 */
final class ChooserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function chooser(): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get('/')->assertOk()->getContent();
    }

    public function test_the_root_renders_the_design_two_chooser_noindex_with_alternates(): void
    {
        $html = $this->chooser();

        $this->assertStringContainsString('<html lang="en" dir="ltr" class="no-js" data-design="2" data-page="chooser"', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        // Design 2.1 (D02): no middle dot in the document title either.
        $this->assertStringContainsString('<title>'.e(ui('brand.name', 'en')).' | '.e(ui('brand.name', 'ar')).'</title>', $html);
        $this->assertStringNotContainsString('·', $html);
        foreach (['en', 'ar', 'ru', 'x-default'] as $hreflang) {
            $this->assertStringContainsString('<link rel="alternate" hreflang="'.$hreflang.'"', $html);
        }
        // The same Design 2 head as the layout: the inline view-transition opt-in with the CSP nonce.
        $this->assertMatchesRegularExpression('#<style nonce="[^"]+">@view-transition\{navigation:auto\}#', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'one H1');
        $this->assertStringContainsString('<span class="sr-only">'.e(ui('brand.logo_alt', 'en')).'</span>', $html);
        // Neutral: no header, footer or tab bar in one language.
        $this->assertStringNotContainsString('data-d2-header', $html);
        $this->assertStringNotContainsString('d2-footer', $html);
        $this->assertStringNotContainsString('class="has-bottombar', $html, 'never the Design 1 layout');
    }

    public function test_each_language_links_to_its_home_page_in_its_own_language_and_direction(): void
    {
        $html = $this->chooser();
        $base = url('/');

        preg_match_all('#<a class="card [^"]*chooser-card" lang="([a-z]{2})" hreflang="([a-z]{2})" dir="(ltr|rtl)" data-lang-switch="([a-z]{2})"\s+href="([^"]+)"#', $html, $m, PREG_SET_ORDER);
        $this->assertCount(3, $m, 'three language links');
        $this->assertSame(['ar', 'en', 'ru'], array_column($m, 1), 'order: Arabic, English, Russian (Design 1 review LANG-02)');
        foreach ($m as [$all, $lang, $hreflang, $dir, $switch, $href]) {
            $this->assertSame($base.'/'.$lang.'/', $href);
            $this->assertSame($lang, $hreflang);
            $this->assertSame($lang, $switch, 'lang-switch.js remembers the choice in summit_lang');
            $this->assertSame($lang === 'ar' ? 'rtl' : 'ltr', $dir);
        }
        foreach (['ar', 'en', 'ru'] as $code) {
            $this->assertStringContainsString('<span class="chooser-card__name">'.e(ui('lang.'.$code, $code)).'</span>', $html);
            $this->assertStringContainsString('<span class="chooser-card__line">'.e(content()->page('P01', $code)['h1']).'</span>', $html);
            $this->assertStringContainsString('<span lang="'.$code.'" dir="'.($code === 'ar' ? 'rtl' : 'ltr').'">'.e(ui('a11y.choose_language', $code)).'</span>', $html);
        }
        // No flags or emoji, and (Design 2.1: D02, D03, D12, brief §3.8) no invented language codes, no arrows and no
        // mark in a black tile: the H1 is the official traced lockup.
        $this->assertDoesNotMatchRegularExpression('/[\x{1F1E6}-\x{1F1FF}\x{1F300}-\x{1FAFF}]/u', $html);
        $this->assertStringNotContainsString('chooser-card__code', $html);
        $this->assertStringNotContainsString('data-icon="arrow"', $html);
        $this->assertStringNotContainsString('chooser__tile', $html);
        $this->assertStringContainsString('<svg class="chooser__lockup" aria-hidden="true" focusable="false"', $html);
    }

    public function test_a_remembered_language_still_redirects_from_the_root(): void
    {
        foreach (['ar', 'ru'] as $code) {
            $this->withUnencryptedCookies(['summit_design' => '2', 'summit_lang' => $code])
                ->get('/')
                ->assertRedirect(url('/').'/'.$code.'/');
        }
        // An unknown value is ignored: the chooser again.
        $this->withUnencryptedCookies(['summit_design' => '2', 'summit_lang' => 'de'])->get('/')->assertOk();
    }

    public function test_design_one_keeps_its_own_chooser(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-design="2"', $html);
        $this->assertStringNotContainsString('chooser-card', $html);
        $this->assertStringContainsString('class="chooser"', $html);
    }
}
