<?php

namespace Tests\Feature\Design;

use App\Models\Enquiry;
use App\Requests\Intake;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Design 2 is the only design (owner, 2026-11-25; App\Http\Middleware\RetireDesignSwitch): old links of the retired
 * design switch get a 301 to the same address without its parameter (the other query parameters as they were sent),
 * and the switch's old cookies are ignored and expired on the next response.
 */
final class RetireDesignSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function cookie(TestResponse $response, string $name): ?Cookie
    {
        return collect($response->headers->getCookies())->first(fn (Cookie $cookie) => $cookie->getName() === $name);
    }

    public function test_old_switch_links_redirect_permanently_to_the_same_address_without_the_parameter(): void
    {
        $base = ''; // root-relative Location (App\Support\SiteUrl::redirectTarget(): never built from the Host header)
        foreach ([
            '/en/?design=1' => '/en/',
            '/ar/?design=2' => '/ar/',
            '/?design=2' => '/',
            '/ru/practice-areas/labor-law/?design=1' => '/ru/practice-areas/labor-law/',
            // the other parameters stay, in their order and encoding
            '/ar/practice-areas/?navigator=S05-2&design=2' => '/ar/practice-areas/?navigator=S05-2',
            '/ru/faq/?design=2&q=cheque%20bounced&utm_source=news' => '/ru/faq/?q=cheque%20bounced&utm_source=news',
            '/en/?a[]=1&design=1&a[]=2' => '/en/?a[]=1&a[]=2',
            // any value, an empty one and a list
            '/en/?design=3' => '/en/',
            '/en/?design=' => '/en/',
            '/en/?design[]=1&utm_campaign=x' => '/en/?utm_campaign=x',
            // an unknown address is redirected first, then answers its 404
            '/en/no-such-page-qa/?design=2' => '/en/no-such-page-qa/',
        ] as $from => $to) {
            $this->get($from)->assertStatus(301)->assertHeader('Location', $base.$to);
        }
        $this->get('/en/no-such-page-qa/')->assertNotFound();
        $this->call('HEAD', '/en/about/?design=2')->assertStatus(301)->assertHeader('Location', $base.'/en/about/');
    }

    public function test_an_old_share_link_loses_its_three_parameters(): void
    {
        $this->get('/en/?design_preview=1&expires=1795000000&signature='.str_repeat('a', 64).'&utm_source=x')
            ->assertStatus(301)->assertHeader('Location', '/en/?utm_source=x');
    }

    public function test_other_requests_and_parameters_are_left_alone(): void
    {
        // expires and signature without a share link (a signed URL of something else) are never touched
        $this->get('/en/?expires=1&signature=x')->assertOk();
        $this->get('/en/?designer=1&redesign=2')->assertOk();
        // only GET and HEAD are redirected: a form post is never turned into a GET
        $this->assertNotSame(301, $this->post('/en/enquiries?design=2', [])->getStatusCode());
    }

    public function test_the_old_cookies_are_ignored_and_expired(): void
    {
        foreach (['/en/' => 200, '/ar/about/' => 200, '/ru/no-such-page-qa/' => 404, '/en/?design=1' => 301] as $url => $status) {
            $response = $this->withUnencryptedCookies(['summit_design' => '1', 'summit_design_share' => '9999999999.1.'.str_repeat('0', 64)])
                ->get($url)->assertStatus($status);
            if ($status !== 301) {
                $this->assertStringContainsString('data-design="2"', (string) $response->getContent(), "{$url}: Design 2 whatever the cookie says");
            }
            foreach (['summit_design', 'summit_design_share'] as $name) {
                $cookie = $this->cookie($response, $name);
                $this->assertNotNull($cookie, "{$url}: {$name} is expired");
                $this->assertTrue($cookie->isCleared(), "{$url}: {$name} expires in the past");
                $this->assertSame('/', $cookie->getPath());
            }
        }

        // A visitor without them gets no such cookie (a clean client: the test client keeps the cookies above).
        $this->unencryptedCookies = [];
        $this->defaultCookies = [];
        $response = $this->get('/en/')->assertOk();
        $this->assertNull($this->cookie($response, 'summit_design'));
        $this->assertNull($this->cookie($response, 'summit_design_share'));
    }

    public function test_the_switch_is_gone_from_every_page(): void
    {
        foreach (['/en/', '/ar/faq/', '/ru/practice-areas/labor-law/'] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-design-switch', $html, $url);
            $this->assertStringNotContainsString('menu-sheet__design', $html, $url);
            $this->assertStringNotContainsString('?design=', $html, $url);
        }
        $this->assertNull(config('design.default'));
        $this->assertNull(config('design.switcher'));
    }

    public function test_a_new_request_stores_no_design(): void
    {
        $this->assertTrue(Schema::hasColumn('enquiries', 'design'), 'the column of older requests is kept');
        $this->assertNotContains('design', array_keys(Intake::context(request())));
        $this->assertContains('design', (new Enquiry)->getFillable(), 'older requests keep their value');
    }
}
