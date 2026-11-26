<?php

namespace Tests\Feature\Cms\Admin;

use App\Access\RoleSync;
use App\Filament\Cms\Pages\Settings\Brand;
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
use Tests\TestCase;

/**
 * Phase 7 settings screens (docs/briefs/design-2.1-and-admin-cms.md §9.4, §12.3): access per screen key, the save
 * confirmation and its one activity entry with only the changed fields, Languages, Forms and Consent, Notifications,
 * Brand (the accent contrast check) and the public HTML with the defaults.
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
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        RoleSync::run();
        $this->withoutVite();
        Filament::setCurrentPanel('admin');
        // The form and the requests screens are off since client PDF item 7 (WhatsApp only); this file tests their kept code.
        $this->consultationFormOn();
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

    private function html(string $url): string
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

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
        foreach (['/en/', '/ar/', '/ru/', '/ru/contact/'] as $url) {
            $this->html($url);
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

        $this->get('/ru/')->assertNotFound();
        $this->get('/ru/contact/')->assertNotFound();
        $html = $this->html('/en/');
        $this->assertStringNotContainsString('hreflang="ru"', $html, 'the menus and hreflang links leave Russian out');
        $this->assertStringContainsString('hreflang="ar"', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="https://www.summitlegaluae.com/ar/">', $html);
        $this->assertStringNotContainsString('/ru/', (string) $this->get('/sitemap.xml')->getContent());

        // the default language must be on
        Livewire::test(Languages::class)->fillForm(['enabled_locales' => ['en'], 'default_locale' => 'ru'])->call('confirmSave')
            ->assertHasFormErrors(['default_locale'])->assertActionNotMounted();

        // back to all three: reachable again
        Livewire::test(Languages::class)->fillForm(['enabled_locales' => ['en', 'ar', 'ru'], 'default_locale' => 'en', 'language_suggestion' => 'environment'])
            ->call('confirmSave')->callMountedAction();
        $this->html('/ru/');
        $this->assertStringContainsString('hreflang="ru"', $this->html('/en/'));
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

    // ------------------------------------------------------------------ public site unchanged

    public function test_with_default_settings_the_public_html_equals_the_html_without_the_settings_rows(): void
    {
        // "/" is a 301 to /en/ since client PDF item 29 (no page): its Location is compared instead.
        $urls = ['/en/', '/ar/', '/ru/contact/'];
        $with = [];
        foreach ($urls as $url) {
            $with[$url] = $this->html($url);
        }
        $root = $this->get('/')->assertStatus(301)->headers->get('Location');

        DB::table('settings')->where('group', 'brand')->delete();
        DB::table('settings')->where('group', 'languages')->whereIn('name', ['enabled_locales', 'default_locale', 'language_suggestion'])->delete();
        content()->flush();

        foreach ($urls as $url) {
            $this->assertSame($with[$url], $this->html($url), $url);
        }
        $this->assertSame($root, $this->get('/')->assertStatus(301)->headers->get('Location'));
    }
}
