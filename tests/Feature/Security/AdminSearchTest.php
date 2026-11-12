<?php

namespace Tests\Feature\Security;

use App\Access\RoleSync;
use App\Access\Screens;
use App\Filament\Livewire\AdminSearch;
use App\Models\Enquiry;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cmd/Ctrl+K search (docs/briefs/design-2.1-and-admin-cms.md §9.1, §13): requests by reference, name, phone and e-mail;
 * content by its title in English, Arabic and Russian; only what the user may open.
 */
final class AdminSearchTest extends TestCase
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

    private function request(): Enquiry
    {
        $e = new Enquiry;
        $e->forceFill(['reference' => 'SLC-261112-QWERTY', 'idempotency_key' => (string) Str::uuid(), 'locale' => 'en', 'status' => 'new', 'name' => 'Findable Person', 'email' => 'findable@example.test', 'phone' => '+971501112233', 'summary' => 'x', 'consent_at' => now(), 'consent_text' => 'x', 'ip_hash' => str_repeat('a', 64)])->save();

        return $e;
    }

    public function test_requests_are_found_by_reference_name_phone_and_email(): void
    {
        $this->request();
        $this->actingAs($this->grantRole(User::factory()->create(), Screens::SUPER_ADMIN));

        foreach (['QWERTY', 'Findable', '501112233', 'findable@'] as $q) {
            $titles = array_column(Livewire::test(AdminSearch::class)->set('q', $q)->instance()->results(), 'title');
            $this->assertContains('SLC-261112-QWERTY', $titles, $q);
        }
    }

    public function test_content_is_found_in_all_three_languages(): void
    {
        $this->actingAs($this->grantRole(User::factory()->create(), Screens::SUPER_ADMIN));
        $guide = \App\Models\Content\Guide::query()->findOrFail('G01');
        foreach (['en', 'ar', 'ru'] as $locale) {
            $title = \App\Content\Cms\Editing\Registry::label($guide, $locale);
            $words = mb_substr($title, 0, 8);
            $urls = array_column(Livewire::test(AdminSearch::class)->set('q', $words)->instance()->results(), 'url');
            $this->assertNotEmpty(array_filter($urls, fn ($u) => str_contains($u, '/G01/')), "{$locale}: {$words}");
        }
    }

    public function test_results_respect_the_permission_matrix(): void
    {
        $this->request();
        $this->actingAs($this->grantRole(User::factory()->create(), Screens::EDITOR));

        $this->assertSame([], Livewire::test(AdminSearch::class)->set('q', 'QWERTY')->instance()->results());
    }
}
