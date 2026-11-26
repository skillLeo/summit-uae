<?php

namespace Tests\Feature;

use App\Models\Redirect;
use App\Settings\LanguageSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client PDF item 29: https://www.summitlegaluae.com/ answers a permanent 301 to /en/ (the default language's home page),
 * the same for every visitor (no cookie, Accept-Language or IP rule), and the target never redirects back.
 *
 * The Location is root-relative ("/en/"), never built from the request's Host header: a forged Host must not produce
 * a cacheable 301 to another domain (host header injection / cache poisoning). The same holds for the site's other
 * redirects (trailing slash, CMS redirects, the retired design switch).
 */
final class RootRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_the_root_is_a_301_to_the_english_home_page(): void
    {
        $this->get('/')->assertStatus(301)->assertHeader('Location', '/en/');
        // HEAD answers the same (link checkers, uptime monitors).
        $this->call('HEAD', '/')->assertStatus(301)->assertHeader('Location', '/en/');
    }

    public function test_every_visitor_gets_the_same_redirect(): void
    {
        $this->withUnencryptedCookie('summit_lang', 'ar')->get('/')->assertStatus(301)->assertHeader('Location', '/en/');
        $this->withHeaders(['Accept-Language' => 'ru-RU,ru;q=0.9'])->get('/')->assertStatus(301)->assertHeader('Location', '/en/');
        // A language page never redirects by Accept-Language either.
        $this->withHeaders(['Accept-Language' => 'ar'])->get('/en/')->assertOk();
    }

    public function test_the_query_string_survives_and_there_is_no_loop(): void
    {
        // (Request::getQueryString() normalises the order of the parameters.)
        $this->get('/?utm_source=whatsapp&utm_medium=share')->assertStatus(301)
            ->assertHeader('Location', '/en/?utm_medium=share&utm_source=whatsapp');

        // Follow the chain: one hop, then 200.
        $seen = [];
        $url = '/';
        for ($hop = 0; $hop < 5; $hop++) {
            $response = $this->get($url);
            if (! $response->isRedirect()) {
                break;
            }
            $this->assertNotContains($url, $seen, 'redirect loop');
            $seen[] = $url;
            $url = (string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);
        }
        $this->assertSame(['/'], $seen);
        $this->assertSame('/en/', $url);
        $this->get($url)->assertOk();
    }

    public function test_the_target_follows_the_default_language_setting(): void
    {
        $settings = app(LanguageSettings::class);
        $settings->default_locale = 'ar';
        $settings->save();
        content()->flush();

        $this->get('/')->assertStatus(301)->assertHeader('Location', '/ar/');
    }

    public function test_a_forged_host_header_never_reaches_a_redirect(): void
    {
        $evil = ['HTTP_HOST' => 'evil.example', 'SERVER_NAME' => 'evil.example'];
        Redirect::query()->create(['from_path' => '/en/old-page/', 'to_path' => '/en/about/', 'code' => 301, 'enabled' => true]);
        content()->flush();

        foreach ([
            '/' => '/en/',
            '/en' => '/en/',
            '/en/resources?x=1' => '/en/resources/?x=1',
            '/en/old-page/' => '/en/about/',
            '/en/about/?design=2' => '/en/about/',
        ] as $from => $to) {
            $location = (string) $this->withServerVariables($evil)->get($from)->assertStatus(301)->headers->get('Location');
            $this->assertSame($to, $location, $from);
            $this->assertStringNotContainsString('evil.example', $location, $from);
        }
    }
}
