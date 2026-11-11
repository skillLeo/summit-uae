<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2.1 Phase 2, brief §5.1 item 4: the home's Who We Help (#who-we-help, <x-d2.home.who-list>) against the
 * homepage DOCX (note of section 03: "Use five audience blocks. These explain who the work serves, rather than
 * repeating the service cards."). The intro and all five audience blocks are visible and complete in the HTML, in
 * the DOCX order, each its approved H3 and text; no switch hides four of them, no service chips repeat the service
 * cards, no card (nothing is interactive), no icon or eyebrow. The Design 1 anchors stay on the blocks.
 */
final class WhoWeHelpTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function section(string $locale): string
    {
        app()->setLocale($locale);
        $html = $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get("/{$locale}/")->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<section class="section who" id="who-we-help".*?</section>#s', $html, $m), "{$locale}: #who-we-help renders");

        return $m[0];
    }

    public function test_the_intro_and_five_audience_blocks_are_all_visible_in_the_docx_order(): void
    {
        foreach (self::LOCALES as $locale) {
            $section = $this->section($locale);
            $who = content()->page('P01', $locale)['content']['who_we_help'];
            $this->assertCount(5, $who['items'], "{$locale}: the DOCX has five audiences");
            $this->assertStringContainsString('<p>'.e($who['intro']).'</p>', $section, "{$locale}: the approved intro");

            $positions = [];
            foreach ($who['items'] as $item) {
                $block = '<li class="who__item" id="who-'.$item['key'].'">';
                $at = strpos($section, $block);
                $this->assertNotFalse($at, "{$locale}: block #who-{$item['key']} (the Design 1 anchor)");
                $positions[] = $at;
                $body = substr($section, $at, (int) strpos($section, '</li>', $at) - $at);
                $this->assertStringContainsString('<h3 class="who__title" id="who-'.$item['key'].'-title">'.e($item['title']).'</h3>', $body);
                $this->assertStringContainsString('<p class="who__text">'.e($item['text']).'</p>', $body, "{$locale}: the complete approved text");
                // Nothing hides a block: no hidden attribute, no aria-hidden, no inert.
                $this->assertDoesNotMatchRegularExpression('/\s(hidden|aria-hidden|inert)[\s=>]/', $body);
            }
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, "{$locale}: the DOCX order");
        }
    }

    public function test_no_switch_no_service_chips_no_cards_no_icons(): void
    {
        foreach (self::LOCALES as $locale) {
            $section = $this->section($locale);
            foreach (['x-data', 'segmented', 'type="radio"', 'chip', 'class="card', 'who__panel', 'who__related', 't-eyebrow', 'data-icon=', 'role="group"'] as $gone) {
                $this->assertStringNotContainsString($gone, $section, "{$locale}: no {$gone}");
            }
            $this->assertStringNotContainsString('>'.e(content()->ui('nav.practice_areas', $locale)).'<', $section, "{$locale}: no repeated \"Practice Areas\" label");
            $this->assertSame(0, substr_count($section, '<a '), "{$locale}: the blocks do not repeat the service links");
        }
        $this->assertFileDoesNotExist(resource_path('js/d2/components/who.js'), 'the switch script is retired');
        $this->assertFileDoesNotExist(resource_path('views/components/d2/home/who-switch.blade.php'));

        $css = (string) file_get_contents(resource_path('css/d2/sections/who.css'));
        foreach (['visibility: hidden', 'opacity: 0', 'overflow', 'box-shadow', 'gradient'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, "who.css: no {$gone}");
        }
        // The rows sit on the 12-column grid of the container (lint rule 11): title 1-4, text 5-11 from 1024 px.
        $this->assertStringContainsString('grid-template-columns: repeat(var(--cols), minmax(0, 1fr));', $css);
        $this->assertMatchesRegularExpression('/@media \(width >= 1024px\) \{\s*\.who__title \{\s*grid-column: 1 \/ span 4;\s*\}\s*\.who__text \{\s*grid-column: 5 \/ span 7;/', $css);
    }
}
