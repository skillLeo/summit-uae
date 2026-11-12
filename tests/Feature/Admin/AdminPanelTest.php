<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Filament\Support\AdminFormat;
use App\Http\Middleware\SecurityHeaders;
use App\Models\Enquiry;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

/**
 * The internal admin panel (Filament, /admin; spec §12 Phase F): access control, the dashboard, the
 * request list, status changes, RTL for Arabic admins, and the panel's own CSP, which must never leak
 * onto the public site.
 */
final class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Approved service titles and form options come from the DOCX content (inside the test transaction).
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        // Livewire remembers "a component was rendered" in static state and would inject its assets into
        // the next HTML response of this PHP process (a later test's public page). A real request starts
        // clean; reset it here so admin tests never leak into the public-site tests.
        Livewire::flushState();

        parent::tearDown();
    }

    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true] + $attributes)->save();

        return $user;
    }

    private function enquiry(array $attributes = []): Enquiry
    {
        $enquiry = new Enquiry;
        $enquiry->forceFill($attributes + [
            'reference' => Enquiry::newReference(),
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
            'status' => Enquiry::STATUS_NEW,
            'name' => 'A. Example',
            'email' => 'a.example@example.test',
            'phone' => null,
            'preferred_language' => 'ar',
            'matter_type' => 'S06',
            'summary' => 'My end-of-service gratuity has not been paid.',
            'consent_at' => now(),
            'consent_text' => 'I agree.',
            'ip_hash' => Enquiry::hashIp('127.0.0.1'),
        ])->save();

        return $enquiry;
    }

    public function test_a_guest_is_redirected_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/requests')->assertRedirect('/admin/login');
    }

    public function test_the_login_page_renders_with_language_links_and_no_third_party_assets(): void
    {
        $html = $this->get('/admin/login')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->getContent();

        $this->assertStringContainsString('class="slc-langs"', $html);
        $this->assertStringContainsString('href="?lang=ar"', $html);
        $this->assertStringContainsString('/images/admin/logo-tile.png', $html);
        // Self-hosted Design 2 fonts, no Inter, no font CDN, no avatar service.
        $this->assertStringContainsString('data-admin-fonts', $html);
        $this->assertStringNotContainsString('fonts.bunny.net', $html);
        $this->assertStringNotContainsString('fonts/filament/filament/inter', $html);
        $this->assertStringNotContainsString('ui-avatars.com', $html);
    }

    public function test_a_user_without_the_admin_flag_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_the_admin_flag_cannot_be_mass_assigned(): void
    {
        $user = User::create(['name' => 'B. Sample', 'email' => 'b.sample@example.test', 'password' => 'secret-secret', 'is_admin' => true]);

        $this->assertFalse((bool) $user->fresh()->is_admin);
    }

    public function test_an_admin_sees_the_dashboard_widgets(): void
    {
        $this->enquiry(['matter_type' => 'S06']);
        $this->enquiry(['matter_type' => 'S06', 'status' => Enquiry::STATUS_SPAM]);

        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        foreach (['new', 'waiting', 'ttfr', 'closed'] as $tile) {
            $this->assertStringContainsString(e(__('dashboard.tile.'.$tile)), $html);
        }
        // D50: no filler tile, no mixed-measure table
        $this->assertStringNotContainsString(__('admin.stats.ratio_value'), $html);
        $this->assertStringNotContainsString(__('admin.by_language.heading'), $html);
        // Approved service titles from the DOCX import (latest requests).
        $this->assertStringContainsString(e(content()->service('S06', 'en')['title']), $html);
    }

    public function test_an_admin_sees_the_request_list_without_the_summary(): void
    {
        $enquiry = $this->enquiry();

        $html = $this->actingAs($this->admin())->get('/admin/requests')->assertOk()->getContent();

        $this->assertStringContainsString($enquiry->reference, $html);
        $this->assertStringContainsString('A. Example', $html);
        $this->assertStringNotContainsString('gratuity has not been paid', $html);
    }

    public function test_the_request_page_shows_the_decrypted_summary(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->admin())
            ->get("/admin/requests/{$enquiry->getKey()}")
            ->assertOk()
            ->assertSee('My end-of-service gratuity has not been paid.')
            ->assertSee(__('admin.attachment.disabled'));
    }

    public function test_an_admin_can_change_the_status_and_keep_an_encrypted_note(): void
    {
        $enquiry = $this->enquiry();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewEnquiry::class, ['record' => $enquiry->getRouteKey()])
            ->callAction('status', data: ['status' => 'in_review'])
            ->assertHasNoActionErrors()
            ->callAction('note', data: ['body' => 'Called back, documents requested.'])
            ->assertHasNoActionErrors();

        $enquiry->refresh();
        $this->assertSame('in_review', $enquiry->status);
        $raw = DB::table('enquiries')->where('id', $enquiry->getKey())->first();
        $this->assertNotNull($raw->status_changed_at);
        $note = DB::table('enquiry_notes')->where('enquiry_id', $enquiry->getKey())->first();
        $this->assertNotSame('Called back, documents requested.', $note->body);
        $this->assertSame('Called back, documents requested.', Crypt::decryptString($note->body));
        // The visitor's own submission is untouched.
        $this->assertSame('My end-of-service gratuity has not been paid.', $enquiry->summary);
    }

    public function test_status_transitions_from_the_request_page_and_the_list(): void
    {
        $enquiry = $this->enquiry();
        $other = $this->enquiry();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewEnquiry::class, ['record' => $enquiry->getRouteKey()])->callAction('markInReview');
        $this->assertSame('in_review', $enquiry->fresh()->status);

        Livewire::test(ViewEnquiry::class, ['record' => $enquiry->getRouteKey()])->callAction('close', data: ['outcome' => 'engaged']);
        $this->assertSame('closed', $enquiry->fresh()->status);
        $this->assertSame('engaged', $enquiry->fresh()->outcome);

        Livewire::test(ListEnquiries::class)
            ->set('activeTab', 'all')
            ->callTableBulkAction('bulkStatus', [$other], data: ['status' => 'closed', 'outcome' => 'no_reply'])
            ->assertHasNoTableBulkActionErrors();
        $this->assertSame('closed', $other->fresh()->status);
    }

    public function test_an_unknown_status_is_never_stored(): void
    {
        $enquiry = $this->enquiry();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewEnquiry::class, ['record' => $enquiry->getRouteKey()])
            ->callAction('status', data: ['status' => 'hacked'])
            ->assertHasActionErrors(['status']);

        $this->assertSame(Enquiry::STATUS_NEW, $enquiry->fresh()->status);
    }

    public function test_requests_cannot_be_created_or_deleted_in_the_panel(): void
    {
        $this->actingAs($this->admin())->get('/admin/requests/create')->assertNotFound();
    }

    public function test_an_arabic_admin_gets_the_panel_right_to_left(): void
    {
        $html = $this->actingAs($this->admin(['admin_locale' => 'ar']))->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('lang="ar"', $html);
        $this->assertStringContainsString(__('dashboard.title', [], 'ar'), $html);
    }

    public function test_arabic_request_values_start_under_their_labels_and_dates_use_the_arabic_comma(): void
    {
        $enquiry = $this->enquiry(['summary' => "First line.\nSecond line <b>kept as text</b>."]);
        $admin = $this->admin(['admin_locale' => 'ar']);

        $html = $this->actingAs($admin)->get("/admin/requests/{$enquiry->getKey()}")->assertOk()->getContent();

        // Short values are inline isolates inside the right-to-left cell (they line up under their labels) ...
        $this->assertStringContainsString('<bdi dir="auto">A. Example</bdi>', $html);
        $this->assertStringContainsString('<bdi dir="ltr">a.example@example.test</bdi>', $html);
        // ... the visitor's own text is a block in its own direction, line breaks kept and escaped.
        $this->assertMatchesRegularExpression('/<div[^>]*class="[^"]*slc-prose[^"]*"[^>]*dir="auto"/', $html);
        $this->assertStringContainsString("First line.<br>\nSecond line &lt;b&gt;kept as text&lt;/b&gt;.", $html);

        // Arabic dates: the Arabic comma, never the Latin one.
        $consent = $enquiry->consent_at->copy()->setTimezone('Asia/Dubai')->locale('ar');
        $this->assertStringContainsString($consent->translatedFormat('j F Y، H:i'), $html);
        $this->assertStringNotContainsString($consent->translatedFormat('j F Y, H:i'), $html);

        $received = $enquiry->received_at->copy()->setTimezone('Asia/Dubai')->locale('ar');
        $this->assertStringContainsString($received->translatedFormat('j F Y، H:i'), $html);

        $english = $this->actingAs($this->admin())->get("/admin/requests/{$enquiry->getKey()}")->assertOk()->getContent();
        $this->assertStringContainsString($enquiry->consent_at->copy()->setTimezone('Asia/Dubai')->locale('en')->translatedFormat('j F Y, H:i'), $english);
    }

    public function test_attachment_sizes_use_decimal_units_like_the_public_form(): void
    {
        // The form's limit is "up to 10 MB" = 10,000,000 bytes and its upload chip counts in decimal units.
        $this->assertSame('2.4 MB', AdminFormat::fileSize(2_400_078, 'en'));
        $this->assertSame('2,4 МБ', AdminFormat::fileSize(2_400_078, 'ru'));
        $this->assertSame('2.4 ميغابايت', AdminFormat::fileSize(2_400_078, 'ar'));
        $this->assertSame('10 MB', AdminFormat::fileSize(10_000_000, 'en'));
        $this->assertSame('999 KB', AdminFormat::fileSize(999_499, 'en'));
        $this->assertSame('1 KB', AdminFormat::fileSize(300, 'en'));

        $enquiry = $this->enquiry(['attachment_path' => 'enquiries/SLC-TEST/0a1b2c.pdf', 'attachment_mime' => 'application/pdf', 'attachment_size' => 2_400_078]);

        $html = $this->actingAs($this->admin())->get("/admin/requests/{$enquiry->getKey()}")->assertOk()->getContent();

        $this->assertStringContainsString('<bdi dir="ltr">PDF</bdi> · 2.4 MB', $html);
        $this->assertStringNotContainsString('2.3 MB', $html);
        // The stored path is never shown.
        $this->assertStringNotContainsString('0a1b2c', $html);
    }

    public function test_the_login_page_language_switch_is_kept_in_the_session(): void
    {
        $this->get('/admin/login?lang=ru')->assertOk()->assertSee('lang="ru"', false);
        $this->get('/admin/login')->assertOk()->assertSee('lang="ru"', false);
        // Anything else is ignored.
        $this->get('/admin/login?lang[]=ar')->assertOk()->assertSee('lang="ru"', false);
    }

    public function test_the_admin_csp_applies_to_the_panel_and_livewire_only(): void
    {
        $admin = (string) $this->get('/admin/login')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'unsafe-eval'", $admin);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $admin);
        $this->assertStringNotContainsString('challenges.cloudflare.com', $admin);

        $public = (string) $this->get('/en/')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString("'unsafe-eval'", $public);
        $this->assertStringNotContainsString("'unsafe-inline'", $public);
        $this->assertStringContainsString('https://challenges.cloudflare.com', $public);
        $this->assertMatchesRegularExpression("/style-src 'self' 'nonce-[^']+'/", $public);

        $livewire = ltrim(EndpointResolver::prefix(), '/');
        $this->assertTrue(SecurityHeaders::isAdminRequest(Request::create("/{$livewire}/update", 'POST')));
        $this->assertTrue(SecurityHeaders::isAdminRequest(Request::create('/admin/requests')));
        $this->assertFalse(SecurityHeaders::isAdminRequest(Request::create('/en/')));
        $this->assertFalse(SecurityHeaders::isAdminRequest(Request::create('/administration/')));
        $this->assertFalse(SecurityHeaders::isAdminRequest(Request::create('/en/admin/')));
    }

    public function test_installing_the_panel_leaves_the_public_html_of_both_designs_untouched(): void
    {
        // blade-icons must neither take over Design 1's <x-icon> nor register its icon components.
        $aliases = array_keys(app('blade.compiler')->getClassComponentAliases());
        $this->assertNotContains('icon', $aliases);
        $this->assertSame([], array_values(array_filter($aliases, fn (string $alias): bool => str_starts_with($alias, 'heroicon'))));

        // Livewire's data-navigate-track tag attributes are removed from the app's real Vite instance.
        $this->withVite();
        $attributes = (function (): array {
            $out = [];
            foreach ([...$this->scriptTagAttributesResolvers, ...$this->styleTagAttributesResolvers] as $resolver) {
                $out += (array) $resolver('resources/js/app.js', '/build/app.js', null, null);
            }

            return $out;
        })->call(app(Vite::class));
        $this->assertArrayNotHasKey('data-navigate-track', $attributes);
        $this->withoutVite();

        // No Livewire or Filament output on public pages of either design.
        foreach ([['/en/', []], ['/ar/', ['summit_design' => '2']], ['/ru/faq/', ['summit_design' => '2']]] as [$path, $cookies]) {
            $html = $this->withUnencryptedCookies($cookies)->get($path)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-navigate-track', $html, $path);
            $this->assertStringNotContainsStringIgnoringCase('livewire', $html, $path);
            $this->assertStringNotContainsStringIgnoringCase('filament', $html, $path);
        }
    }

    public function test_the_design_switcher_is_never_injected_into_the_panel(): void
    {
        config(['design.switcher' => true]);

        $this->get('/admin/login')->assertOk()->assertDontSee('data-design-switch', false);
        $this->get('/en/')->assertOk()->assertSee('data-design-switch', false);
    }
}
