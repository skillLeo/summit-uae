<?php

namespace Tests\Feature\Design;

use App\Design\DesignManager;
use App\Http\Middleware\SetLocale;
use App\Support\Seo;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/** ?design=1|2, cookie + session persistence, view fallback and the injected switcher (docs/upgrade-plan.md). */
final class DesignSwitchTest extends TestCase
{
    use RefreshDatabase;

    /** Design 1 layout marker (layouts/site body class); Design 2 marks <html data-design="2">. */
    private const D1_MARKER = 'class="has-bottombar';

    protected function setUp(): void
    {
        parent::setUp();
        // Seed inside the test transaction (a class-level $seed would persist in the shared in-memory
        // connection and leak into later test classes).
        $this->seed(ContentSeeder::class);
        // Asset tags are irrelevant here and must not depend on a build or a running dev server.
        $this->withoutVite();
    }

    private function d2(): array
    {
        return ['summit_design' => '2'];
    }

    public function test_default_design_is_one_and_sets_no_cookie(): void
    {
        $response = $this->get('/en/');

        $response->assertOk();
        $this->assertStringNotContainsString('data-design="2"', $response->getContent());
        $response->assertCookieMissing('summit_design');
    }

    public function test_query_switch_sets_cookie_and_redirects_to_the_clean_url(): void
    {
        $response = $this->get('/en/practice-areas/?navigator=S05-2&design=2');

        $response->assertStatus(302);
        $this->assertSame(url('/').'/en/practice-areas/?navigator=S05-2', $response->headers->get('Location'));
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'summit_design');
        $this->assertNotNull($cookie);
        $this->assertSame('2', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_cookie_selects_design_two_on_every_language(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->withUnencryptedCookies($this->d2())->get("/{$locale}/")->assertOk()->getContent();
            $this->assertStringContainsString('data-design="2"', $html, $locale);
            $this->assertStringContainsString('<html lang="'.$locale.'"', $html);
            $this->assertStringNotContainsString(self::D1_MARKER, $html, 'Design 1 layout never renders on Design 2 pages');
        }
    }

    public function test_choice_is_kept_in_the_session(): void
    {
        $this->withUnencryptedCookies($this->d2())->get('/en/')->assertOk();
        $this->assertSame(2, session('summit.design'));

        // Cookie gone, session still there (same test client session): still Design 2.
        $html = $this->get('/ru/')->assertOk()->getContent();
        $this->assertStringContainsString('data-design="2"', $html);
    }

    public function test_design_one_cookie_overrides_session(): void
    {
        $this->withUnencryptedCookies($this->d2())->get('/en/')->assertOk();
        $html = $this->withUnencryptedCookies(['summit_design' => '1'])->get('/en/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-design="2"', $html);
        $this->assertSame(1, session('summit.design'));
    }

    public function test_missing_design_two_views_fall_back_to_design_one(): void
    {
        // Every Design 1 entry view has a Design 2 replacement by now, so the rule is proved with a view that only
        // Design 1 has: a temporary view folder behind resources/views (added before the first request, so the
        // design manager keeps it in its base paths) and a temporary route in the locale group.
        $dir = storage_path('framework/testing/d1-only-views-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($dir.'/pages');
        File::put($dir.'/pages/qa-fallback-probe.blade.php', <<<'BLADE'
            @extends('layouts.site')
            @section('content')<p class="qa-fallback-probe">{{ ui('brand.name') }}</p>@endsection
            BLADE);

        try {
            View::addLocation($dir);
            Route::middleware(['web', SetLocale::class])
                ->prefix('{locale}')
                ->where(['locale' => implode('|', array_keys(config('summit.locales')))])
                ->get('qa-fallback-probe/', fn () => view('pages.qa-fallback-probe', [
                    'seo' => Seo::make('Probe | '.ui('brand.name'), null, index: false, alternates: false),
                    'pageKey' => 'qa-fallback-probe',
                ]));
            $this->assertFileDoesNotExist(resource_path('views/designs/d2/pages/qa-fallback-probe.blade.php'));

            foreach (['en', 'ar', 'ru'] as $locale) {
                $html = $this->withUnencryptedCookies($this->d2())->get("/{$locale}/qa-fallback-probe/")->assertOk()->getContent();
                $this->assertStringContainsString('<p class="qa-fallback-probe">', $html, $locale);
                $this->assertStringContainsString(self::D1_MARKER, $html, "{$locale}: rendered with Design 1's own layout");
                $this->assertStringNotContainsString('data-design="2"', $html, "{$locale}: no Design 2 layout or assets");
            }

            // Control: under the same cookie a view Design 2 does have (pages.offline) renders in Design 2.
            $offline = $this->withUnencryptedCookies($this->d2())->get('/en/offline/')->assertOk()->getContent();
            $this->assertStringContainsString('data-design="2"', $offline);
            $this->assertStringNotContainsString(self::D1_MARKER, $offline);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_unmatched_urls_render_their_404_in_the_chosen_design(): void
    {
        $response = $this->withUnencryptedCookies($this->d2())->get('/ar/no-such-page/');
        $response->assertNotFound();
        if (is_file(resource_path('views/designs/d2/errors/404.blade.php'))) {
            $this->assertStringContainsString('data-design="2"', $response->getContent());
        }
    }

    public function test_invalid_values_are_ignored(): void
    {
        $this->get('/en/?design=3')->assertOk();
        $this->get('/en/?design[]=2')->assertOk();
        $this->get('/en/?design=2x')->assertOk();
        $this->withUnencryptedCookies(['summit_design' => 'x'])->get('/en/')->assertOk();
        $this->assertNull(DesignManager::normalize(['2']));
        $this->assertNull(DesignManager::normalize('22'));
        $this->assertSame(2, DesignManager::normalize('2'));
    }

    public function test_default_design_comes_from_config(): void
    {
        config(['design.default' => 2]);
        $this->assertStringContainsString('data-design="2"', $this->get('/en/')->assertOk()->getContent());
    }

    public function test_switcher_is_injected_only_when_enabled_and_never_for_qa(): void
    {
        config(['design.switcher' => false]);
        $this->assertStringNotContainsString('data-design-switch', $this->get('/en/')->getContent());

        config(['design.switcher' => true]);
        $html = $this->get('/ar/practice-areas/?navigator=S01-1')->getContent();
        $this->assertStringContainsString('data-design-switch', $html);
        $this->assertMatchesRegularExpression('#href="/ar/practice-areas/\?navigator=S01-1&amp;design=2"#', $html);
        $this->assertMatchesRegularExpression('#<style nonce="[^"]+">\.sds#', $html, 'inline CSS carries the CSP nonce');
        $this->assertMatchesRegularExpression('#<script nonce="[^"]+">\(function \(d, w\)#', $html);
        $this->assertSame(1, substr_count($html, '<nav class="sds"'));

        $qa = $this->withHeaders(['X-Summit-QA' => '1'])->get('/en/')->getContent();
        $this->assertStringNotContainsString('data-design-switch', $qa);
        $qaCookie = $this->withUnencryptedCookies(['summit_qa' => '1'])->get('/en/')->getContent();
        $this->assertStringNotContainsString('data-design-switch', $qaCookie);

        $this->assertStringNotContainsString('data-design-switch', $this->get('/sitemap.xml')->getContent());
    }

    public function test_switcher_marks_the_current_design(): void
    {
        config(['design.switcher' => true]);
        $html = $this->withUnencryptedCookies($this->d2())->get('/en/')->getContent();
        $this->assertMatchesRegularExpression('#data-design-option="2"[^>]*aria-current="true"#s', $html);
    }

    public function test_design_one_html_is_identical_with_or_without_an_explicit_design_one_choice(): void
    {
        config(['design.switcher' => false]);
        $normalize = fn (string $html) => preg_replace(
            ['/nonce="[^"]+"/', '/name="_token" value="[^"]+"/', '/content="[A-Za-z0-9]{40}"/', '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/'],
            ['nonce=""', 'name="_token" value=""', 'content=""', 'uuid'],
            $html,
        );
        $plain = $normalize($this->get('/en/')->getContent());
        $chosen = $normalize($this->withUnencryptedCookies(['summit_design' => '1'])->get('/en/')->getContent());
        $this->assertSame($plain, $chosen);
    }
}
