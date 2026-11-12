<?php

namespace Tests\Feature\Security;

use App\Access\RoleSync;
use App\Access\Screens;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Security (docs/briefs/design-2.1-and-admin-cms.md §12.2, §14.2 "Security"): two-factor authentication is required for
 * the super admin and the admin and optional for the other roles; a password alone never signs a two-factor user in;
 * deactivated, role-less and demo users cannot use the panel; admin passwords follow the rules.
 */
final class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        RoleSync::run();
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    private function staff(string $role, bool $twoFactor): User
    {
        // a new user per request: the session must not carry the previous user's password hash (AuthenticateSession)
        $this->flushSession();
        $user = User::factory()->create(['password' => 'correct-horse-12']);
        $user->forceFill(['is_admin' => true])->save();
        $user->syncRoles([$role]);
        if ($twoFactor) {
            $user->forceFill(['app_authentication_secret' => app(AppAuthentication::class)->generateSecret()])->save();
        }

        return $user->refresh();
    }

    public function test_super_admin_and_admin_must_set_up_two_factor_before_any_screen(): void
    {
        $setUp = Filament::getSetUpRequiredMultiFactorAuthenticationUrl();
        foreach ([Screens::SUPER_ADMIN, Screens::ADMIN] as $role) {
            $user = $this->staff($role, false);
            $this->actingAs($user)->get('/admin')->assertRedirect($setUp);
            $this->actingAs($user)->get('/admin/requests')->assertRedirect($setUp);
            $this->assertTrue($user->requiresTwoFactor());
        }
    }

    public function test_set_up_later_lets_them_in_for_this_sign_in_only(): void
    {
        $setUp = Filament::getSetUpRequiredMultiFactorAuthenticationUrl();
        $user = $this->staff(Screens::SUPER_ADMIN, false);

        $this->actingAs($user)->get($setUp)->assertOk()->assertSee(__('admin_shell.two_factor_setup.later'));
        Livewire::actingAs($user)->test(\App\Filament\Pages\Auth\SetUpTwoFactor::class)
            ->callAction('later')
            ->assertRedirect();
        $this->assertTrue(session(\App\Filament\Http\Middleware\EnsureTwoFactor::SKIPPED));
        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/requests')->assertOk();

        // the next sign-in asks again
        event(new \Illuminate\Auth\Events\Login('web', $user, false));
        $this->actingAs($user)->get('/admin')->assertRedirect($setUp);
    }

    public function test_with_two_factor_set_up_they_get_in(): void
    {
        $this->actingAs($this->staff(Screens::SUPER_ADMIN, true))->get('/admin')->assertOk();
        $this->actingAs($this->staff(Screens::ADMIN, true))->get('/admin')->assertOk();
    }

    public function test_other_roles_may_work_without_two_factor(): void
    {
        foreach ([Screens::EDITOR, Screens::CASE_HANDLER, Screens::VIEWER] as $role) {
            $user = $this->staff($role, false);
            $this->assertFalse($user->requiresTwoFactor());
            $this->actingAs($user)->get('/admin')->assertOk();
        }
    }

    public function test_a_password_alone_does_not_sign_in_a_two_factor_user(): void
    {
        $user = $this->staff(Screens::SUPER_ADMIN, true);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'correct-horse-12'])
            ->call('authenticate')
            ->assertSee('one-time-code', false);

        $this->assertGuest();
    }

    public function test_deactivated_role_less_and_demo_users_cannot_use_the_panel(): void
    {
        $deactivated = $this->staff(Screens::EDITOR, false);
        $deactivated->forceFill(['deactivated_at' => now()])->save();
        $this->actingAs($deactivated)->get('/admin')->assertForbidden();

        $this->flushSession();
        $roleless = User::factory()->create();
        $roleless->forceFill(['is_admin' => true])->save();
        $this->actingAs($roleless)->get('/admin')->assertForbidden();

        $demo = $this->staff(Screens::EDITOR, false);
        $demo->forceFill(['is_demo' => true])->save();
        $this->actingAs($demo)->get('/admin')->assertForbidden();
    }

    public function test_admin_passwords_need_twelve_characters_with_letters_and_digits(): void
    {
        $rule = \Illuminate\Validation\Rules\Password::default();
        $this->assertTrue(validator(['p' => 'short1'], ['p' => [$rule]])->fails());
        $this->assertTrue(validator(['p' => 'onlylettersssss'], ['p' => [$rule]])->fails());
        $this->assertFalse(validator(['p' => 'letters-and-1234'], ['p' => [$rule]])->fails());
    }

    public function test_the_login_is_rate_limited(): void
    {
        $user = $this->staff(Screens::EDITOR, false);
        $component = Livewire::test(Login::class);
        foreach (range(1, 5) as $i) {
            $component->fillForm(['email' => $user->email, 'password' => 'wrong-password-1'])->call('authenticate');
        }
        $component->fillForm(['email' => $user->email, 'password' => 'correct-horse-12'])->call('authenticate');

        $this->assertGuest();
    }
}
