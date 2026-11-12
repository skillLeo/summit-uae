<?php

namespace Tests\Feature\System;

use App\Content\Cms\Editing\Publisher;
use App\Models\Content\Guide;
use App\Models\Enquiry;
use App\Models\User;
use App\Requests\Workflow;
use App\Support\ActivityLogging;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Phase 7 (docs/briefs/design-2.1-and-admin-cms.md §1.4, §12.2): every content change with only the changed fields,
 * requests with their workflow fields only, users without secrets, sign-ins without the address typed, and an import
 * that does not flood the log.
 */
final class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function staff(string $name = 'Staff One'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['is_admin' => true])->save();
        $this->grantRole($user, 'editor');

        return $user;
    }

    public function test_the_content_import_writes_no_activity(): void
    {
        $this->seed(ContentSeeder::class);
        $this->artisan('summit:content-import')->assertSuccessful();

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_a_content_change_logs_only_the_changed_fields_with_the_user(): void
    {
        $this->seed(ContentSeeder::class);
        $user = $this->staff();
        $this->actingAs($user);
        $guide = Guide::query()->firstOrFail();
        $oldTitle = $guide->getTranslation('title', 'en');

        $guide->setTranslation('title', 'en', 'A changed title')->save();

        $activity = Activity::query()->where('subject_type', 'guide')->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('admin', $activity->log_name);
        $this->assertSame((string) $user->id, (string) $activity->causer_id);
        $this->assertSame(['title'], array_keys($activity->attribute_changes['attributes']));
        $this->assertSame(['title'], array_keys($activity->attribute_changes['old']));
        $new = json_decode($activity->attribute_changes['attributes']['title'], true);
        $old = json_decode($activity->attribute_changes['old']['title'], true);
        $this->assertSame('A changed title', $new['en']);
        $this->assertSame($oldTitle, $old['en']);
        unset($new['en'], $old['en']);
        $this->assertSame($old, $new, 'the other languages are unchanged');
        $this->assertNotEmpty($activity->getProperty('label'));
    }

    public function test_a_draft_save_keeps_only_the_draft_fields_that_differ(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs($this->staff());
        $guide = Guide::query()->firstOrFail();
        $data = Publisher::workingCopy($guide);
        $data['title']['ar'] = 'عنوان جديد';

        Publisher::saveDraft($guide, $data);

        $activity = Activity::query()->where('subject_type', 'guide')->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame(['title.ar' => 'عنوان جديد'], $activity->attribute_changes['attributes']['draft']);
        $this->assertArrayHasKey('title.ar', $activity->attribute_changes['old']['draft']);
        $this->assertCount(1, $activity->attribute_changes['old']['draft']);
    }

    public function test_trash_and_restore_are_logged_without_copying_the_record(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs($this->staff());
        $guide = Guide::query()->firstOrFail();

        $guide->delete();
        $guide->restore();

        $deleted = Activity::query()->where('event', 'deleted')->firstOrFail();
        $restored = Activity::query()->where('event', 'restored')->firstOrFail();
        $this->assertSame(['deleted_at'], array_keys($deleted->attribute_changes['attributes']));
        $this->assertNotNull($deleted->attribute_changes['attributes']['deleted_at']);
        $this->assertSame(['deleted_at'], array_keys($restored->attribute_changes['attributes']));
        $this->assertSame(0, Activity::query()->where('event', 'updated')->where('subject_type', 'guide')->count());
    }

    public function test_a_request_status_change_logs_no_personal_data(): void
    {
        $staff = $this->staff();
        $enquiry = new Enquiry;
        $enquiry->forceFill([
            'reference' => Enquiry::newReference(),
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
            'status' => Enquiry::STATUS_NEW,
            'name' => 'Zelda Privateperson',
            'email' => 'zelda.private@example.test',
            'phone' => '+971500009999',
            'matter_type' => 'S06',
            'summary' => 'Unpaid gratuity at Acme Secret Trading.',
            'deadline_details' => 'Hearing on the ninth.',
            'consent_at' => now(),
            'consent_text' => 'I agree.',
            'ip_hash' => Enquiry::hashIp('127.0.0.1'),
        ])->save();

        Workflow::make()->assign($enquiry, $staff->id, $staff);
        Workflow::make()->setStatus($enquiry->refresh(), Enquiry::STATUS_IN_REVIEW, $staff);
        Workflow::make()->setStatus($enquiry->refresh(), Enquiry::STATUS_CLOSED, $staff, 'engaged');

        $rows = Activity::query()->where('subject_type', $enquiry->getMorphClass())->get();
        $this->assertGreaterThanOrEqual(3, $rows->count());
        $this->assertTrue($rows->contains(fn (Activity $a) => ($a->attribute_changes['attributes']['status'] ?? null) === Enquiry::STATUS_CLOSED));
        foreach (Activity::query()->get() as $row) {
            $json = json_encode($row->getAttributes(), JSON_UNESCAPED_UNICODE);
            foreach (['Zelda', 'zelda.private@example.test', '500009999', 'Acme Secret', 'ninth', $enquiry->ip_hash, 'I agree'] as $secret) {
                $this->assertStringNotContainsString($secret, $json);
            }
        }
        foreach ($rows as $row) {
            $this->assertSame($enquiry->reference, $row->getProperty('reference'));
            $fields = array_keys([...($row->attribute_changes['attributes'] ?? []), ...($row->attribute_changes['old'] ?? [])]);
            $this->assertSame([], array_diff($fields, Enquiry::LOGGED_FIELDS));
        }
    }

    public function test_demo_requests_are_not_logged_when_they_are_created(): void
    {
        $enquiry = new Enquiry;
        $enquiry->forceFill([
            'reference' => Enquiry::newReference(), 'idempotency_key' => (string) Str::uuid(), 'locale' => 'en',
            'name' => 'Sample Client 001', 'summary' => 'Sample.', 'consent_at' => now(), 'consent_text' => 'x',
            'ip_hash' => 'x', 'is_demo' => true,
        ])->save();

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_a_user_change_never_logs_the_password_or_two_factor_secrets(): void
    {
        $admin = $this->staff('Admin Person');
        $this->actingAs($admin);
        $user = User::factory()->create(['name' => 'Old Name', 'password' => 'first-Password-123']);

        $user->forceFill([
            'name' => 'New Name',
            'password' => 'second-Password-456',
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
            'app_authentication_recovery_codes' => ['code-one', 'code-two'],
            'remember_token' => 'remember-me-token',
        ])->save();
        $user->forceFill(['app_authentication_secret' => 'ANOTHERSECRET234'])->save();

        $rows = Activity::query()->where('subject_type', $user->getMorphClass())->where('subject_id', (string) $user->id)->get();
        $updated = $rows->where('event', 'updated');
        $this->assertCount(1, $updated, 'a change of a secret alone writes nothing');
        $this->assertSame(['name' => 'New Name'], $updated->first()->attribute_changes['attributes']);
        $this->assertSame(['name' => 'Old Name'], $updated->first()->attribute_changes['old']);
        foreach (Activity::query()->get() as $row) {
            $json = json_encode($row->getAttributes());
            foreach (['Password-', 'JBSWY3DPEHPK3PXP', 'ANOTHERSECRET', 'code-one', 'remember-me-token', $user->password, '"password"'] as $secret) {
                $this->assertStringNotContainsString($secret, $json);
            }
        }
    }

    public function test_secret_looking_keys_are_masked_whatever_writes_them(): void
    {
        activity('admin')->withProperties(['api_token' => 'abc123', 'nested' => ['password' => 'p', 'rows' => 3]])->log('test');

        $row = Activity::query()->latest('id')->firstOrFail();
        $this->assertSame(ActivityLogging::MASK, $row->getProperty('api_token'));
        $this->assertSame(ActivityLogging::MASK, $row->getProperty('nested.password'));
        $this->assertSame(3, $row->getProperty('nested.rows'));
    }

    public function test_a_sign_in_is_logged_and_sets_the_last_login_time(): void
    {
        $user = $this->staff();
        $this->assertNull($user->last_login_at);
        Activity::query()->delete();

        Auth::guard('web')->login($user);

        $activity = Activity::query()->where('event', 'auth.login')->firstOrFail();
        $this->assertSame((string) $user->id, (string) $activity->causer_id);
        $this->assertSame((string) $user->id, (string) $activity->subject_id);
        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertSame(0, Activity::query()->where('event', 'updated')->count(), 'the sign-in time is not a user change');

        Auth::guard('web')->logout();
        $this->assertSame(1, Activity::query()->where('event', 'auth.logout')->count());
    }

    public function test_a_failed_sign_in_logs_neither_the_address_nor_the_password(): void
    {
        $user = $this->staff();
        $user->forceFill(['email' => 'known.staff@example.test'])->save();
        Activity::query()->delete();

        $this->assertFalse(Auth::guard('web')->attempt(['email' => 'known.staff@example.test', 'password' => 'Wrong-Guess-1']));
        $this->assertFalse(Auth::guard('web')->attempt(['email' => 'nobody.here@example.test', 'password' => 'Wrong-Guess-2']));

        $rows = Activity::query()->where('event', 'auth.failed')->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $json = json_encode($row->getAttributes());
            $this->assertStringNotContainsString('known.staff', $json);
            $this->assertStringNotContainsString('nobody.here', $json);
            $this->assertStringNotContainsString('Wrong-Guess', $json);
            $this->assertNull($row->causer_id);
            $this->assertNull($row->subject_id);
        }
    }
}
