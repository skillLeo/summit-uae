<?php

namespace Tests\Feature\Design\D2;

use App\Design\DesignManager;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Design 2's error catch-alls: errors/4xx and errors/5xx (statuses without a page of their own) and
 * errors/401 and errors/402 (Laravel ships stock 401 and 402 pages, which would otherwise win over 4xx).
 * They share d2::errors.status with the family copy (errors.title_4xx / errors.title_5xx). Design 1 keeps
 * Laravel's behaviour exactly: the stock page where one exists, the plain fallback otherwise.
 */
class ErrorCatchAllTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.debug' => false]);
        Exceptions::fake();
        // Test-only route (never in routes/web.php).
        Route::middleware('web')->get('/{locale}/d2-test-catchall/{status}', fn (string $locale, string $status) => abort((int) $status));
    }

    private function visit(string $uri, ?string $design, array $headers = []): TestResponse
    {
        $this->unencryptedCookies = [];
        $this->defaultCookies = [];
        $this->app['session']->driver()->flush();
        $this->app->forgetInstance(DesignManager::class);

        return $this->withUnencryptedCookies($design === null ? [] : ['summit_design' => $design])
            ->withHeaders($headers)
            ->get($uri);
    }

    public function test_design_two_renders_its_own_page_for_4xx_statuses_without_a_page_of_their_own(): void
    {
        foreach ([400, 401, 402, 405, 413, 422] as $status) {
            $response = $this->visit("/en/d2-test-catchall/{$status}", '2');

            $response->assertStatus($status);
            $response->assertSee('data-design="2" data-page="error" data-status="'.$status.'"', false);
            $response->assertSee(__('proposed.d2.errors.title_4xx', [], 'en'));
            $response->assertSee('noindex', false);
        }
    }

    public function test_design_two_renders_its_own_page_for_5xx_statuses_without_a_page_of_their_own(): void
    {
        foreach ([501, 502, 504] as $status) {
            $response = $this->visit("/en/d2-test-catchall/{$status}", '2');

            $response->assertStatus($status);
            $response->assertSee('data-status="'.$status.'"', false);
            $response->assertSee(__('proposed.d2.errors.title_5xx', [], 'en'));
            $response->assertSee('tel:+971524666191', false);
        }
    }

    public function test_the_catch_all_copy_follows_the_language_of_the_url(): void
    {
        $ar = $this->visit('/ar/d2-test-catchall/405', '2');
        $ar->assertStatus(405);
        $ar->assertSee('dir="rtl"', false);
        $ar->assertSee(__('proposed.d2.errors.title_4xx', [], 'ar'));

        $ru = $this->visit('/ru/d2-test-catchall/502', '2');
        $ru->assertStatus(502);
        $ru->assertSee(__('proposed.d2.errors.title_5xx', [], 'ru'));
    }

    public function test_statuses_with_their_own_copy_keep_it(): void
    {
        $response = $this->visit('/en/d2-test-catchall/403', '2');

        $response->assertStatus(403);
        $response->assertSee(__('proposed.d2.errors.title_403', [], 'en'));
        $response->assertDontSee(__('proposed.d2.errors.title_4xx', [], 'en'));
    }

    public function test_design_one_keeps_laravels_behaviour_for_the_same_statuses(): void
    {
        foreach (['1', null] as $design) {
            foreach ([405, 502] as $status) {
                $response = $this->visit("/en/d2-test-catchall/{$status}", $design);
                $response->assertStatus($status);
                $response->assertDontSee('data-design="2"', false);
            }

            // 401 has a stock page: Design 1 renders exactly it.
            (new RegisterErrorViewPaths)();
            $stock = view('errors::401', ['errors' => new ViewErrorBag, 'exception' => new HttpException(401)])->render();
            $this->assertSame($stock, $this->visit('/en/d2-test-catchall/401', $design)->assertStatus(401)->getContent());
        }
    }

    public function test_json_requests_still_get_json(): void
    {
        $response = $this->visit('/en/d2-test-catchall/405', '2', ['Accept' => 'application/json']);

        $response->assertStatus(405);
        $this->assertJson((string) $response->getContent());
    }
}
