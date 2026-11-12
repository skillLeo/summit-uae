<?php

namespace Tests\Feature\Requests;

use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Mail\EnquiryReceived;
use App\Mail\RequestOverdue;
use App\Mail\RequestsDigest;
use App\Models\Enquiry;
use App\Models\EnquiryEvent;
use App\Models\EnquiryNote;
use App\Models\User;
use App\Requests\BusinessHours;
use App\Requests\Digest;
use App\Requests\Notifier;
use App\Requests\Privacy;
use App\Requests\SpamCheck;
use App\Requests\Workflow;
use App\Settings\NotificationSettings;
use App\Settings\OfficeHoursSettings;
use App\Settings\RequestSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Phase 5 (docs/briefs/design-2.1-and-admin-cms.md §10, §14.2 "Requests"): the form's states in the DOCX order, the
 * spam rules (never notified, never counted), first_reviewed_at once, the status history, the notifications, the
 * internal target, the retention job, export, anonymise and delete, the intake context and the demo command.
 */
final class RequestsInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        Mail::fake();
        config([
            'summit.enquiries.inbox' => 'inbox@summit.test',
            'summit.turnstile.enabled' => false,
            'summit.contact_page_released' => true,
        ]);
        Http::preventStrayRequests();
        RateLimiter::clear('x');
    }

    protected function tearDown(): void
    {
        Livewire::flushState();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function admin(string $name = 'Staff One'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    /** @param  array<string, mixed>  $attributes */
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
            'phone' => '+971500000001',
            'matter_type' => 'S06',
            'summary' => 'My gratuity has not been paid.',
            'consent_at' => now(),
            'consent_text' => 'I agree.',
            'ip_hash' => Enquiry::hashIp('127.0.0.1'),
        ])->save();

        return $enquiry;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'idempotency_key' => (string) Str::uuid(),
            'name' => 'Jane Example',
            'phone' => '+971 50 123 4567',
            'email' => 'jane@example.com',
            'preferred_language' => 'en',
            'matter_type' => 'S04',
            'summary' => 'A dispute about a profit share in a Dubai company.',
            'enquiry_consent' => '1',
            'website' => '',
        ];
    }

    private function submit(array $payload, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'] + $headers)->post('/en/enquiries', $payload);
    }

    // ------------------------------------------------------------------ the public form (contract unchanged)

    public function test_field_errors_come_back_in_the_docx_order_and_nothing_is_stored(): void
    {
        $response = $this->submit(['idempotency_key' => (string) Str::uuid(), 'deadline_date' => 'not-a-date', 'summary' => '']);

        $response->assertStatus(422)->assertJsonPath('status', 'invalid');
        // DOCX: name and contact details first, then summary, deadline, (attachment), consent
        $this->assertSame(['name', 'contact', 'summary', 'deadline_date', 'enquiry_consent'], array_keys($response->json('errors')));
        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_success_is_answered_only_after_the_row_is_committed_with_its_context(): void
    {
        $response = $this->submit(
            $this->payload(['form_elapsed' => '45000', 'landing_referrer' => 'https://www.google.com/search?q=x', 'utm_source' => 'google', 'utm_campaign' => 'employment']),
            ['Referer' => url('/en/contact-us/').'/?utm_medium=cpc', 'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'],
        );

        $response->assertCreated()->assertJsonPath('status', 'received');
        $enquiry = Enquiry::query()->sole();
        $this->assertSame($response->json('reference'), $enquiry->reference);
        $this->assertNotNull($enquiry->received_at);
        $this->assertSame('/en/contact-us/', $enquiry->source_page);
        $this->assertSame('google.com', $enquiry->referrer);
        $this->assertSame(['google', 'cpc', 'employment'], [$enquiry->utm_source, $enquiry->utm_medium, $enquiry->utm_campaign]);
        $this->assertSame('mobile', $enquiry->device_class);
        $this->assertSame(1, $enquiry->design);
        $this->assertSame(0, $enquiry->spam_score);
        $privacy = DB::table('revisions')->where('revisionable_type', 'page')->where('revisionable_id', 'L01')->max('id');
        $this->assertSame($privacy, $enquiry->privacy_revision_id);
        $this->assertSame(['received'], $enquiry->events()->pluck('type')->all());
        // no User-Agent, no IP, no query string other than the UTM keys
        $this->assertStringNotContainsString('iPhone', json_encode(DB::table('enquiries')->first()));
    }

    public function test_the_pending_extras_are_kept_only_while_pending_copy_is_shown(): void
    {
        config(['design.d2.show_pending' => true]);
        $this->submit($this->payload(['context_audience' => 'business', 'context_situation' => 'S04-2', 'context_documents' => "Trade licence\nContract"]))->assertCreated();
        $this->assertSame(['audience' => 'business', 'situation' => 'S04-2', 'documents' => ['Trade licence', 'Contract']], Enquiry::query()->latest('id')->first()->extras);

        config(['design.d2.show_pending' => false]);
        $this->submit($this->payload(['context_audience' => 'business']))->assertCreated();
        $this->assertNull(Enquiry::query()->latest('id')->first()->extras);
    }

    // ------------------------------------------------------------------ spam

    public function test_every_spam_rule_scores_and_spam_is_answered_like_a_success_without_notification(): void
    {
        $cases = [
            'honeypot' => ['website' => 'http://spam.example'],
            'too_fast' => ['form_elapsed' => '900'],
            'links' => ['summary' => 'Visit https://a.example https://b.example https://c.example now'],
            'name_link' => ['name' => 'Cheap www.spam-shop.com'],
        ];
        foreach ($cases as $reason => $override) {
            $response = $this->submit($this->payload($override));
            $response->assertCreated()->assertJsonPath('status', 'received');
            $enquiry = Enquiry::query()->where('reference', $response->json('reference'))->sole();
            $this->assertSame(Enquiry::STATUS_SPAM, $enquiry->status, $reason);
            $this->assertContains($reason, $enquiry->spam_reasons, $reason);
            RateLimiter::clear('enquiries:'.Enquiry::hashIp('127.0.0.1'));
        }
        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, Enquiry::query()->counted()->count());
    }

    public function test_a_burst_from_one_connection_scores_but_is_never_spam_alone(): void
    {
        foreach (range(1, 3) as $i) {
            $this->enquiry(['ip_hash' => Enquiry::hashIp('127.0.0.1')]);
        }
        $result = SpamCheck::evaluate(['name' => 'Jane', 'summary' => 'Hello'], false, null, Enquiry::hashIp('127.0.0.1'));

        $this->assertSame(['burst'], $result['reasons']);
        $this->assertSame(40, $result['score']);
        $this->assertFalse($result['spam']);
    }

    public function test_the_spam_thresholds_come_from_the_settings(): void
    {
        $settings = app(RequestSettings::class);
        $settings->spam_max_links = 5;
        $settings->spam_min_fill_seconds = 0;
        $settings->save();

        $result = SpamCheck::evaluate(['name' => 'J', 'summary' => 'a.com b.com c.com'], false, 100, 'x');
        $this->assertSame([], $result['reasons']);
    }

    // ------------------------------------------------------------------ workflow

    public function test_first_reviewed_at_is_set_once_when_the_status_leaves_new(): void
    {
        $by = $this->admin();
        $enquiry = $this->enquiry();
        CarbonImmutable::setTestNow('2026-11-10 08:00:00');
        $this->travelTo('2026-11-10 08:00:00');

        Workflow::make()->setStatus($enquiry, Enquiry::STATUS_IN_REVIEW, $by);
        $first = $enquiry->fresh()->first_reviewed_at;
        $this->assertSame('2026-11-10 08:00:00', $first->utc()->format('Y-m-d H:i:s'));

        $this->travelTo('2026-11-11 08:00:00');
        Workflow::make()->setStatus($enquiry, Enquiry::STATUS_NEW, $by);
        Workflow::make()->setStatus($enquiry, Enquiry::STATUS_IN_REVIEW, $by);
        Workflow::make()->addNote($enquiry, $by, 'Later note');
        $this->assertEquals($first, $enquiry->fresh()->first_reviewed_at);
    }

    public function test_first_reviewed_at_is_set_by_the_first_note_too(): void
    {
        $enquiry = $this->enquiry();
        $this->travelTo('2026-11-10 06:30:00');
        Workflow::make()->addNote($enquiry, $this->admin(), 'Tried to call.');

        $fresh = $enquiry->fresh();
        $this->assertSame(Enquiry::STATUS_NEW, $fresh->status);
        $this->assertSame('2026-11-10 06:30:00', $fresh->first_reviewed_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_closing_needs_an_outcome_and_the_history_records_every_change(): void
    {
        $by = $this->admin();
        $other = $this->admin('Staff Two');
        $enquiry = $this->enquiry();

        try {
            Workflow::make()->setStatus($enquiry, Enquiry::STATUS_CLOSED, $by);
            $this->fail('Closing without an outcome must be refused.');
        } catch (InvalidArgumentException) {
            // expected
        }

        Workflow::make()->assign($enquiry, $other->id, $by);
        Workflow::make()->setStatus($enquiry, Enquiry::STATUS_IN_REVIEW, $by);
        Workflow::make()->setStatus($enquiry, Enquiry::STATUS_CLOSED, $by, 'engaged');
        $this->assertNotNull($enquiry->fresh()->closed_at);
        Workflow::make()->setStatus($enquiry, Enquiry::STATUS_IN_REVIEW, $by);
        $fresh = $enquiry->fresh();
        $this->assertNull($fresh->closed_at);
        $this->assertNull($fresh->outcome);

        $history = $enquiry->events()->get()->map(fn (EnquiryEvent $e) => [$e->type, $e->from, $e->to, $e->user_id])->all();
        $this->assertSame([
            ['assigned', null, (string) $other->id, $by->id],
            ['status', 'new', 'in_review', $by->id],
            ['status', 'in_review', 'closed', $by->id],
            ['outcome', null, 'engaged', $by->id],
            ['status', 'closed', 'in_review', $by->id],
        ], $history);
        // the timeline never holds personal data
        $this->assertStringNotContainsString('Example', json_encode(EnquiryEvent::all()));
    }

    public function test_a_note_is_encrypted_and_can_be_changed_by_its_author_for_ten_minutes_only(): void
    {
        $author = $this->admin();
        $colleague = $this->admin('Staff Two');
        $enquiry = $this->enquiry();
        $note = Workflow::make()->addNote($enquiry, $author, 'Called, no answer.', [$colleague->id]);

        $this->assertStringNotContainsString('Called', (string) DB::table('enquiry_notes')->value('body'));
        $this->assertFalse(Workflow::make()->canChangeNote($note, $colleague));
        Workflow::make()->editNote($note, $author, 'Called twice, no answer.');
        $this->assertSame('Called twice, no answer.', $note->fresh()->body);

        $this->travel(11)->minutes();
        $this->expectException(InvalidArgumentException::class);
        Workflow::make()->deleteNote($note->fresh(), $author);
    }

    // ------------------------------------------------------------------ notifications

    public function test_a_new_request_notifies_the_on_duty_list_and_the_staff_without_personal_data(): void
    {
        $staff = $this->admin();
        $settings = app(NotificationSettings::class);
        $settings->recipients = ['duty@summit.test', 'second@summit.test'];
        $settings->save();

        $reference = $this->submit($this->payload())->assertCreated()->json('reference');

        Mail::assertQueued(EnquiryReceived::class, 2);
        Mail::assertQueued(EnquiryReceived::class, fn (EnquiryReceived $mail) => $mail->hasTo('duty@summit.test') && ! $mail->details);
        $notification = DB::table('notifications')->where('notifiable_id', $staff->id)->sole();
        $this->assertStringContainsString($reference, $notification->data);
        $this->assertStringNotContainsString('Jane', $notification->data);
    }

    public function test_details_are_mailed_only_when_switched_on_and_the_mailer_really_sends(): void
    {
        $settings = app(NotificationSettings::class);
        $settings->include_details = true;
        $settings->save();

        config(['mail.default' => 'log']);
        $this->assertFalse(Notifier::includeDetails());
        config(['mail.default' => 'smtp']);
        $this->assertTrue(Notifier::includeDetails());

        $this->submit($this->payload())->assertCreated();
        Mail::assertQueued(EnquiryReceived::class, function (EnquiryReceived $mail) {
            $mail->assertSeeInText('name: Jane Example');

            return $mail->details;
        });
    }

    public function test_assigning_and_mentioning_notify_that_person_in_the_panel(): void
    {
        $by = $this->admin();
        $to = $this->admin('Staff Two');
        $enquiry = $this->enquiry();

        Workflow::make()->assign($enquiry, $to->id, $by);
        Workflow::make()->addNote($enquiry, $by, 'Please call.', [$to->id]);

        $this->assertSame(2, DB::table('notifications')->where('notifiable_id', $to->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $by->id)->count());
    }

    public function test_the_overdue_alert_goes_once_after_the_target_in_business_hours(): void
    {
        $hours = app(OfficeHoursSettings::class);
        $hours->review_target_hours = 4;
        $hours->save();
        // Monday 10 November 2026, 09:00 Dubai = 05:00 UTC
        $enquiry = $this->enquiry(['received_at' => '2026-11-10 05:00:00']);

        $this->assertSame(0, Digest::sweepOverdue(app(Notifier::class), CarbonImmutable::parse('2026-11-10 08:59:00')));
        $this->assertSame(1, Digest::sweepOverdue(app(Notifier::class), CarbonImmutable::parse('2026-11-10 09:01:00')));
        $this->assertSame(0, Digest::sweepOverdue(app(Notifier::class), CarbonImmutable::parse('2026-11-10 12:00:00')));
        Mail::assertQueued(RequestOverdue::class, 1);
        $this->assertNotNull($enquiry->fresh()->overdue_notified_at);
    }

    public function test_the_daily_digest_lists_references_only(): void
    {
        $this->enquiry(['received_at' => now()->subHours(2), 'reference' => 'SLC-261111-AAAAAA']);
        $this->enquiry(['received_at' => now()->subHours(3), 'status' => Enquiry::STATUS_SPAM]);

        $this->artisan('summit:requests-digest')->assertSuccessful();

        Mail::assertQueued(RequestsDigest::class, function (RequestsDigest $mail) {
            $mail->assertSeeInText('SLC-261111-AAAAAA');
            $mail->assertDontSeeInText('Example');

            return $mail->figures['received'] === 1 && $mail->figures['waiting'] === 1;
        });
    }

    // ------------------------------------------------------------------ business hours (the waiting time)

    public function test_business_hours_skip_evenings_weekends_and_closures(): void
    {
        $bh = new BusinessHours([1, 2, 3, 4, 5], '09:00', '18:00', ['2026-11-11']);
        $utc = fn (string $dubai) => CarbonImmutable::parse($dubai, 'Asia/Dubai')->utc();

        // Friday 17:00 to Monday 10:00 = 1 h Friday + 1 h Monday
        $this->assertSame(2.0, $bh->between($utc('2026-11-13 17:00'), $utc('2026-11-16 10:00')));
        // Tuesday 17:30 to Thursday 09:30 skips the closure on Wednesday 11 November
        $this->assertSame(1.0, $bh->between($utc('2026-11-10 17:30'), $utc('2026-11-12 09:30')));
        // received at night, reviewed before opening
        $this->assertSame(0.0, $bh->between($utc('2026-11-12 20:00'), $utc('2026-11-13 08:00')));
        // reversed interval
        $this->assertSame(0.0, $bh->between($utc('2026-11-13 10:00'), $utc('2026-11-13 09:00')));
    }

    // ------------------------------------------------------------------ privacy

    public function test_export_returns_every_request_of_the_person_and_is_logged(): void
    {
        $by = $this->admin();
        $first = $this->enquiry(['email' => 'same@example.test', 'phone' => null]);
        $second = $this->enquiry(['email' => 'same@example.test', 'phone' => '+971500000099']);
        $this->enquiry(['email' => 'other@example.test', 'phone' => '+971500000077']);
        Workflow::make()->addNote($first, $by, 'Internal');

        $data = Privacy::export($second, $by);

        $this->assertSame([$first->reference, $second->reference], array_column($data['requests'], 'reference'));
        $this->assertSame('Internal', $data['requests'][0]['notes'][0]['text']);
        $this->assertSame('UTC', $data['time_zone_of_times']);
        $this->assertTrue(Activity::query()->where('event', 'export_person')->exists());
        $this->assertSame(2, EnquiryEvent::query()->where('type', 'export')->count());
    }

    public function test_anonymise_removes_the_personal_data_and_keeps_the_figures(): void
    {
        $by = $this->admin();
        $enquiry = $this->enquiry(['deadline_details' => 'Hearing on Monday']);
        Workflow::make()->setStatus($enquiry, Enquiry::STATUS_CLOSED, $by, 'engaged');
        Workflow::make()->addNote($enquiry, $by, 'Spoke to A. Example');

        $this->assertTrue(Privacy::anonymise($enquiry->fresh(), $by));
        $fresh = $enquiry->fresh();
        $this->assertSame(Privacy::ANONYMISED_NAME, $fresh->name);
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->phone);
        $this->assertSame('', $fresh->summary);
        $this->assertNull($fresh->deadline_details);
        $this->assertSame(0, EnquiryNote::query()->count());
        $this->assertSame('engaged', $fresh->outcome);
        $this->assertNotNull($fresh->closed_at);
        $this->assertNotNull($fresh->anonymized_at);
        $this->assertFalse(Privacy::anonymise($fresh, $by));
        $this->assertTrue(Activity::query()->where('event', 'anonymise')->exists());
    }

    public function test_the_retention_job_deletes_old_spam_and_trash_and_anonymises_old_closed_requests(): void
    {
        $this->travelTo('2026-11-12 00:00:00');
        $oldSpam = $this->enquiry(['status' => Enquiry::STATUS_SPAM, 'received_at' => now()->subDays(31)]);
        $newSpam = $this->enquiry(['status' => Enquiry::STATUS_SPAM, 'received_at' => now()->subDays(5)]);
        $oldClosed = $this->enquiry(['status' => Enquiry::STATUS_CLOSED, 'outcome' => 'engaged', 'closed_at' => now()->subMonths(25)]);
        $newClosed = $this->enquiry(['status' => Enquiry::STATUS_CLOSED, 'outcome' => 'engaged', 'closed_at' => now()->subMonths(23)]);
        $trashed = $this->enquiry();
        Privacy::delete($trashed);
        DB::table('enquiries')->where('id', $trashed->id)->update(['deleted_at' => now()->subDays(40)]);

        $this->artisan('summit:requests-retention')->assertSuccessful();

        $this->assertNull(Enquiry::withTrashed()->find($oldSpam->id));
        $this->assertNotNull(Enquiry::find($newSpam->id));
        $this->assertNull(Enquiry::withTrashed()->find($trashed->id));
        $this->assertNotNull($oldClosed->fresh()->anonymized_at);
        $this->assertNull($newClosed->fresh()->anonymized_at);
    }

    public function test_the_retention_periods_come_from_the_settings(): void
    {
        $settings = app(RequestSettings::class);
        $settings->retention_closed_months = 6;
        $settings->save();
        $closed = $this->enquiry(['status' => Enquiry::STATUS_CLOSED, 'outcome' => 'no_reply', 'closed_at' => now()->subMonths(7)]);

        $this->assertSame(1, Privacy::retention()['anonymised']);
        $this->assertNotNull($closed->fresh()->anonymized_at);
    }

    // ------------------------------------------------------------------ inbox screens

    public function test_the_inbox_tabs_count_and_filter_the_views(): void
    {
        $me = $this->admin();
        $this->enquiry();
        $this->enquiry(['status' => Enquiry::STATUS_IN_REVIEW, 'assignee_id' => $me->id]);
        $this->enquiry(['status' => Enquiry::STATUS_CLOSED, 'outcome' => 'referred']);
        $spam = $this->enquiry(['status' => Enquiry::STATUS_SPAM]);
        $this->actingAs($me);
        Filament::setCurrentPanel('admin');

        $list = Livewire::test(ListEnquiries::class);
        $list->assertCanSeeTableRecords(Enquiry::where('status', 'new')->get())->assertCanNotSeeTableRecords([$spam]);
        $list->set('activeTab', 'mine')->assertCountTableRecords(1);
        $list->set('activeTab', 'spam')->assertCanSeeTableRecords([$spam]);
        $list->set('activeTab', 'all')->assertCountTableRecords(4);

        $html = $this->get('/admin/requests')->assertOk()->getContent();
        $this->assertStringNotContainsString('fi-font-mono', $html);
        $this->assertStringContainsString('data-inbox="list"', $html);
    }

    public function test_the_detail_has_the_keyboard_shortcuts_and_the_actions_work(): void
    {
        $me = $this->admin();
        $older = $this->enquiry(['received_at' => now()->subHours(5)]);
        $enquiry = $this->enquiry(['received_at' => now()->subHour(), 'deadline_date' => now()->addDays(3)->format('Y-m-d')]);
        $this->actingAs($me);
        Filament::setCurrentPanel('admin');
        session()->put(ListEnquiries::SESSION_TAB, 'new');

        $html = $this->get("/admin/requests/{$enquiry->id}")->assertOk()->getContent();
        foreach (['j', 'k', 'a', 's', 'n', 'e'] as $key) {
            $this->assertStringContainsString('data-shortcut="'.$key.'"', $html, $key);
        }
        $this->assertStringContainsString('id="slc-shortcuts"', $html);
        $this->assertStringContainsString('data-deadline="soon"', $html);
        $this->assertStringContainsString('https://wa.me/971500000001', $html);
        $this->assertStringContainsString("/admin/requests/{$older->id}", $html);

        Livewire::test(ViewEnquiry::class, ['record' => $enquiry->getRouteKey()])
            ->callAction('assign', data: ['assignee_id' => (string) $me->id])
            ->assertHasNoActionErrors()
            ->callAction('status', data: ['status' => 'closed'])
            ->assertHasActionErrors(['outcome']);
        Livewire::test(ViewEnquiry::class, ['record' => $enquiry->getRouteKey()])
            ->callAction('status', data: ['status' => 'closed', 'outcome' => 'not_engaged'])
            ->assertHasNoActionErrors();

        $fresh = $enquiry->fresh();
        $this->assertSame($me->id, $fresh->assignee_id);
        $this->assertSame('not_engaged', $fresh->outcome);
    }

    public function test_the_inbox_exports_csv_without_the_summary(): void
    {
        $this->actingAs($this->admin());
        $this->enquiry(['name' => '=HYPERLINK("x")']);
        Filament::setCurrentPanel('admin');

        $response = Livewire::test(ListEnquiries::class)->callAction('exportCsv');
        $response->assertFileDownloaded();
        $this->assertTrue(Activity::query()->where('event', 'export')->exists());

        $csv = \App\Requests\RequestExport::csv(\App\Requests\RequestExport::headings(), \App\Requests\RequestExport::rows(Enquiry::all()));
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('+971500000001', $csv);
        $this->assertStringNotContainsString('gratuity', $csv);
    }

    public function test_csv_and_xlsx_share_one_formula_guard_and_keep_phone_numbers(): void
    {
        // visitors type the name (the summary is never exported): a leading = @ + - must arrive as text in both formats
        $this->enquiry(['name' => '=HYPERLINK("https://evil.example","x")', 'phone' => '+971 52 466 6191', 'email' => '@SUM(A1)@example.test']);
        $rows = \App\Requests\RequestExport::rows(Enquiry::all());
        $headings = \App\Requests\RequestExport::headings();

        $csv = \App\Requests\RequestExport::csv($headings, $rows);
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"https://evil.example\"\",\"\"x\"\")\"", $csv);
        $this->assertStringContainsString(",\"+971 52 466 6191\",", $csv);
        $this->assertStringContainsString(",'@SUM(A1)@example.test,", $csv);

        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        \App\Requests\RequestExport::xlsx($path, $headings, $rows);
        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open($path);
        $cells = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                if ($i === 2) {
                    $cells = $row->toArray();
                }
            }
        }
        $reader->close();
        @unlink($path);
        $this->assertSame("'=HYPERLINK(\"https://evil.example\",\"x\")", $cells[7]);
        $this->assertSame('+971 52 466 6191', $cells[8]);
        $this->assertSame("'@SUM(A1)@example.test", $cells[9]);
        $this->assertSame("'-x", \App\Requests\RequestExport::cell('-x'));
        $this->assertSame(12.5, \App\Requests\RequestExport::cell(12.5));
    }

    // ------------------------------------------------------------------ demo data

    public function test_demo_requests_are_marked_and_removed_with_one_command(): void
    {
        $real = $this->enquiry();
        $this->artisan('summit:demo-requests', ['--days' => 30, '--count' => 25])->assertSuccessful();

        $this->assertSame(25, Enquiry::query()->where('is_demo', true)->count());
        $this->assertSame(0, Enquiry::query()->where('is_demo', true)->where('name', 'not like', 'Sample Client %')->count());
        $this->assertSame(0, Enquiry::query()->where('is_demo', true)->whereNotNull('email')->where('email', 'not like', '%@example.invalid')->count());
        $this->actingAs($this->admin());
        $this->get('/admin/requests')->assertSee('data-demo-banner', false);

        $this->artisan('summit:demo-requests', ['--purge' => true])->assertSuccessful();
        $this->assertSame(0, Enquiry::query()->withTrashed()->where('is_demo', true)->count());
        $this->assertSame(0, User::query()->where('is_demo', true)->count());
        $this->assertNotNull($real->fresh());
        $this->get('/admin/requests')->assertDontSee('data-demo-banner', false);
    }

    public function test_demo_requests_are_refused_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('summit:demo-requests')->assertFailed();
        $this->assertSame(0, Enquiry::query()->count());
    }
}
