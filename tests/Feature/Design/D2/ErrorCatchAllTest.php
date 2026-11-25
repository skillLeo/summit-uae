<?php

namespace Tests\Feature\Design\D2;

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Design 2's error catch-alls: errors/4xx and errors/5xx (statuses without a page of their own) and
 * errors/401 and errors/402 (Laravel ships stock 401 and 402 pages, which would otherwise win over 4xx).
 * They share d2::errors.status with the family copy (errors.title_4xx / errors.title_5xx).
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

    private function visit(string $uri, array $headers = []): TestResponse
    {
        $this->unencryptedCookies = [];
        $this->defaultCookies = [];
        $this->app['session']->driver()->flush();

        return $this->withHeaders($headers)->get($uri);
    }

    public function test_design_two_renders_its_own_page_for_4xx_statuses_without_a_page_of_their_own(): void
    {
        foreach ([400, 401, 402, 405, 413, 422] as $status) {
            $response = $this->visit("/en/d2-test-catchall/{$status}");

            $response->assertStatus($status);
            $response->assertSee('data-design="2" data-page="error" data-status="'.$status.'"', false);
            $response->assertSee(__('proposed.d2.errors.title_4xx', [], 'en'));
            $response->assertSee('noindex', false);
        }
    }

    public function test_design_two_renders_its_own_page_for_5xx_statuses_without_a_page_of_their_own(): void
    {
        foreach ([501, 502, 504] as $status) {
            $response = $this->visit("/en/d2-test-catchall/{$status}");

            $response->assertStatus($status);
            $response->assertSee('data-status="'.$status.'"', false);
            $response->assertSee(__('proposed.d2.errors.title_5xx', [], 'en'));
            $response->assertSee('tel:+971524666191', false);
        }
    }

    public function test_the_catch_all_copy_follows_the_language_of_the_url(): void
    {
        $ar = $this->visit('/ar/d2-test-catchall/405');
        $ar->assertStatus(405);
        $ar->assertSee('dir="rtl"', false);
        $ar->assertSee(__('proposed.d2.errors.title_4xx', [], 'ar'));

        $ru = $this->visit('/ru/d2-test-catchall/502');
        $ru->assertStatus(502);
        $ru->assertSee(__('proposed.d2.errors.title_5xx', [], 'ru'));
    }

    public function test_statuses_with_their_own_copy_keep_it(): void
    {
        $response = $this->visit('/en/d2-test-catchall/403');

        $response->assertStatus(403);
        $response->assertSee(__('proposed.d2.errors.title_403', [], 'en'));
        $response->assertDontSee(__('proposed.d2.errors.title_4xx', [], 'en'));
    }

    public function test_json_requests_still_get_json(): void
    {
        $response = $this->visit('/en/d2-test-catchall/405', ['Accept' => 'application/json']);

        $response->assertStatus(405);
        $this->assertJson((string) $response->getContent());
    }
}
