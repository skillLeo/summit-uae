<?php

namespace Tests\Feature;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Client PDF item 20: folder URLs (/en/resources/), never numbered .html files. Laravel serves every folder address
 * itself (there are no .html files in public/), adds the trailing slash with a 301 and answers 404 for any .html
 * address.
 */
final class CleanUrlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_no_public_route_and_no_public_file_is_an_html_file(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('.html', $route->uri(), $route->uri());
        }
        $html = glob(public_path('{,*/,*/*/}*.html'), GLOB_BRACE) ?: [];
        $this->assertSame([], $html, 'no .html file is served from public/');
    }

    public function test_folder_urls_answer_and_html_variants_do_not(): void
    {
        $this->get('/en/resources/')->assertOk();
        $this->get('/en/resources')->assertStatus(301)->assertHeader('Location', '/en/resources/');
        foreach (['/14-resources.html', '/en/14-resources.html', '/en/resources/index.html', '/en/index.html', '/index.html', '/en/practice-areas/labor-law.html'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    public function test_the_front_controller_address_goes_to_the_clean_home_page(): void
    {
        // On Apache, /index.php is served by the front controller with the base URL /index.php: the root redirect
        // still names the clean folder address.
        $this->withServerVariables(['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => public_path('index.php'), 'PHP_SELF' => '/index.php'])
            ->get('/index.php')->assertStatus(301)->assertHeader('Location', '/en/');
    }
}
