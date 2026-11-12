<?php

namespace Tests\Feature\Cms\Admin;

use App\Access\RoleSync;
use App\Design\DesignManager;
use App\Filament\Cms\Pages\Settings\Brand;
use App\Filament\Cms\Pages\Settings\DesignPreview;
use App\Filament\Cms\Pages\Settings\FormsConsent;
use App\Filament\Cms\Pages\Settings\GeneralContact;
use App\Filament\Cms\Pages\Settings\Languages;
use App\Filament\Cms\Pages\Settings\Notifications;
use App\Filament\Cms\Pages\Settings\OfficeHours;
use App\Filament\Cms\Pages\Settings\WhatsApp;
use App\Filament\Support\AdminPalette;
use App\Models\Revision;
use App\Models\User;
use App\Settings\BrandSettings;
use App\Settings\DesignSettings;
use App\Settings\LanguageSettings;
use App\Settings\NotificationSettings;
use App\Settings\OfficeHoursSettings;
use App\Settings\RequestSettings;
use App\Settings\SeoSettings;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Phase 7 settings screens (docs/briefs/design-2.1-and-admin-cms.md §9.4, §12.3): access per screen key, the save
 * confirmation and its one activity entry with only the changed fields, Languages, Forms and Consent, Notifications,
 * Brand (the accent contrast check), Design Preview (share links, production) and the public HTML with the defaults.
 */
final class SettingsScreensTest extends TestCase
{
    use RefreshDatabase;

    /** screen key => page */
    private const SCREENS = [
        'settings_general' => GeneralContact::class,
        'settings_hours' => OfficeHours::class,
        'settings_whatsapp' => WhatsApp::class,
        'settings_languages' => Languages::class,
        'settings_forms' => FormsConsent::class,
        'settings_notifications' => Notifications::class,
        'settings_brand' => Brand::class,
        'settings_design' => DesignPreview::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        RoleSync::run();
        $this->withoutVite();
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['name' => 'Super Admin Person']);
        $user->forceFill(['is_admin' => true])->save();
        $this->grantRole($user, 'super_admin');

        return $user->refresh();
    }

    /** @param list<string> $permissions */
    private function staff(array $permissions): User
    {
        $role = Role::findOrCreate('settings_tester_'.md5(implode(',', $permissions)), 'web');
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create(['name' => 'Settings Tester']);
        $user->forceFill(['is_admin' => true])->save();
        $this->grantRole($user, $role);

        return $user->refresh();
    }

    private function editor(): User
    {
        $user = User::factory()->create(['name' => 'Editor Person']);
        $user->forceFill(['is_admin' => true])->save();
        $this->grantRole($user, 'editor');

        return $user->refresh();
    }

    /** @return list<Activity> */
    private function settingsActivity(string $screen): array
    {
        return Activity::query()->where('log_name', 'admin')->where('description', $screen.'.update')->orderBy('id')->get()->all();
    }

    private function html(string $url, int $design, array $cookies = []): string
    {
        $html = (string) $this->withUnencryptedCookies(['summit_design' => (string) $design, 'summit_qa' => '1'] + $cookies)->get($url)->assertOk()->getContent();

        return self::normalise($html);
    }

    /** The parts that differ on every request: CSP nonces, CSRF tokens, the form's idempotency key. */
    private static function normalise(string $html): string
    {
        return (string) preg_replace(
            ['/nonce="[^"]*"/', '/name="idempotency_key" value="[^"]*"/', '/name="_token" value="[^"]*"/', '/(name="csrf-token" content=")[^"]*/'],
            ['nonce=""', 'name="idempotency_key"', 'name="_token"', '$1'],
            $html,
        );
    }

    // ------------------------------------------------------------------ access

    public function test_every_settings_screen_opens_for_a_super_admin_and_is_refused_without_its_permission(): void
    {
        $super = $this->superAdmin();
        foreach (self::SCREENS as $key => $page) {
            $this->actingAs($super);
            $this->assertTrue(Livewire::test($page)->assertOk()->instance()->canEdit(), $key);
            $this->assertTrue($page::canAccess(), $key);

            $this->actingAs($this->editor());
            $this->assertFalse($page::canAccess(), "{$key}: the editor role has no settings permission");
            $this->get($page::getUrl())->assertForbidden();
        }

        // view only: the screen opens read-only; another screen stays closed
        $viewer = $this->staff(['settings_hours.view', 'dashboard.view']);
        $this->actingAs($viewer);
        $this->get(OfficeHours::getUrl())->assertOk();
        $this->get(Languages::getUrl())->assertForbidden();
        $before = app(OfficeHoursSettings::class)->open;
        $page = Livewire::test(OfficeHours::class)->assertOk();
        $this->assertFalse($page->instance()->canEdit(), 'read-only without settings_hours.update');
        $page->fillForm(['open' => '11:00'])->call('confirmSave')->assertActionNotMounted();
        $this->assertSame($before, app(OfficeHoursSettings::class)->refresh()->open);
    }

    // ------------------------------------------------------------------ confirmation and log

    public function test_saving_asks_for_a_confirmation_and_logs_only_the_changed_fields(): void
    {
        $this->actingAs($this->superAdmin());

        // nothing changed: no dialog, no entry
        Livewire::test(Notifications::class)->call('confirmSave')->assertActionNotMounted();
        $this->assertSame([], $this->settingsActivity('settings_notifications'));

        $page = Livewire::test(Notifications::class)
            ->fillForm(['digest_time' => '10:30', 'overdue_alerts' => false, 'recipients' => [['email' => 'duty@example.com']]])
            ->call('confirmSave')
            ->assertHasNoErrors()
            ->assertActionMounted('saveSettings')
            ->assertMountedActionModalSee(['Save these settings?', 'You are changing', 'legal or data impact']);
        $this->assertSame('09:00', app(NotificationSettings::class)->refresh()->digest_time, 'nothing is written before the confirmation');
        $this->assertSame([], $this->settingsActivity('settings_notifications'));
        $sentence = $page->instance()->confirmationSentence(['digest_time', 'overdue_alerts', 'recipients']);
        $this->assertStringContainsString('legal or data impact', $sentence, 'the recipients receive personal data');

        $page->callMountedAction()->assertActionNotMounted();
        $settings = app(NotificationSettings::class)->refresh();
        $this->assertSame('10:30', $settings->digest_time);
        $this->assertFalse($settings->overdue_alerts);
        $this->assertSame(['duty@example.com'], $settings->recipients);

        $log = $this->settingsActivity('settings_notifications');
        $this->assertCount(1, $log, 'one entry per save');
        $this->assertSame('notifications', $log[0]->properties['group']);
        $this->assertSame([
            'recipients' => ['old' => [], 'new' => ['duty@example.com']],
            'digest_time' => ['old' => '09:00', 'new' => '10:30'],
            'overdue_alerts' => ['old' => true, 'new' => false],
        ], $log[0]->properties['changes']);

        // the existing screens confirm too (Cmd/Ctrl+S and Enter submit to confirmSave)
        Livewire::test(OfficeHours::class)->fillForm(['open' => '10:00'])->call('confirmSave')->assertActionMounted('saveSettings')->callMountedAction();
        $this->assertSame('10:00', app(OfficeHoursSettings::class)->refresh()->open);
        $this->assertSame(['open' => ['old' => '09:00', 'new' => '10:00']], $this->settingsActivity('settings_hours')[0]->properties['changes']);
    }

    public function test_an_invalid_form_never_reaches_the_confirmation(): void
    {
        $this->actingAs($this->superAdmin());
        Livewire::test(Notifications::class)->fillForm(['recipients' => [['email' => 'not-an-address']]])->call('confirmSave')
            ->assertHasFormErrors()->assertActionNotMounted();
        $this->assertSame([], app(NotificationSettings::class)->refresh()->recipients);
    }

    // ------------------------------------------------------------------ languages

    public function test_language_defaults_leave_every_locale_reachable_and_a_locale_switched_off_answers_404(): void
    {
        $settings = app(LanguageSettings::class);
        $this->assertSame(['en', 'ar', 'ru'], $settings->enabled_locales);
        $this->assertSame('en', $settings->default_locale);
        $this->assertNull($settings->language_suggestion);
        foreach ([1, 2] as $design) {
            foreach (['/en/', '/ar/', '/ru/', '/ru/contact/'] as $url) {
                $this->html($url, $design);
            }
        }

        $this->actingAs($this->superAdmin());
        Livewire::test(Languages::class)
            ->assertFormSet(['enabled_locales' => ['en', 'ar', 'ru'], 'default_locale' => 'en', 'language_suggestion' => 'environment'])
            ->fillForm(['enabled_locales' => ['ar'], 'default_locale' => 'ar', 'language_suggestion' => 'off'])
            ->call('confirmSave')->assertHasNoErrors()->callMountedAction();
        $settings->refresh();
        $this->assertSame(['en', 'ar'], $settings->enabled_locales, 'English is always on');
        $this->assertSame('ar', $settings->default_locale);
        $this->assertFalse($settings->language_suggestion);

        $this->withUnencryptedCookies(['summit_qa' => '1'])->get('/ru/')->assertNotFound();
        $this->withUnencryptedCookies(['summit_qa' => '1'])->get('/ru/contact/')->assertNotFound();
        $d2 = $this->html('/en/', 2);
        $this->assertStringNotContainsString('hreflang="ru"', $d2, 'Design 2: the menus and hreflang links leave Russian out');
        $this->assertStringContainsString('hreflang="ar"', $d2);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="https://www.summitlegaluae.com/ar/">', $d2);
        $d1 = $this->html('/en/', 1);
        $this->assertStringNotContainsString('hreflang="ru"', $d1);
        $this->assertStringNotContainsString('data-suggest-dismiss', $d1, 'the suggestion bar follows the setting');
        $this->assertStringNotContainsString('/ru/', (string) $this->get('/sitemap.xml')->getContent());

        // the default language must be on
        Livewire::test(Languages::class)->fillForm(['enabled_locales' => ['en'], 'default_locale' => 'ru'])->call('confirmSave')
            ->assertHasFormErrors(['default_locale'])->assertActionNotMounted();

        // back to all three: reachable again
        Livewire::test(Languages::class)->fillForm(['enabled_locales' => ['en', 'ar', 'ru'], 'default_locale' => 'en', 'language_suggestion' => 'environment'])
            ->call('confirmSave')->callMountedAction();
        $this->html('/ru/', 2);
        $this->assertStringContainsString('hreflang="ru"', $this->html('/en/', 1));
    }

    // ------------------------------------------------------------------ forms and consent

    public function test_forms_and_consent_saves_retention_with_the_legal_confirmation_and_warns_about_a_newer_privacy_revision(): void
    {
        $this->actingAs($this->superAdmin());
        $first = Revision::query()->create(['revisionable_type' => 'page', 'revisionable_id' => 'L01', 'snapshot' => [], 'created_at' => now()->subDay()]);
        $newer = Revision::query()->create(['revisionable_type' => 'page', 'revisionable_id' => 'L01', 'snapshot' => [], 'created_at' => now()]);

        $page = Livewire::test(FormsConsent::class)
            ->assertSee('I agree to the use of my data')
            ->assertSee('أوافق على استخدام بياناتي')
            ->assertSee('Locked:')
            ->fillForm(['retention_closed_months' => 36, 'consent_privacy_revision_id' => $first->id])
            ->assertSee('A newer Privacy Policy revision was published (revision '.$newer->id)
            ->call('confirmSave')->assertHasNoErrors()->assertActionMounted('saveSettings');
        $this->assertStringContainsString('legal or data impact', $page->instance()->confirmationSentence(['retention_closed_months']));
        $page->callMountedAction();

        $settings = app(RequestSettings::class)->refresh();
        $this->assertSame(36, $settings->retention_closed_months);
        $this->assertSame($first->id, $settings->consent_privacy_revision_id);
        $changes = $this->settingsActivity('settings_forms')[0]->properties['changes'];
        $this->assertEqualsCanonicalizing(['retention_closed_months', 'consent_privacy_revision_id'], array_keys(collect($changes)->all()));

        Livewire::test(FormsConsent::class)->fillForm(['retention_spam_days' => 0])->call('confirmSave')->assertHasFormErrors(['retention_spam_days']);
        $this->assertFalse((bool) config('summit.features.attachments_enabled'), 'the attachment field has no switch here');
    }

    // ------------------------------------------------------------------ brand

    public function test_the_accent_contrast_check_blocks_a_failing_colour_and_accepts_bronze(): void
    {
        $this->actingAs($this->superAdmin());
        $this->assertTrue(collect(Brand::checks('#8A6A2E'))->every(fn ($c) => $c['pass']));
        $this->assertFalse(collect(Brand::checks('#C9A96B'))->every(fn ($c) => $c['pass']), 'gold fails white text');
        $this->assertSame('5.02:1', Brand::ratioLabel(Brand::contrast('#FFFFFF', '#8A6A2E')));

        Livewire::test(Brand::class)->fillForm(['accent' => '#C9A96B'])->call('confirmSave')
            ->assertHasFormErrors(['accent'])->assertActionNotMounted()->assertSee('4.5');
        Livewire::test(Brand::class)->fillForm(['accent' => 'C9A96B'])->call('confirmSave')->assertHasFormErrors(['accent']);
        $this->assertNull(app(BrandSettings::class)->refresh()->accent);

        Livewire::test(Brand::class)->fillForm(['accent' => '#1F4E79'])->call('confirmSave')->assertHasNoErrors()->callMountedAction();
        $this->assertSame('#1F4E79', app(BrandSettings::class)->refresh()->accent);
        $this->assertSame('#1F4E79', AdminPalette::colors('#1F4E79')['primary'][500], 'the admin primary follows the accent');

        // bronze is the default: stored as null; "Reset to default" empties the field
        Livewire::test(Brand::class)->callFormComponentAction('accent', 'resetAccent')->assertFormSet(['accent' => null]);
        Livewire::test(Brand::class)->fillForm(['accent' => '#8a6a2e'])->call('confirmSave')->assertHasNoErrors()->callMountedAction();
        $this->assertNull(app(BrandSettings::class)->refresh()->accent);
        $this->assertSame(AdminPalette::primary(), AdminPalette::colors()['primary'], 'no accent: the bronze palette of today');

        // a user who may edit Brand but is not the super admin cannot change the accent
        app(BrandSettings::class)->fill(['accent' => '#1F4E79'])->save();
        $this->actingAs($this->staff(['settings_brand.view', 'settings_brand.update']));
        Livewire::test(Brand::class)->assertFormFieldIsDisabled('accent')
            ->fillForm(['favicon' => null, 'accent' => '#000000'])->call('confirmSave')->assertActionNotMounted();
        $this->assertSame('#1F4E79', app(BrandSettings::class)->refresh()->accent);
    }

    public function test_the_brand_social_image_is_the_seo_default_social_image(): void
    {
        $this->actingAs($this->superAdmin());
        app(SeoSettings::class)->fill(['default_og_image' => 'a-uuid'])->save();
        Livewire::test(Brand::class)->assertFormSet(['social_image' => 'a-uuid']);
    }

    // ------------------------------------------------------------------ design preview

    public function test_a_share_link_is_signed_shows_the_switch_and_expires_after_seven_days(): void
    {
        config(['design.switcher' => false]);
        app(DesignSettings::class)->fill(['switcher' => 'link'])->save();
        $this->assertSame('link', app(DesignManager::class)->switcherMode());
        $link = DesignManager::createShareLink('en');
        $this->assertMatchesRegularExpression('#/en/\?design_preview=1&expires=\d+&signature=[0-9a-f]{64}$#', $link['url']);
        $this->assertSame(7, (int) round(now()->diffInDays($link['expires'])));

        // a forged signature sets nothing
        $forged = $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $link['url']));
        $forged->assertRedirect(url('/').'/en/');
        $this->assertNull(collect($forged->headers->getCookies())->first(fn (Cookie $c) => $c->getName() === 'summit_design_share'));

        $response = $this->get($link['url']);
        $response->assertRedirect(url('/').'/en/');
        $cookie = collect($response->headers->getCookies())->first(fn (Cookie $c) => $c->getName() === 'summit_design_share');
        $this->assertNotNull($cookie, 'the share link sets the share cookie');
        $this->assertTrue($cookie->isHttpOnly());
        $value = decrypt($cookie->getValue(), false);
        $plain = str_contains($value, '|') ? substr($value, strpos($value, '|') + 1) : $value;

        $page = fn () => (string) $this->withCookie('summit_design_share', $plain)->get('/en/')->assertOk()->getContent();
        $this->assertStringContainsString('data-design-switch', $page(), 'the browser with the link sees the switch');
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->assertStringNotContainsString('data-design-switch', (string) $this->get('/en/')->getContent(), 'others do not');

        $this->travel(7)->days();
        $this->travel(2)->minutes();
        $this->assertStringNotContainsString('data-design-switch', $page(), 'the cookie ends with the link');
        $late = $this->get($link['url']);
        $this->assertNull(collect($late->headers->getCookies())->first(fn (Cookie $c) => $c->getName() === 'summit_design_share'), 'an expired link sets nothing');
    }

    public function test_revoking_ends_every_share_link(): void
    {
        config(['design.switcher' => false]);
        app(DesignSettings::class)->fill(['switcher' => 'link'])->save();
        $link = DesignManager::createShareLink('en');
        $this->actingAs($this->superAdmin());
        Livewire::test(DesignPreview::class)->call('createShareLink')->assertSet('data.share_link', fn ($v) => is_string($v) && str_contains($v, 'signature='));
        $this->assertSame('settings_design.share_link', Activity::query()->latest('id')->value('description'));
        Livewire::test(DesignPreview::class)->call('revokeShareLinks');
        $this->assertSame(2, app(DesignSettings::class)->refresh()->share_version);
        auth()->logout();
        $response = $this->get($link['url']);
        $this->assertNull(collect($response->headers->getCookies())->first(fn (Cookie $c) => $c->getName() === 'summit_design_share'));
    }

    public function test_switcher_visibility_and_the_environment_winning_in_production(): void
    {
        $designs = app(DesignManager::class);
        config(['design.switcher' => true, 'design.switcher_env' => true]);
        $this->assertSame('everyone', $designs->switcherMode(), 'null setting: the environment decides, as before');

        app(DesignSettings::class)->fill(['switcher' => 'admins'])->save();
        $this->assertSame('admins', $designs->switcherMode());
        $this->assertStringNotContainsString('data-design-switch', (string) $this->get('/en/')->getContent(), 'visitors');
        $this->actingAs($this->superAdmin());
        $this->assertStringContainsString('data-design-switch', (string) $this->get('/en/')->getContent(), 'signed-in admins');

        config(['design.switcher' => false, 'design.switcher_env' => false]);
        $this->assertSame('admins', $designs->switcherMode(), 'outside production the setting may turn it on for admins');
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame('off', $designs->switcherMode(), 'SUMMIT_DESIGN_SWITCHER=false always wins in production');
        $this->assertStringNotContainsString('data-design-switch', (string) $this->get('/en/')->getContent());
    }

    public function test_the_default_design_setting_overrides_the_environment_only_when_set(): void
    {
        config(['design.default' => 1]);
        $this->assertSame(1, app(DesignManager::class)->default());
        $this->actingAs($this->superAdmin());
        Livewire::test(DesignPreview::class)->assertFormSet(['default_design' => 'environment', 'switcher' => 'environment'])
            ->fillForm(['default_design' => '2'])->call('confirmSave')->callMountedAction();
        $this->assertSame(2, app(DesignSettings::class)->refresh()->default_design);
        auth()->logout();
        $this->assertStringContainsString('data-design="2"', (string) $this->withUnencryptedCookies(['summit_qa' => '1'])->get('/en/')->getContent());
    }

    // ------------------------------------------------------------------ public site unchanged

    public function test_with_default_settings_the_public_html_equals_the_html_without_the_settings_rows(): void
    {
        $urls = ['/', '/en/', '/ar/'];
        $with = [];
        foreach ([1, 2] as $design) {
            foreach ($urls as $url) {
                $with["{$design}{$url}"] = $this->html($url, $design);
            }
        }
        // the switch shown to everyone (SUMMIT_DESIGN_SWITCHER=true) is unchanged as well
        config(['design.switcher' => true]);
        $switch = self::normalise((string) $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/')->getContent());

        DB::table('settings')->whereIn('group', ['brand', 'design'])->delete();
        DB::table('settings')->where('group', 'languages')->whereIn('name', ['enabled_locales', 'default_locale', 'language_suggestion'])->delete();
        content()->flush();

        config(['design.switcher' => false]);
        foreach ([1, 2] as $design) {
            foreach ($urls as $url) {
                $this->assertSame($with["{$design}{$url}"], $this->html($url, $design), "Design {$design} {$url}");
            }
        }
        config(['design.switcher' => true]);
        $this->assertSame($switch, self::normalise((string) $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/')->getContent()));
    }
}
