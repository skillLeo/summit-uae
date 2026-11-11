<?php

namespace Tests\Feature\Design;

use App\Design\D2\Icons;
use Database\Seeders\ContentSeeder;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Design 2 icon sprite (App\Design\D2\Icons, App\Design\D2\IconSprite): <x-d2.ui.icon> prints
 * <svg><use href="#d2i-{name}"/></svg>, and every HTML response that printed one gets each glyph exactly once,
 * as a <symbol> of one hidden sprite right after <body>. Responses without Design 2 icons stay byte for byte.
 */
final class IconSpriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    /** @return array{0: list<string>, 1: list<string>} the referenced ids and the symbol ids of a page */
    private function references(string $html): array
    {
        preg_match_all('/<use href="#(d2i-[A-Za-z0-9_-]+)"\/>/', $html, $uses);
        preg_match_all('/<symbol id="(d2i-[A-Za-z0-9_-]+)"/', $html, $symbols);

        return [$uses[1], $symbols[1]];
    }

    private function assertSprite(string $html, string $context): void
    {
        $this->assertSame(1, substr_count($html, '<svg class="icon-sprite"'), "{$context}: one sprite");
        $this->assertMatchesRegularExpression('/<body\b[^>]*><svg class="icon-sprite" width="0" height="0" hidden aria-hidden="true" focusable="false" data-icon-sprite><symbol /', $html, "{$context}: right after <body>, before every icon");
        [$uses, $symbols] = $this->references($html);
        $this->assertNotEmpty($uses, $context);
        $this->assertSame(array_values(array_unique($symbols)), $symbols, "{$context}: each glyph once");
        $this->assertEqualsCanonicalizing(array_values(array_unique($uses)), $symbols, "{$context}: a symbol for every glyph used and none unused");
        $this->assertDoesNotMatchRegularExpression('/<svg class="icon(?: [^"]*)?"[^>]*>\s*<(?!use\b)[a-z]/', $html, "{$context}: no glyph drawn inline any more");
        $this->assertSame(preg_match_all('/<svg class="icon(?: [^"]*)?"/', $html), substr_count($html, '<use href="#d2i-'), "{$context}: every icon is a <use>");
    }

    public function test_design_two_pages_print_each_glyph_once_in_a_sprite_after_body(): void
    {
        foreach (['/en/', '/ar/', '/ru/', '/en/contact/', '/ar/practice-areas/', '/ru/styleguide/'] as $url) {
            $html = (string) $this->withUnencryptedCookies(['summit_design' => '2'])->get($url)->assertOk()->getContent();
            $this->assertSprite($html, $url);
        }
        // The home's 43 glyphs serve its ~785 icons.
        [$uses, $symbols] = $this->references((string) $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/')->getContent());
        $this->assertGreaterThan(5 * count($symbols), count($uses));
    }

    public function test_error_pages_get_the_sprite_too(): void
    {
        $html = (string) $this->withUnencryptedCookies(['summit_design' => '2'])->get('/ar/no-such-page/')->assertNotFound()->getContent();
        $this->assertSprite($html, '404');

        // Maintenance mode's 503 is rendered inside the global middleware stack: the sprite middleware is outermost.
        // In memory only: storage/framework/down would take down the other servers on this tree.
        $this->app->instance(MaintenanceMode::class, new class implements MaintenanceMode
        {
            public function activate(array $payload): void {}

            public function deactivate(): void {}

            public function active(): bool
            {
                return true;
            }

            public function data(): array
            {
                return [];
            }
        });
        $html = (string) $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/')->assertStatus(503)->getContent();
        $this->assertStringContainsString('data-status="503"', $html);
        $this->assertSprite($html, '503');
    }

    public function test_responses_without_design_two_icons_are_untouched(): void
    {
        foreach (['/en/', '/ar/contact/'] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('icon-sprite', $html, "Design 1 {$url}");
            $this->assertStringNotContainsString('#d2i-', $html, "Design 1 {$url}");
        }
        $this->assertStringNotContainsString('icon-sprite', (string) $this->withUnencryptedCookies(['summit_design' => '2'])->get('/sitemap.xml')->getContent());
        $this->assertSame([], Icons::used(), 'the request leaves nothing recorded behind');
    }

    public function test_symbols_carry_what_css_cannot_reach_inside_a_use(): void
    {
        // info: a duotone circle, a dot and a stroke; contract: a fill-only duotone shape (stroke="none").
        $info = Icons::symbol('info');
        $this->assertStringStartsWith('<symbol id="d2i-info" viewBox="0 0 24 24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">', $info);
        $this->assertStringContainsString('<circle class="duo" cx="12" cy="12" r="9.25" vector-effect="non-scaling-stroke"/>', $info, 'the duotone shape inherits the <svg> fill');
        $this->assertStringContainsString('<path class="dot" d="M12 7.75h.01" vector-effect="non-scaling-stroke" stroke-width="'.Icons::DOT_STROKE.'" fill="none"/>', $info);
        $this->assertStringContainsString('<path d="M10.5 11H12v5.75M10.25 16.75h3.5" vector-effect="non-scaling-stroke" fill="none"/>', $info);
        $this->assertStringContainsString('<path class="duo" stroke="none" d="M6.5 2.75H14.5', Icons::symbol('contract'));
        $this->assertStringNotContainsString('style=', $info, 'CSP: no style attributes');
        foreach (Icons::names() as $name) {
            $symbol = Icons::symbol($name);
            $this->assertSame(substr_count(Icons::inner($name), '/>'), substr_count($symbol, 'vector-effect="non-scaling-stroke"'), "{$name}: every shape keeps the 1.5 px pen at every size");
        }
    }

    public function test_with_sprite_places_the_sprite_once(): void
    {
        Icons::flush();
        $this->assertSame('<body><p>x</p></body>', Icons::withSprite('<body><p>x</p></body>'), 'no icon, no sprite');

        $this->assertSame('d2i-phone', Icons::use('phone'));
        Icons::use('phone');
        Icons::use('../mail');
        $this->assertSame(['phone', 'mail'], Icons::used(), 'first-use order, each glyph once, names only');

        $html = Icons::withSprite('<!DOCTYPE html><html><body class="d2 page-home"><p>x</p></body></html>');
        $this->assertStringStartsWith('<!DOCTYPE html><html><body class="d2 page-home"><svg class="icon-sprite"', $html);
        $this->assertSame(2, substr_count($html, '<symbol '));
        $this->assertSame($html, Icons::withSprite($html), 'never a second sprite');
        $this->assertStringStartsWith('<svg class="icon-sprite"', Icons::withSprite('<div>fragment</div>'), 'a fragment without <body>: first');

        Icons::flush();
        $this->assertSame([], Icons::used());
    }

    public function test_aliases_draw_their_glyph_under_their_own_name_and_the_whatsapp_mark_is_never_a_symbol(): void
    {
        Icons::flush();
        // Design 2.1 (docs/d2.1/phase-1/components.md §3): the allowed arrows have their own data-icon names but share
        // the glyph's one <symbol>.
        foreach (Icons::ALIASES as $alias => $glyph) {
            $this->assertSame('d2i-'.$glyph, Icons::use($alias), $alias);
            $this->assertSame(Icons::inner($glyph), Icons::inner($alias), $alias);
        }
        $this->assertSame(array_values(array_unique(Icons::ALIASES)), Icons::used(), 'one symbol per glyph, never one per alias');
        Icons::flush();

        // The official WhatsApp logo is a brand mark (Meta's files, unchanged): no glyph file, no symbol on any page.
        $this->assertFileDoesNotExist(resource_path('svg/d2/icons/whatsapp.svg'));
        foreach (['/en/', '/ar/contact/', '/ru/styleguide/'] as $url) {
            $html = (string) $this->withUnencryptedCookies(['summit_design' => '2'])->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('d2i-whatsapp', $html, $url);
            $this->assertStringContainsString('data-brand="whatsapp"', $html, $url);
            preg_match_all('/<svg class="icon(?: [^"]*)?"[^>]*\bdata-icon="([^"]+)"/', $html, $icons);
            $this->assertNotContains('whatsapp', $icons[1], "{$url}: never drawn as an icon of the set");
        }
    }
}
