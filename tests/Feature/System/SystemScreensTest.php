<?php

namespace Tests\Feature\System;

use App\Access\RoleSync;
use App\Filament\Cms\Pages\System\ActivityLog;
use App\Filament\Cms\Pages\System\Backups;
use App\Filament\Cms\Pages\System\Health;
use App\Filament\Cms\Pages\System\SystemLabels;
use App\Models\Content\Guide;
use App\Models\User;
use App\Settings\BackupSettings;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase 7 (docs/briefs/design-2.1-and-admin-cms.md §12.4): Activity Log (list, filters, diff, CSV with the formula
 * guard, refused without activity.view), Backups (list, download with backups.export only, delete, schedule) and Health
 * (runs the checks and shows each result).
 */
final class SystemScreensTest extends TestCase
{
    use RefreshDatabase;

    private const BACKUP = '2026-11-10-22-30-00.zip';

    protected function setUp(): void
    {
        parent::setUp();
        RoleSync::run();
        $this->withoutVite();
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        parent::tearDown();
    }

    /** A staff member with exactly these permissions (a custom role: no two-factor set-up in the way). */
    private function staff(array $permissions, string $name = 'System Tester'): User
    {
        $role = Role::findOrCreate('tester_'.md5(implode(',', $permissions)), 'web');
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['is_admin' => true])->save();
        $this->grantRole($user, $role);

        return $user->refresh();
    }

    private function editor(): User
    {
        $user = User::factory()->create(['name' => 'Editor Person']);
        $user->forceFill(['is_admin' => true])->save();
        $this->grantRole($user, 'editor');

        return $user;
    }

    // ------------------------------------------------------------------ Activity Log

    public function test_the_activity_log_lists_filters_and_shows_the_diff(): void
    {
        $this->seed(ContentSeeder::class);
        $viewer = $this->staff(['activity.view']);
        $this->actingAs($viewer);
        $guide = Guide::query()->firstOrFail();
        $before = $guide->getTranslation('title', 'en');
        $guide->setTranslation('title', 'en', 'Changed for the log')->save();
        $updated = Activity::query()->where('event', 'updated')->where('subject_type', 'guide')->firstOrFail();
        $created = Activity::query()->where('event', 'created')->firstOrFail(); // the tester user

        $this->get('/admin/system/activity')->assertOk()->assertSee(__('system.activity.title'));

        Livewire::test(ActivityLog::class)
            ->assertCanSeeTableRecords([$updated, $created])
            ->filterTable('event', 'updated')
            ->assertCanSeeTableRecords([$updated])
            ->assertCanNotSeeTableRecords([$created])
            ->resetTableFilters()
            ->filterTable('subject_type', 'guide')
            ->assertCanSeeTableRecords([$updated])
            ->assertCanNotSeeTableRecords([$created])
            ->resetTableFilters()
            ->filterTable('causer', (string) $viewer->id)
            ->assertCanSeeTableRecords([$updated])
            ->resetTableFilters()
            ->filterTable('date', ['from' => now('Asia/Dubai')->addDay()->toDateString()])
            ->assertCanNotSeeTableRecords([$updated, $created])
            ->resetTableFilters()
            ->filterTable('date', ['from' => now('Asia/Dubai')->toDateString(), 'until' => now('Asia/Dubai')->toDateString()])
            ->assertCanSeeTableRecords([$updated])
            ->resetTableFilters()
            ->assertActionHidden('exportCsv')
            ->mountTableAction('changes', $updated)
            ->assertHasNoErrors();

        $rows = SystemLabels::diff($updated);
        $this->assertCount(1, $rows);
        $this->assertSame(['field' => (string) __('cms.keys.title') === 'cms.keys.title' ? 'Title' : (string) __('cms.keys.title'), 'path' => 'en', 'before' => $before, 'after' => 'Changed for the log'], $rows[0]);
        $html = view('filament.system.activity-changes', ['rows' => $rows, 'properties' => SystemLabels::properties($updated)])->render();
        $this->assertStringContainsString((string) __('system.activity.diff.before'), $html);
        $this->assertStringContainsString((string) __('system.activity.diff.after'), $html);
        $this->assertStringContainsString(e($before), $html);
        $this->assertStringContainsString('Changed for the log', $html);
        $this->assertStringNotContainsString('→', $html);
    }

    public function test_the_activity_log_is_refused_without_activity_view(): void
    {
        $this->actingAs($this->editor());

        $this->get('/admin/system/activity')->assertForbidden();
        $this->get('/admin/system/backups')->assertForbidden();
        $this->get('/admin/system/health')->assertForbidden();
    }

    public function test_the_activity_csv_is_formula_guarded_and_logged(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs($this->staff(['activity.view', 'activity.export'], '=HYPERLINK("http://evil.test","x")'));
        $guide = Guide::query()->firstOrFail();
        $guide->setTranslation('title', 'en', '@SUM(1+1)')->save();

        $page = Livewire::test(ActivityLog::class)->instance();
        $response = $page->export();
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $cells = 0;
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            foreach ($row as $cell) {
                $cells++;
                $this->assertDoesNotMatchRegularExpression('/^[=@\t\r]/', ltrim($cell, "\xEF\xBB\xBF"), 'no cell starts as a formula');
            }
        }
        $this->assertGreaterThan(14, $cells);
        $this->assertSame(1, Activity::query()->where('description', 'activity.export')->count());

        $this->actingAs($this->staff(['activity.view'], 'No Export'));
        $this->expectException(HttpException::class);
        Livewire::test(ActivityLog::class)->instance()->export();
    }

    // ------------------------------------------------------------------ Backups

    public function test_the_backups_screen_lists_and_downloads_a_backup_with_export_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/'.self::BACKUP, 'PK fixture');
        Storage::disk('local')->put('backups/notes.txt', 'not a backup');

        $this->actingAs($this->staff(['backups.view']));
        $this->get('/admin/system/backups')->assertOk()->assertSee(self::BACKUP)->assertDontSee('notes.txt');
        Livewire::test(Backups::class)
            ->assertSee(self::BACKUP)
            ->assertTableActionHidden('download', self::BACKUP)
            ->assertTableActionHidden('delete', self::BACKUP)
            ->assertActionHidden('runBackup');
        try {
            Backups::download(self::BACKUP);
            $this->fail('download without backups.export');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->actingAs($this->staff(['backups.view', 'backups.export'], 'Exporter'));
        Livewire::test(Backups::class)
            ->assertTableActionVisible('download', self::BACKUP)
            ->assertTableActionHidden('delete', self::BACKUP)
            ->callTableAction('download', self::BACKUP)
            ->assertFileDownloaded(self::BACKUP);
        $response = Backups::download(self::BACKUP);
        ob_start();
        $response->sendContent();
        $this->assertSame('PK fixture', ob_get_clean());
        try {
            Backups::download('../../.env');
            $this->fail('only listed backups can be downloaded');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertSame(2, Activity::query()->where('description', 'backups.download')->count());
    }

    public function test_a_backup_is_deleted_with_backups_delete_and_the_schedule_is_saved(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/'.self::BACKUP, 'PK fixture');
        $this->actingAs($this->staff(['backups.view', 'backups.delete', 'backups.create']));

        Livewire::test(Backups::class)
            ->callTableAction('delete', self::BACKUP)
            ->assertNotified((string) __('system.backups.deleted'));
        Storage::disk('local')->assertMissing('backups/'.self::BACKUP);

        Livewire::test(Backups::class)
            ->callAction('schedule', ['enabled' => false, 'time' => '04:00'])
            ->assertHasNoActionErrors();
        $settings = app(BackupSettings::class)->refresh();
        $this->assertFalse($settings->enabled);
        $this->assertSame('04:00', $settings->time);
        $this->assertSame(1, Activity::query()->where('description', 'backups.schedule')->count());
    }

    // ------------------------------------------------------------------ Health

    public function test_the_health_screen_runs_the_checks_and_shows_each_result(): void
    {
        Storage::fake('local');
        $this->actingAs($this->staff(['health.view']));

        $page = Livewire::test(Health::class);
        foreach (Health::CHECKS as $check) {
            $page->assertSee(__('system.health.check.'.$check));
        }
        $rows = collect(Health::rows())->keyBy('key');
        $this->assertSame(Health::CHECKS, $rows->keys()->all());
        $this->assertSame('ok', $rows['database']['status']);
        $this->assertSame('ok', $rows['cache']['status']);
        $this->assertSame('ok', $rows['queue']['status'], 'phpunit runs the queue synchronously');
        $this->assertSame('failed', $rows['schedule']['status']);
        $this->assertSame('warning', $rows['mail']['status'], 'the array mailer sends nothing');
        $this->assertSame('warning', $rows['backup']['status']);
        $this->assertSame((string) __('system.health.message.backup_none'), $rows['backup']['message']);
        $page->assertSee((string) __('system.health.message.backup_none'));

        $this->artisan('health:schedule-check-heartbeat')->assertSuccessful();
        Storage::disk('local')->put('backups/'.self::BACKUP, 'PK fixture');
        $page->callAction('runChecks')->assertNotified((string) __('system.health.ran'));
        $rows = collect(Health::rows())->keyBy('key');
        $this->assertSame('ok', $rows['schedule']['status']);
        $this->assertSame('ok', $rows['backup']['status']);
        $page->assertSee((string) __('system.health.status.ok'));
    }
}
