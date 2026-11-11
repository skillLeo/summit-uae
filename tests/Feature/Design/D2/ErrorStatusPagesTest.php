<?php

namespace Tests\Feature\Design\D2;

use App\Design\DesignManager;
use Database\Seeders\ContentSeeder;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Design 2 error pages 403, 419, 429, 500 and 503 (owner: error-pages; d2::errors.{status} → d2::errors.status).
 *
 * Laravel renders errors::{status}; DesignManager::apply() lists resources/views/designs/d2 first in
 * config('view.paths') for Design 2, which Laravel's handler maps to the errors:: namespace right before it
 * renders. So Design 2 gets its own page with the right status, in the language of the URL, noindex, without
 * the database, the exception, the address or the query; Design 1 (cookie 1, or no choice) keeps exactly the
 * stock page. bootstrap/app.php applies the design for errors thrown before SetDesign (maintenance mode).
 */
final class ErrorStatusPagesTest extends TestCase
{
    use RefreshDatabase;

    private const STATUSES = [403, 419, 429, 500, 503];

    private const LOCALES = ['en' => 'ltr', 'ar' => 'rtl', 'ru' => 'ltr'];

    private const STOCK = 'vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views';

    /** @var list<string> paths of every view rendered (View::composer) */
    private array $rendered = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // With debug on, an unhandled exception shows Laravel's debug page instead of errors::500.
        config(['app.debug' => false]);
        // The errors raised on purpose here are not written to the log.
        Exceptions::fake();
        // Test-only routes (never in routes/web.php). No SetLocale: the page takes the language from the URL.
        Route::middleware('web')->get('/{locale}/d2-test-status/{status}', fn (string $locale, string $status) => self::raise((int) $status));
        Route::middleware('web')->get('/d2-test-status/{status}', fn (string $status) => self::raise((int) $status));
        Route::middleware('web')->get('/en/d2-test-database/', fn () => DB::table('pages')->count());
        View::composer('*', function ($view) {
            $this->rendered[] = $view->getPath();
        });
    }

    private static function raise(int $status): never
    {
        match ($status) {
            419 => throw new TokenMismatchException('CSRF token mismatch.'),
            429 => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => '60']),
            500 => throw new RuntimeException('secret-detail SQLSTATE[HY000] /var/www/private.php'),
            default => abort($status, $status === 403 ? 'secret-policy-message' : ''),
        };
    }

    /** One request with a clean client: only the given design cookie, no session carried over. */
    private function visit(string $uri, ?string $design): TestResponse
    {
        $this->unencryptedCookies = [];
        $this->defaultCookies = [];
        $this->app['session']->driver()->flush();
        $this->app->forgetInstance(DesignManager::class);
        $this->rendered = [];

        return $this->withUnencryptedCookies($design === null ? [] : ['summit_design' => $design])->get($uri);
    }

    /**
     * The stock page Laravel renders for a status (Design 1 has no error views but its 404), with the same
     * exception message as raise() (the stock 403 prints it).
     */
    private function stock(int $status): string
    {
        (new RegisterErrorViewPaths)();
        $exception = new HttpException($status, $status === 403 ? 'secret-policy-message' : '');

        return view('errors::'.$status, ['errors' => new ViewErrorBag, 'exception' => $exception])->render();
    }

    private function fakeMaintenance(): void
    {
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
                return ['retry' => 120];
            }
        });
    }

    private function assertDesignTwoPage(string $html, int $status, string $locale): void
    {
        $dir = self::LOCALES[$locale];
        // Arabic pages also carry the render-time digit setting (App\Design\D2\Digits, brief §3.7).
        $this->assertMatchesRegularExpression('#<html lang="'.$locale.'" dir="'.$dir.'" class="no-js" data-design="2" data-page="error" data-status="'.$status.'"'.($locale === 'ar' ? '( data-digits="(western|eastern)")?' : '').'>#', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('rel="alternate"', $html);
        $this->assertSame(1, substr_count($html, '<h1'), "{$status} {$locale}: one H1");
        $this->assertStringContainsString('<h1 class="err__title" id="err-title" data-copy-status="proposed">'.e(d2p("errors.title_{$status}", [], $locale)).'</h1>', $html);
        $this->assertStringContainsString('data-copy-status="proposed">'.e(d2p("errors.text_{$status}", [], $locale)).'</p>', $html);
        // Design 2.1 (D01, D03, D04, D06, D13): the code is a plain label (no eyebrow), the brand bar is the official
        // lockup (no tile, no wordmark set in a font), no metal seal, no glyph on the buttons, no object tiles.
        $this->assertStringContainsString('<p class="err__code"><bdi dir="ltr">'.$status.'</bdi></p>', $html);
        $this->assertStringContainsString('<svg class="err-brand__lockup" aria-hidden="true" focusable="false"', $html);
        $this->assertStringNotContainsString('err-brand__tile', $html);
        $this->assertStringNotContainsString('err-brand__name', $html);
        $this->assertStringNotContainsString('err-art__seal', $html);
        $this->assertStringNotContainsString('t-eyebrow', $html);
        $this->assertStringNotContainsString('nf-link__icon', $html);
        $this->assertDoesNotMatchRegularExpression('/data-icon="(home|refresh|arrow|arrow-start|shield|clock|warning)"/', $html);
        $this->assertStringContainsString('<main id="main-content" class="err" tabindex="-1">', $html);
        $this->assertStringNotContainsString('style="', $html, 'no style attributes (the CSP blocks them)');
        $this->assertDoesNotMatchRegularExpression('#<script(?![^>]*\bnonce=)#', $html, 'every inline script carries the nonce');
        $this->assertDoesNotMatchRegularExpression('#<style(?![^>]*\bnonce=)#', $html, 'every inline style carries the nonce');
        $this->assertStringNotContainsString('⟦', $html, 'no missing-content marker');
    }

    public function test_each_status_renders_its_design_two_page_in_the_language_of_the_url(): void
    {
        $this->seed(ContentSeeder::class);

        foreach (self::STATUSES as $status) {
            foreach (array_keys(self::LOCALES) as $locale) {
                $response = $this->visit("/{$locale}/d2-test-status/{$status}", '2')->assertStatus($status);
                $html = (string) $response->getContent();
                $this->assertDesignTwoPage($html, $status, $locale);

                // The Design 2 files, not the stock page.
                $this->assertContains(resource_path("views/designs/d2/errors/{$status}.blade.php"), $this->rendered);
                $this->assertContains(resource_path('views/designs/d2/errors/status.blade.php'), $this->rendered);
                $this->assertEmpty(array_filter($this->rendered, fn (string $path) => str_contains($path, self::STOCK)), "{$status} {$locale}: no stock view");

                // Approved copy, read from the import snapshot, equals the seeded content.
                app()->setLocale($locale);
                $this->assertStringContainsString('<title>'.e(d2p("errors.title_{$status}")).' | '.e(ui('brand.name')).'</title>', $html);
                $this->assertStringContainsString('<a class="skip-link" href="#main-content">'.e(ui('a11y.skip_to_content')).'</a>', $html);
                $this->assertStringContainsString('<a class="err-brand" href="'.url('/').'/'.$locale.'/" aria-label="'.e(ui('brand.logo_alt')).'">', $html);
                // The copyright line with the current Asia/Dubai year (brief §5.2), as the shell's footer.
                $year = (string) now('Asia/Dubai')->year;
                $this->assertStringContainsString('<p class="err-foot__text">'.e((string) preg_replace('/\b\d{4}\b/u', $year, ui('footer.copyright'), 1)).'</p>', $html);
            }
        }
    }

    public function test_status_codes_and_headers_are_kept(): void
    {
        $this->visit('/en/d2-test-status/429', '2')->assertStatus(429)->assertHeader('Retry-After', '60');
        foreach (self::STATUSES as $status) {
            $response = $this->visit("/ar/d2-test-status/{$status}", '2')->assertStatus($status);
            $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
            $this->assertNotNull($response->headers->get('Content-Security-Policy'), 'the global security headers still apply');
        }
    }

    public function test_design_one_renders_exactly_the_stock_pages(): void
    {
        $this->assertSame([resource_path('views')], config('view.paths'));
        $expected = [];
        foreach (self::STATUSES as $status) {
            $expected[$status] = $this->stock($status);
        }

        // A Design 2 request first: Design 1 must not inherit anything from it.
        $this->visit('/en/d2-test-status/500', '2')->assertStatus(500);

        foreach (self::STATUSES as $status) {
            foreach (['no choice' => null, 'cookie 1' => '1'] as $label => $design) {
                foreach (['en', 'ar'] as $locale) {
                    $response = $this->visit("/{$locale}/d2-test-status/{$status}", $design)->assertStatus($status);
                    $this->assertSame($expected[$status], $response->getContent(), "{$status} {$label} {$locale}: the stock page, byte for byte");
                    $this->assertContains(base_path(self::STOCK."/{$status}.blade.php"), $this->rendered);
                    $this->assertEmpty(array_filter($this->rendered, fn (string $path) => str_contains($path, 'designs/d2')), "{$status} {$label}: no Design 2 view");
                    $this->assertSame([resource_path('views')], config('view.paths'), 'Design 1 keeps the original view paths');
                }
            }
        }
        $this->visit('/en/d2-test-status/429', '1')->assertHeader('Retry-After', '60');
    }

    public function test_maintenance_mode_renders_the_design_two_503(): void
    {
        $this->fakeMaintenance();

        foreach (self::LOCALES as $locale => $dir) {
            $response = $this->visit("/{$locale}/", '2')->assertStatus(503)->assertHeader('Retry-After', '120');
            $this->assertDesignTwoPage((string) $response->getContent(), 503, $locale);
        }
    }

    public function test_maintenance_mode_keeps_the_stock_503_for_design_one(): void
    {
        $expected = $this->stock(503);
        $this->fakeMaintenance();

        foreach ([null, '1'] as $design) {
            foreach (['en', 'ar'] as $locale) {
                $response = $this->visit("/{$locale}/", $design)->assertStatus(503)->assertHeader('Retry-After', '120');
                $this->assertSame($expected, $response->getContent(), ($design ?? 'no choice')." {$locale}");
            }
        }
    }

    public function test_the_pages_never_touch_the_database(): void
    {
        // The database is gone: any content(), ui() or setting() call inside the page would throw, and
        // Laravel would fall back to its plain Symfony page instead of the Design 2 page. (A separate
        // connection becomes the default, so RefreshDatabase's own connection and transaction stay intact.)
        $default = config('database.default');
        config([
            // A MySQL server that is not there (port 1 refuses at once).
            'database.connections.gone' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'summit_gone', 'username' => 'nobody', 'password' => '', 'prefix' => ''],
            'database.default' => 'gone',
        ]);

        try {
            $html = (string) $this->visit('/en/d2-test-database/', '2')->assertStatus(500)->getContent();
            $this->assertDesignTwoPage($html, 500, 'en');
            Exceptions::assertReported(fn (QueryException $e) => str_contains($e->getMessage(), 'Connection: gone'));

            foreach (self::STATUSES as $status) {
                $this->assertDesignTwoPage((string) $this->visit("/ar/d2-test-status/{$status}", '2')->assertStatus($status)->getContent(), $status, 'ar');
            }

            $this->fakeMaintenance();
            $this->assertDesignTwoPage((string) $this->visit('/ru/', '2')->assertStatus(503)->getContent(), 503, 'ru');
        } finally {
            config(['database.default' => $default]);
        }
    }

    public function test_the_exception_the_address_and_the_query_are_never_shown(): void
    {
        foreach ([500, 403] as $status) {
            $html = (string) $this->visit("/ru/d2-test-status/{$status}?name=Ivan&case=12345", '2')->assertStatus($status)->getContent();
            foreach (['secret-detail', 'SQLSTATE', 'private.php', 'secret-policy-message', 'd2-test-status', 'Ivan', '12345', 'RuntimeException'] as $leak) {
                $this->assertStringNotContainsString($leak, $html, "{$status}: {$leak}");
            }
        }
    }

    public function test_each_page_offers_the_right_next_step(): void
    {
        $this->seed(ContentSeeder::class);
        $base = url('/');
        app()->setLocale('en');
        $u01 = content()->page('U01');
        $home = '<a class="btn btn--%s btn--48" href="'.$base.'/en/">';
        $retry = '<a class="btn btn--%s btn--48" href="./">';

        // 403: back home (primary), then the approved U01 links.
        $html = (string) $this->visit('/en/d2-test-status/403', '2')->getContent();
        $this->assertStringContainsString(sprintf($home, 'ink'), $html);
        $this->assertStringContainsString(e($u01['actions']['home_label']), $html);
        $this->assertStringNotContainsString('href="./"', $html);

        // 419: go back (reloads the previous page with a nonce'd script; "./" without JavaScript), then home.
        $html = (string) $this->visit('/en/d2-test-status/419', '2')->getContent();
        $this->assertStringContainsString('<a class="btn btn--ink btn--48" href="./" data-error-back="">', $html);
        $this->assertStringContainsString(e(d2p('errors.back')), $html);
        $this->assertStringContainsString(sprintf($home, 'secondary'), $html);
        $this->assertMatchesRegularExpression('#<script nonce="[^"]*">\s*document\.addEventListener\(\'click\'.*?location\.replace\(from\.href\)#s', $html);

        // 429 and 500: try again ("./": the same page without its query), then home.
        foreach ([429, 500] as $status) {
            $html = (string) $this->visit("/en/d2-test-status/{$status}", '2')->getContent();
            $this->assertStringContainsString(sprintf($retry, 'ink'), $html, (string) $status);
            $this->assertStringContainsString('<span data-copy-status="proposed">'.e(d2p('offline.retry')).'</span>', $html);
            $this->assertStringContainsString(sprintf($home, 'secondary'), $html, (string) $status);
            $this->assertStringNotContainsString('data-error-back', $html);
        }

        // 503: try again only (in maintenance mode the home page is down too).
        $html = (string) $this->visit('/en/d2-test-status/503', '2')->getContent();
        $this->assertStringContainsString(sprintf($retry, 'ink'), $html);
        $this->assertSame(1, substr_count($html, 'class="btn '), '503: one action');

        // 403, 419, 429: the approved "Looking for specific information?" links, as on the 404: the kit's link rows.
        foreach ([403, 419, 429] as $status) {
            $html = (string) $this->visit("/en/d2-test-status/{$status}", '2')->getContent();
            $this->assertStringContainsString('<h2 class="err__next-title" id="err-next-title">'.e($u01['more_heading']).'</h2>', $html);
            foreach ($u01['links'] as $link) {
                $this->assertStringContainsString(e($link['label']), $html);
            }
            foreach (['/en/resources/', '/en/faq/', '/en/#consultation-form'] as $path) {
                $this->assertMatchesRegularExpression('#<a class="link-row nf-link" href="'.preg_quote($base.$path, '#').'">\s*<span class="link-row__label">#', $html, "{$status} {$path}");
            }
            $this->assertStringNotContainsString('tel:', $html);
        }
    }

    public function test_500_and_503_show_the_approved_contact_details(): void
    {
        $this->seed(ContentSeeder::class);

        foreach ([500, 503] as $status) {
            foreach (array_keys(self::LOCALES) as $locale) {
                $html = (string) $this->visit("/{$locale}/d2-test-status/{$status}", '2')->getContent();
                app()->setLocale($locale);
                $details = content()->page('P07')['content']['details'];

                $this->assertStringContainsString('<h2 class="err__next-title" id="err-next-title">'.e($details['heading']).'</h2>', $html);
                $this->assertStringContainsString('<span class="err-channel__label">'.e($details['telephone_label']).'</span>', $html);
                $this->assertStringContainsString('<span class="err-channel__label">'.e($details['email_label']).'</span>', $html);
                // The constants equal the approved settings (a changed number in the DOCX fails here).
                $this->assertStringContainsString('href="'.setting('phone_href').'"', $html);
                $this->assertStringContainsString('href="'.setting('email_href').'"', $html);
                $this->assertStringContainsString('<bdi class="err-channel__value" dir="ltr">'.e(setting('phone_display')).'</bdi>', $html);
                $this->assertStringContainsString('<bdi class="err-channel__value" dir="ltr">'.str_replace('@', '@<wbr>', e(setting('email'))).'</bdi>', $html);
                $this->assertSame('tel:+971524666191', setting('phone_href'));
                $this->assertSame('+971 52 466 6191', setting('phone_display'));
                $this->assertSame('info@summitlegaluae.com', setting('email'));
                foreach (['24/7', 'free consultation', 'law firm', 'within 24', 'google.com/maps'] as $banned) {
                    $this->assertStringNotContainsStringIgnoringCase($banned, $html);
                }
            }
        }
    }

    public function test_urls_without_a_language_use_the_default_language(): void
    {
        $this->assertDesignTwoPage((string) $this->visit('/d2-test-status/500', '2')->assertStatus(500)->getContent(), 500, 'en');
        $this->assertDesignTwoPage((string) $this->visit('/de/d2-test-status/403', '2')->assertStatus(403)->getContent(), 403, 'en');
    }

    public function test_the_inline_style_and_scripts_carry_the_response_nonce(): void
    {
        $this->withVite();
        Vite::clearResolvedInstance();

        $response = $this->visit('/ar/d2-test-status/419', '2')->assertStatus(419);
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $m);
        $this->assertNotEmpty($m[1] ?? null);
        preg_match_all('#<(?:script|style)\b[^>]*>#', (string) $response->getContent(), $tags);
        $this->assertGreaterThanOrEqual(4, count($tags[0]), 'fonts, fallback, boot script, back script');
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $tag);
        }
    }

    public function test_json_requests_keep_laravels_json_errors(): void
    {
        $this->withUnencryptedCookies(['summit_design' => '2']);
        $this->getJson('/en/d2-test-status/403')->assertStatus(403)->assertJsonStructure(['message']);
        $this->getJson('/en/d2-test-status/500')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    }

    public function test_debug_mode_keeps_laravels_debug_page_for_unhandled_errors(): void
    {
        config(['app.debug' => true]);
        $html = (string) $this->visit('/en/d2-test-status/500', '2')->assertStatus(500)->getContent();
        $this->assertStringNotContainsString('data-design="2"', $html);
        $this->assertStringContainsString('secret-detail', $html);
    }

    public function test_design_manager_restores_the_view_paths_for_design_one(): void
    {
        $original = config('view.paths');
        $designs = app(DesignManager::class);

        $designs->apply(2);
        $this->assertSame([config('design.d2.views'), ...$original], config('view.paths'));
        $designs->apply(2);
        $this->assertSame([config('design.d2.views'), ...$original], config('view.paths'), 'applying twice adds nothing');
        $designs->apply(1);
        $this->assertSame($original, config('view.paths'));
        $this->assertTrue($designs->applied());
    }

    public function test_proposed_copy_exists_for_every_status_in_every_language(): void
    {
        $keys = [...array_map(fn (int $s) => "title_{$s}", self::STATUSES), ...array_map(fn (int $s) => "text_{$s}", self::STATUSES), 'back'];
        foreach (array_keys(self::LOCALES) as $locale) {
            foreach ($keys as $key) {
                $this->assertNotSame('proposed.d2.errors.'.$key, d2p('errors.'.$key, [], $locale), "{$locale} {$key}");
            }
        }
        foreach ($keys as $key) {
            foreach (['24/7', 'free consultation', 'law firm', 'minutes', 'hours', 'soon', 'shortly', 'immediately'] as $banned) {
                $this->assertStringNotContainsStringIgnoringCase($banned, d2p('errors.'.$key, [], 'en'), "no promise: {$key}");
            }
        }
    }
}
