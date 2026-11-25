<?php

namespace Tests\Feature\Cms\Admin;

use App\Access\RoleSync;
use App\Access\Screens;
use App\Filament\Cms\Pages\Settings\Integrations;
use App\Models\User;
use App\Settings\IntegrationSettings;
use App\Support\Analytics;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Settings, Integrations (docs/briefs/design2-only-black-header-ga4.md C2; App\Filament\Cms\Pages\Settings\Integrations):
 * the Google Analytics measurement ID, super admin only, validated against ^G-[A-Z0-9]{6,12}$, empty by default (the
 * environment's GA4_MEASUREMENT_ID decides), saved after the confirmation with one activity log entry.
 */
final class IntegrationsSettingsTest extends TestCase
{
    use RefreshDatabase;

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

    /** @param list<string> $permissions */
    private function custom(array $permissions): User
    {
        $role = Role::findOrCreate('integrations_tester', 'web');
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->grantRole(User::factory()->create(), $role);
    }

    public function test_super_admin_only_by_default(): void
    {
        $this->assertSame(['view', 'update'], Screens::all()['settings_integrations']['abilities']);
        foreach (Screens::roles() as $role => $permissions) {
            $this->assertEmpty(preg_grep('/^settings_integrations\./', $permissions), "{$role} has no Integrations permission");
            $this->actingAs($this->grantRole(User::factory()->create(), $role));
            $this->assertFalse(Integrations::canAccess(), $role);
            $this->get(Integrations::getUrl())->assertForbidden();
        }

        $this->actingAs($this->grantRole(User::factory()->create(), Screens::SUPER_ADMIN));
        $this->get(Integrations::getUrl())->assertOk()->assertSee('Google Analytics measurement ID');
        $this->assertTrue(Livewire::test(Integrations::class)->assertOk()->instance()->canEdit());
    }

    public function test_the_id_is_empty_by_default_validated_confirmed_and_logged(): void
    {
        $this->assertNull(app(IntegrationSettings::class)->ga4_measurement_id, 'empty until the owner confirms the ID');
        config(['services.ga4.id' => null]);
        $this->actingAs($this->grantRole(User::factory()->create(), Screens::SUPER_ADMIN));

        foreach (['G-12345', 'g-ydernr1r8d', 'UA-1234567-1', 'G-YDERNR1R8D!', 'GTM-ABC1234'] as $invalid) {
            Livewire::test(Integrations::class)->fillForm(['ga4_measurement_id' => $invalid])->call('confirmSave')
                ->assertHasFormErrors(['ga4_measurement_id'])->assertActionNotMounted();
        }
        $this->assertNull(app(IntegrationSettings::class)->refresh()->ga4_measurement_id);

        $page = Livewire::test(Integrations::class)->fillForm(['ga4_measurement_id' => 'G-YDERNR1R8D'])->call('confirmSave')
            ->assertHasNoErrors()->assertActionMounted('saveSettings')
            ->assertMountedActionModalSee(['Google Analytics measurement ID', 'legal or data impact']);
        $this->assertNull(app(IntegrationSettings::class)->refresh()->ga4_measurement_id, 'nothing is written before the confirmation');
        $page->callMountedAction();

        $this->assertSame('G-YDERNR1R8D', app(IntegrationSettings::class)->refresh()->ga4_measurement_id);
        $this->assertSame('G-YDERNR1R8D', Analytics::measurementId(), 'the public site reads it at once');
        $log = Activity::query()->where('log_name', 'admin')->where('description', 'settings_integrations.update')->get();
        $this->assertCount(1, $log);
        $this->assertSame('integrations', $log[0]->properties['group']);
        $this->assertSame(['ga4_measurement_id' => ['old' => null, 'new' => 'G-YDERNR1R8D']], $log[0]->properties['changes']);

        // Cleared: stored as null, the environment decides again.
        Livewire::test(Integrations::class)->assertFormSet(['ga4_measurement_id' => 'G-YDERNR1R8D'])
            ->fillForm(['ga4_measurement_id' => ''])->call('confirmSave')->assertActionMounted('saveSettings')->callMountedAction();
        $this->assertNull(app(IntegrationSettings::class)->refresh()->ga4_measurement_id);
        config(['services.ga4.id' => 'G-ENVVALUE1']);
        $this->assertSame('G-ENVVALUE1', Analytics::measurementId());
    }

    public function test_a_custom_role_given_the_screen_sees_the_id_but_cannot_change_it(): void
    {
        app(IntegrationSettings::class)->fill(['ga4_measurement_id' => 'G-OWNER1234'])->save();
        $this->actingAs($this->custom(['settings_integrations.view', 'settings_integrations.update', 'dashboard.view']));

        $this->get(Integrations::getUrl())->assertOk()->assertSee('Only a super admin can change the integrations.');
        Livewire::test(Integrations::class)->assertFormFieldIsDisabled('ga4_measurement_id')
            ->fillForm(['ga4_measurement_id' => 'G-OTHER12345'])->call('confirmSave');
        $this->assertSame('G-OWNER1234', app(IntegrationSettings::class)->refresh()->ga4_measurement_id);
    }
}
