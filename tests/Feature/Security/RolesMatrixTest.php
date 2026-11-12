<?php

namespace Tests\Feature\Security;

use App\Access\RoleSync;
use App\Access\Screens;
use App\Filament\Cms\Support\CmsAccess;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The permission matrix for every role on every screen (docs/briefs/design-2.1-and-admin-cms.md §12.1, §14.2 "Roles"):
 * each built-in role opens exactly the screens its permissions allow and is refused (403) everywhere else, and the
 * brief's own sentences hold (admin: no users, roles, settings or system screens; editor: no publish by default; case
 * handler: requests and read-only reports; viewer: dashboard and reports only). A custom role made from the matrix
 * works the same way.
 */
final class RolesMatrixTest extends TestCase
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

    /** @return array<string, string> screen => the URL of its main page */
    private function urls(): array
    {
        return [
            'dashboard' => '/admin',
            'reports' => '/admin/reports',
            'requests' => '/admin/requests',
            'pages' => '/admin/pages',
            'practice_areas' => \App\Filament\Resources\PracticeAreas\PracticeAreaResource::getUrl('index'),
            'guides' => \App\Filament\Resources\Guides\GuideResource::getUrl('index'),
            'guide_categories' => \App\Filament\Resources\GuideCategories\GuideCategoryResource::getUrl('index'),
            'case_studies' => \App\Filament\Resources\CaseStudies\CaseStudyResource::getUrl('index'),
            'faqs' => \App\Filament\Resources\Faqs\FaqResource::getUrl('index'),
            'faq_groups' => \App\Filament\Resources\FaqGroups\FaqGroupResource::getUrl('index'),
            'team' => \App\Filament\Resources\Team\TeamResource::getUrl('index'),
            'situations' => \App\Filament\Resources\Situations\SituationResource::getUrl('index'),
            'document_samples' => \App\Filament\Resources\DocumentSamples\DocumentSampleResource::getUrl('index'),
            'associated_sites' => \App\Filament\Resources\AssociatedSites\AssociatedSiteResource::getUrl('index'),
            'approval_report' => '/admin/approval-report',
            'navigation' => \App\Filament\Resources\Navigation\NavigationResource::getUrl('index'),
            'media' => \App\Filament\Resources\MediaLibrary\MediaLibraryResource::getUrl('index'),
            'interface_text' => \App\Filament\Resources\InterfaceText\InterfaceTextResource::getUrl('index'),
            'seo' => '/admin/seo',
            'redirects' => \App\Filament\Resources\Redirects\RedirectResource::getUrl('index'),
            'calculators' => \App\Filament\Resources\Calculators\CalculatorResource::getUrl('index'),
            'settings_general' => '/admin/settings/contact',
            'settings_hours' => '/admin/settings/office-hours',
            'settings_whatsapp' => '/admin/settings/whatsapp',
            'settings_languages' => '/admin/settings/languages',
            'settings_forms' => '/admin/settings/forms',
            'settings_notifications' => '/admin/settings/notifications',
            'settings_brand' => '/admin/settings/brand',
            'settings_design' => '/admin/settings/design-preview',
            'users' => '/admin/system/users',
            'roles' => '/admin/system/roles',
            'activity' => '/admin/system/activity',
            'trash' => '/admin/trash',
            'backups' => '/admin/system/backups',
            'health' => '/admin/system/health',
        ];
    }

    private function staff(string $role): User
    {
        return $this->grantRole(User::factory()->create(), $role);
    }

    public function test_every_screen_has_a_url_in_this_test(): void
    {
        $this->assertSame([], array_values(array_diff(array_keys(Screens::all()), array_keys($this->urls()))));
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return array_combine(Screens::SYSTEM_ROLES, array_map(fn ($r) => [$r], Screens::SYSTEM_ROLES));
    }

    /** @dataProvider roles */
    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_each_role_opens_exactly_its_screens(string $role): void
    {
        $user = $this->staff($role);
        $granted = $role === Screens::SUPER_ADMIN ? Screens::permissions() : Screens::roles()[$role];
        $failures = [];
        foreach ($this->urls() as $screen => $url) {
            $allowed = in_array("{$screen}.view", $granted, true);
            $status = $this->actingAs($user)->get($url)->getStatusCode();
            $ok = $allowed ? $status === 200 : $status === 403;
            if (! $ok) {
                $failures[] = "{$role} {$screen} {$url}: ".($allowed ? 'expected 200' : 'expected 403').", got {$status}";
            }
        }

        $this->assertSame([], $failures);
    }

    public function test_the_briefs_role_sentences_hold(): void
    {
        $admin = $this->staff(Screens::ADMIN);
        foreach (['users', 'roles', 'activity', 'backups', 'health', 'settings_general', 'settings_design', 'calculators'] as $screen) {
            $this->assertFalse(CmsAccess::can($screen, 'view', $admin), "admin {$screen}");
        }
        foreach (['pages', 'guides', 'requests', 'reports', 'navigation', 'media', 'seo'] as $screen) {
            $this->assertTrue(CmsAccess::can($screen, 'view', $admin), "admin {$screen}");
        }
        $this->assertTrue(CmsAccess::can('guides', 'publish', $admin));
        $this->assertFalse(CmsAccess::can('guides', 'force_delete', $admin));

        $editor = $this->staff(Screens::EDITOR);
        $this->assertTrue(CmsAccess::can('guides', 'update', $editor));
        $this->assertTrue(CmsAccess::can('media', 'create', $editor));
        $this->assertTrue(CmsAccess::can('interface_text', 'update', $editor));
        $this->assertFalse(CmsAccess::can('guides', 'publish', $editor));
        $this->assertFalse(CmsAccess::can('requests', 'view', $editor));

        $handler = $this->staff(Screens::CASE_HANDLER);
        $this->assertTrue(CmsAccess::can('requests', 'update', $handler));
        $this->assertTrue(CmsAccess::can('reports', 'view', $handler));
        $this->assertFalse(CmsAccess::can('reports', 'export', $handler));
        $this->assertFalse(CmsAccess::can('pages', 'view', $handler));

        $viewer = $this->staff(Screens::VIEWER);
        $this->assertTrue(CmsAccess::can('dashboard', 'view', $viewer));
        $this->assertTrue(CmsAccess::can('reports', 'view', $viewer));
        $this->assertTrue(CmsAccess::readOnly($viewer));
        foreach (array_keys(Screens::all()) as $screen) {
            if (! in_array($screen, ['dashboard', 'reports'], true)) {
                $this->assertFalse(CmsAccess::can($screen, 'view', $viewer), "viewer {$screen}");
            }
        }

        $super = $this->staff(Screens::SUPER_ADMIN);
        foreach (Screens::permissions() as $permission) {
            [$screen, $ability] = explode('.', $permission);
            $this->assertTrue(CmsAccess::can($screen, $ability, $super), $permission);
        }
    }

    public function test_publish_and_permanent_delete_follow_the_matrix_on_the_screens(): void
    {
        $url = \App\Filament\Resources\Guides\GuideResource::getUrl('edit', ['record' => 'G01']);
        $editor = $this->actingAs($this->staff(Screens::EDITOR))->get($url)->assertOk()->getContent();
        $this->assertStringNotContainsString('cmsPublish', $editor);
        $admin = $this->actingAs($this->staff(Screens::ADMIN))->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('cmsPublish', $admin);
    }

    public function test_a_custom_role_from_the_matrix_grants_exactly_what_is_ticked(): void
    {
        $role = Role::create(['name' => 'faq_keeper', 'guard_name' => 'web']);
        $role->syncPermissions(['faqs.view', 'faqs.update', 'dashboard.view']);
        $user = $this->staff('faq_keeper');

        $this->actingAs($user)->get($this->urls()['faqs'])->assertOk();
        $this->actingAs($user)->get($this->urls()['guides'])->assertForbidden();
        $this->assertFalse(CmsAccess::can('faqs', 'publish', $user));
    }

    public function test_the_roles_screen_saves_a_matrix_and_protects_the_super_admin(): void
    {
        $this->actingAs($this->staff(Screens::SUPER_ADMIN));
        Livewire::test(\App\Filament\Resources\Roles\Pages\CreateRole::class)
            ->fillForm(['name' => 'content_lead', 'matrix' => ['guides' => ['view', 'update', 'publish'], 'dashboard' => ['view']]])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing(
            ['guides.view', 'guides.update', 'guides.publish', 'dashboard.view'],
            Role::findByName('content_lead', 'web')->permissions->pluck('name')->all(),
        );

        $super = Role::findByName(Screens::SUPER_ADMIN, 'web');
        $this->get(\App\Filament\Resources\Roles\RoleResource::getUrl('edit', ['record' => $super]))->assertForbidden();
        $this->assertFalse(\App\Filament\Resources\Roles\RoleResource::canDelete(Role::findByName(Screens::EDITOR, 'web')));
    }

    public function test_users_cannot_lock_themselves_out_and_one_super_admin_remains(): void
    {
        $me = $this->staff(Screens::SUPER_ADMIN);
        $this->actingAs($me);

        Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)->callTableAction('deactivate', $me);
        $this->assertNull($me->fresh()->deactivated_at);

        $other = $this->staff(Screens::EDITOR);
        Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)->callTableAction('deactivate', $other);
        $this->assertNotNull($other->fresh()->deactivated_at);
        $this->actingAs($other->fresh())->get('/admin')->assertForbidden();
    }

    public function test_case_handlers_see_only_their_requests_when_the_setting_says_so(): void
    {
        $handler = $this->staff(Screens::CASE_HANDLER);
        $settings = app(\App\Settings\RequestSettings::class);
        $settings->case_handler_scope = 'assigned';
        $settings->save();

        $mine = new \App\Models\Enquiry;
        $mine->forceFill(['reference' => 'SLC-261112-AAAAA1', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'locale' => 'en', 'status' => 'new', 'name' => 'Mine', 'email' => 'm@example.test', 'summary' => 'x', 'consent_at' => now(), 'consent_text' => 'x', 'ip_hash' => str_repeat('a', 64), 'assignee_id' => $handler->id])->save();
        $theirs = $mine->replicate()->forceFill(['reference' => 'SLC-261112-AAAAA2', 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Theirs', 'assignee_id' => null]);
        $theirs->save();

        $this->actingAs($handler)->get("/admin/requests/{$mine->id}")->assertOk();
        $this->actingAs($handler)->get("/admin/requests/{$theirs->id}")->assertNotFound();
    }
}
