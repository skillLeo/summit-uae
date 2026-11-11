<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * POST /{locale}/enquiries (docs/enquiries.md). Interface strings are seeded with recognisable
 * values ("en:form.name.error") so every assertion proves which approved key was used.
 */
final class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    private const UI_KEYS = [
        'form.name.error', 'form.phone.error', 'form.email.error', 'form.contact_group.error',
        'form.summary.error', 'form.deadline_date.error', 'form.consent.error', 'form.consent.label',
        'form.attachment.error_type', 'form.attachment.error_size', 'form.attachment.error_count',
        'form.attachment.error_rejected',
        'form.state.summary', 'form.state.success', 'form.state.failure', 'form.state.rate_limited',
    ];

    private const REFERENCE_PATTERN = '/^SLC-\d{6}-[0-9ABCDEFGHJKMNPQRSTVWXYZ]{6}$/';

    private bool $turnstilePasses = true;

    private bool $turnstileReachable = true;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        // Approved interface strings are group "ui" of the CMS interface text (App\Models\Content\UiString).
        DB::table('ui_strings')->insert(array_map(fn (string $key) => [
            'group' => 'ui',
            'key' => $key,
            'text' => json_encode(['en' => "en:{$key}", 'ar' => "ar:{$key}", 'ru' => "ru:{$key}"]),
            'created_at' => $now,
            'updated_at' => $now,
        ], self::UI_KEYS));
        app(ContentRepository::class)->flush();

        config([
            'summit.turnstile.enabled' => true,
            'summit.features.attachments_enabled' => false,
            'summit.enquiries.inbox' => 'inbox@summit.test',
        ]);

        Mail::fake();
        Http::preventStrayRequests();
        Http::fake([
            config('summit.turnstile.verify_url') => fn (HttpRequest $request) => $this->turnstileReachable
                ? Http::response(['success' => $this->turnstilePasses, 'error-codes' => $this->turnstilePasses ? [] : ['invalid-input-response']])
                : Http::failedConnection()($request),
        ]);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $without
     * @return array<string, mixed>
     */
    private function payload(array $overrides = [], array $without = []): array
    {
        return Arr::except(array_replace([
            'idempotency_key' => (string) Str::uuid(),
            'name' => 'Jane Example',
            'phone' => '+971 50 123 4567',
            'email' => 'jane@example.com',
            'preferred_language' => 'en',
            'matter_type' => 'S04',
            'summary' => "My business partner withheld the agreed profit share.\nThe agreement was signed in 2024.",
            'deadline_date' => '2026-12-01',
            'deadline_details' => 'First hearing before the Dubai Courts',
            'enquiry_consent' => '1',
            'website' => '',
            'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ], $overrides), $without);
    }

    /** As the front end posts it: form fields via fetch, asking for JSON. */
    private function submit(array $payload, string $locale = 'en'): TestResponse
    {
        return $this->post("/{$locale}/enquiries", $payload, ['Accept' => 'application/json']);
    }

    /** An HTML form POST without JavaScript. */
    private function submitWithoutJavaScript(array $payload, string $locale = 'en'): TestResponse
    {
        return $this->post("/{$locale}/enquiries", $payload, ['Accept' => 'text/html']);
    }

    /** @param  array<string, list<string>>  $errors */
    private function assertInvalid(TestResponse $response, array $errors, string $locale = 'en'): void
    {
        $response->assertStatus(422)->assertExactJson([
            'status' => 'invalid',
            'message' => "{$locale}:form.state.summary",
            'errors' => $errors,
        ]);
        $this->assertSame(0, Enquiry::count());
        Mail::assertNothingQueued();
    }

    /** A confirmed failure: the failure message only (no field errors), nothing new stored or queued. */
    private function assertFailed(TestResponse $response, int $status = 422, int $alreadyAccepted = 0): void
    {
        $response->assertStatus($status)->assertExactJson(['status' => 'failed', 'message' => 'en:form.state.failure']);
        $this->assertSame($alreadyAccepted, Enquiry::count());
        Mail::assertQueuedCount($alreadyAccepted);
    }

    private function assertAccepted(TestResponse $response): Enquiry
    {
        $response->assertCreated()->assertExactJson([
            'status' => 'received',
            'reference' => (string) $response->json('reference'),
            'message' => 'en:form.state.success',
        ]);

        return Enquiry::query()->where('reference', $response->json('reference'))->sole();
    }

    private function captureLogs(): TestHandler
    {
        config([
            'logging.default' => 'capture',
            'logging.channels.capture' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        ]);

        return Log::channel('capture')->getLogger()->getHandlers()[0];
    }

    // ------------------------------------------------------------------ acceptance

    public function test_a_valid_enquiry_is_stored_then_acknowledged_with_201_and_queued_for_the_firm(): void
    {
        $payload = $this->payload();

        $response = $this->submit($payload);

        $enquiry = $this->assertAccepted($response);
        $this->assertMatchesRegularExpression(self::REFERENCE_PATTERN, $enquiry->reference);
        $this->assertSame($payload['idempotency_key'], $enquiry->idempotency_key);
        $this->assertSame('en', $enquiry->locale);
        $this->assertSame(Enquiry::STATUS_NEW, $enquiry->status);
        $this->assertSame('Jane Example', $enquiry->name);
        $this->assertSame('+971501234567', $enquiry->phone);
        $this->assertSame('jane@example.com', $enquiry->email);
        $this->assertSame('en', $enquiry->preferred_language);
        $this->assertSame('S04', $enquiry->matter_type);
        $this->assertSame($payload['summary'], $enquiry->summary);
        $this->assertSame('2026-12-01', $enquiry->deadline_date?->format('Y-m-d'));
        $this->assertSame('First hearing before the Dubai Courts', $enquiry->deadline_details);
        $this->assertSame('en:form.consent.label', $enquiry->consent_text);
        $this->assertNotNull($enquiry->consent_at);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(EnquiryReceived::class, fn (EnquiryReceived $mail) => $mail->hasTo('inbox@summit.test') && $mail->enquiry->is($enquiry));
    }

    public function test_a_phone_number_alone_is_enough(): void
    {
        $enquiry = $this->assertAccepted($this->submit($this->payload(['email' => ''])));

        $this->assertSame('+971501234567', $enquiry->phone);
        $this->assertNull($enquiry->email);
    }

    public function test_an_email_address_alone_is_enough(): void
    {
        $enquiry = $this->assertAccepted($this->submit($this->payload(without: ['phone'])));

        $this->assertNull($enquiry->phone);
        $this->assertSame('jane@example.com', $enquiry->email);
    }

    public function test_a_malformed_email_is_an_error_even_when_the_phone_is_valid(): void
    {
        $this->assertInvalid($this->submit($this->payload(['email' => 'jane@example..com'])), [
            'email' => ['en:form.email.error'],
        ]);
    }

    public function test_neither_phone_nor_email_is_reported_on_the_contact_group(): void
    {
        $this->assertInvalid($this->submit($this->payload(['phone' => '', 'email' => ''])), [
            'contact' => ['en:form.contact_group.error'],
        ]);
    }

    public function test_the_prefilled_country_code_alone_counts_as_no_phone(): void
    {
        $this->assertInvalid($this->submit($this->payload(['phone' => '+971 ', 'email' => ''])), [
            'contact' => ['en:form.contact_group.error'],
        ]);

        $enquiry = $this->assertAccepted($this->submit($this->payload(['phone' => '+971 '])));
        $this->assertNull($enquiry->phone);
    }

    /** @return array<string, array{string, string}> */
    public static function validPhones(): array
    {
        return [
            'spaces' => ['+971 52 466 6191', '+971524666191'],
            'double zero, parentheses, hyphen' => ['00971 (52) 466-6191', '+971524666191'],
            'dots' => ['+44.20.7946.0958', '+442079460958'],
            'arabic-indic digits' => ['+٩٧١ ٥٢ ٤٦٦ ٦١٩١', '+971524666191'],
            'direction marks from rtl text' => ["\u{200E}+971 52 466 6191\u{200F}", '+971524666191'],
            'seven digits' => ['+1234567', '+1234567'],
            'fifteen digits' => ['+123456789012345', '+123456789012345'],
        ];
    }

    #[DataProvider('validPhones')]
    public function test_international_phone_numbers_are_accepted_and_stored_canonically(string $phone, string $stored): void
    {
        $enquiry = $this->assertAccepted($this->submit($this->payload(['phone' => $phone, 'email' => ''])));

        $this->assertSame($stored, $enquiry->phone);
    }

    /** @return array<string, array{string}> */
    public static function invalidPhones(): array
    {
        return [
            'no country code' => ['052 466 6191'],
            'no plus or double zero' => ['971524666191'],
            'six digits' => ['+971 524'],
            'sixteen digits' => ['+1234567890123456'],
            'extension text' => ['+971 52 466 6191 ext. 2'],
            'slashes' => ['+971/52/4666191'],
            'plus inside' => ['971+524666191'],
        ];
    }

    #[DataProvider('invalidPhones')]
    public function test_a_phone_without_the_international_format_is_an_error(string $phone): void
    {
        $this->assertInvalid($this->submit($this->payload(['phone' => $phone])), [
            'phone' => ['en:form.phone.error'],
        ]);
    }

    public function test_missing_name_summary_and_consent_are_each_reported(): void
    {
        $this->assertInvalid($this->submit($this->payload(['name' => '   '], without: ['summary', 'enquiry_consent'])), [
            'name' => ['en:form.name.error'],
            'summary' => ['en:form.summary.error'],
            'enquiry_consent' => ['en:form.consent.error'],
        ]);
    }

    public function test_a_declined_consent_is_an_error(): void
    {
        $this->assertInvalid($this->submit($this->payload(['enquiry_consent' => '0'])), [
            'enquiry_consent' => ['en:form.consent.error'],
        ]);
    }

    public function test_over_long_text_is_an_error(): void
    {
        $this->assertInvalid($this->submit($this->payload([
            'name' => str_repeat('a', 121),
            'summary' => str_repeat('b', 5001),
            'deadline_details' => str_repeat('c', 501),
        ])), [
            'name' => ['en:form.name.error'],
            'summary' => ['en:form.summary.error'],
            'deadline_details' => ['en:form.state.summary'],
        ]);
    }

    public function test_browser_line_breaks_count_as_one_character(): void
    {
        // 1,000 lines of "line" + CRLF is 6,000 bytes as submitted but 4,999 characters in the textarea.
        $enquiry = $this->assertAccepted($this->submit($this->payload(['summary' => str_repeat("line\r\n", 1000)])));

        $this->assertSame(rtrim(str_repeat("line\n", 1000)), $enquiry->summary);
    }

    public function test_a_past_deadline_is_accepted(): void
    {
        $enquiry = $this->assertAccepted($this->submit($this->payload(['deadline_date' => '2019-03-14'])));

        $this->assertSame('2019-03-14', $enquiry->deadline_date?->format('Y-m-d'));
    }

    /** @return array<string, array{string}> */
    public static function malformedDates(): array
    {
        return [
            'day first' => ['14/03/2019'],
            'impossible day' => ['2026-02-30'],
            'missing zero padding' => ['2026-3-4'],
            'words' => ['next Tuesday'],
            'datetime' => ['2026-12-01T10:00'],
        ];
    }

    #[DataProvider('malformedDates')]
    public function test_a_malformed_deadline_date_is_an_error(string $date): void
    {
        $this->assertInvalid($this->submit($this->payload(['deadline_date' => $date])), [
            'deadline_date' => ['en:form.deadline_date.error'],
        ]);
    }

    public function test_unknown_select_values_are_silently_treated_as_not_chosen(): void
    {
        $enquiry = $this->assertAccepted($this->submit($this->payload(['preferred_language' => 'de', 'matter_type' => 'S99'])));

        $this->assertNull($enquiry->preferred_language);
        $this->assertNull($enquiry->matter_type);
    }

    public function test_every_approved_select_value_is_stored(): void
    {
        foreach (['ar', 'en', 'ru'] as $language) {
            $this->assertSame($language, $this->assertAccepted($this->submit($this->payload(['preferred_language' => $language])))->preferred_language);
        }
        $this->travel(11)->minutes(); // stay under the rate limit
        foreach (['S01', 'S11', 'other', 'unsure'] as $matter) {
            $this->assertSame($matter, $this->assertAccepted($this->submit($this->payload(['matter_type' => $matter])))->matter_type);
        }
    }

    // ------------------------------------------------------------------ language

    public function test_messages_and_the_stored_locale_follow_the_url_language(): void
    {
        $this->assertInvalid($this->submit($this->payload(without: ['summary']), 'ar'), [
            'summary' => ['ar:form.summary.error'],
        ], 'ar');

        $response = $this->submit($this->payload(), 'ru');
        $response->assertCreated()->assertJsonPath('message', 'ru:form.state.success');
        $enquiry = Enquiry::sole();
        $this->assertSame('ru', $enquiry->locale);
        $this->assertSame('ru:form.consent.label', $enquiry->consent_text);
    }

    // ------------------------------------------------------------------ idempotency

    public function test_replaying_an_accepted_key_returns_the_original_reference_without_a_new_row(): void
    {
        $payload = $this->payload();
        $first = $this->submit($payload);
        $first->assertCreated();

        // The retry carries an already-redeemed Turnstile token: it must not be verified again.
        $this->turnstilePasses = false;
        $replay = $this->submit(['cf-turnstile-response' => 'ANOTHER.TOKEN'] + $payload);

        $replay->assertOk()->assertExactJson([
            'status' => 'received',
            'reference' => $first->json('reference'),
            'message' => 'en:form.state.success',
        ]);
        $this->assertSame(1, Enquiry::count());
        Mail::assertQueuedCount(1);
        Http::assertSentCount(1);
    }

    public function test_a_key_reused_for_a_different_enquiry_is_refused(): void
    {
        $payload = $this->payload();
        $this->submit($payload)->assertCreated();

        $this->assertFailed($this->submit(['summary' => 'A completely different matter.'] + $payload), alreadyAccepted: 1);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function badKeys(): array
    {
        return [
            'missing' => [['idempotency_key' => null]],
            'not a uuid' => [['idempotency_key' => 'enquiry-1']],
            'array' => [['idempotency_key' => ['f47ac10b-58cc-4372-a567-0e02b2c3d479']]],
        ];
    }

    /** @param  array<string, mixed>  $override */
    #[DataProvider('badKeys')]
    public function test_a_missing_or_malformed_idempotency_key_is_a_failure_without_field_errors(array $override): void
    {
        $payload = array_filter($this->payload($override), fn ($value) => $value !== null);

        $this->assertFailed($this->submit($payload));
        Http::assertNothingSent();
    }

    public function test_an_upper_case_key_is_the_same_key(): void
    {
        $payload = $this->payload(['idempotency_key' => 'F47AC10B-58CC-4372-A567-0E02B2C3D479']);
        $first = $this->submit($payload);

        $this->submit(['idempotency_key' => strtolower($payload['idempotency_key'])] + $payload)
            ->assertOk()->assertJsonPath('reference', $first->json('reference'));
        $this->assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', Enquiry::sole()->idempotency_key);
    }

    // ------------------------------------------------------------------ abuse protection

    public function test_the_sixth_accepted_submission_within_ten_minutes_is_rate_limited(): void
    {
        $first = $this->payload();
        $this->submit($first)->assertCreated();
        for ($i = 2; $i <= 5; $i++) {
            $this->submit($this->payload())->assertCreated();
        }

        // A replay of an accepted key is not a new submission and is not counted.
        $this->submit($first)->assertOk();

        $limited = $this->submit($this->payload());
        $limited->assertStatus(429)
            ->assertExactJson(['status' => 'rate_limited', 'message' => 'en:form.state.rate_limited'])
            ->assertHeader('Retry-After');
        $this->assertSame(5, Enquiry::count());

        $this->travel(601)->seconds();
        $this->submit($this->payload())->assertCreated();
    }

    public function test_rejected_submissions_do_not_use_up_the_rate_limit(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->submit($this->payload(['email' => 'not-an-email']))->assertStatus(422);
        }

        $this->submit($this->payload())->assertCreated();
    }

    public function test_a_filled_honeypot_is_stored_as_spam_and_answered_exactly_like_a_success(): void
    {
        $enquiry = $this->assertAccepted($this->submit($this->payload(['website' => 'https://cheap-links.example'])));

        $this->assertSame(Enquiry::STATUS_SPAM, $enquiry->status);
        Mail::assertNothingQueued();
    }

    public function test_a_failed_turnstile_check_is_refused_with_the_failure_message_only(): void
    {
        $this->turnstilePasses = false;

        $this->assertFailed($this->submit($this->payload()));
    }

    public function test_a_missing_turnstile_token_is_refused_without_calling_cloudflare(): void
    {
        $this->assertFailed($this->submit($this->payload(without: ['cf-turnstile-response'])));
        Http::assertNothingSent();
    }

    public function test_an_unreachable_turnstile_fails_closed(): void
    {
        $this->turnstileReachable = false;

        $this->assertFailed($this->submit($this->payload()));
    }

    public function test_turnstile_receives_the_secret_token_ip_and_idempotency_key_as_a_form(): void
    {
        $payload = $this->payload();
        $this->submit($payload)->assertCreated();

        Http::assertSent(fn (HttpRequest $request) => $request->url() === config('summit.turnstile.verify_url')
            && $request->isForm()
            && $request['secret'] === config('summit.turnstile.secret_key')
            && $request['response'] === 'XXXX.DUMMY.TOKEN.XXXX'
            && $request['remoteip'] === '127.0.0.1'
            && $request['idempotency_key'] === $payload['idempotency_key']);
    }

    public function test_turnstile_is_skipped_when_disabled(): void
    {
        config(['summit.turnstile.enabled' => false]);

        $this->assertAccepted($this->submit($this->payload(without: ['cf-turnstile-response'])));
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ attachments

    public function test_an_uploaded_file_is_ignored_and_never_written_while_attachments_are_disabled(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('notes.txt', 'Not even an accepted type.');

        $enquiry = $this->assertAccepted($this->submit($this->payload(['attachment' => $file])));

        $this->assertNull($enquiry->attachment_path);
        $this->assertNull($enquiry->attachment_mime);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_with_attachments_enabled_a_valid_document_is_stored_on_the_private_disk(): void
    {
        config(['summit.features.attachments_enabled' => true]);
        Storage::fake('local');
        $pdf = UploadedFile::fake()->createWithContent('Passport Jane.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

        $enquiry = $this->assertAccepted($this->submit($this->payload(['attachment' => $pdf])));

        $this->assertSame('application/pdf', $enquiry->attachment_mime);
        $this->assertSame($pdf->getSize(), $enquiry->attachment_size);
        $this->assertMatchesRegularExpression('#^enquiries/'.$enquiry->reference.'/[A-Za-z0-9]{40}\.pdf$#', (string) $enquiry->attachment_path);
        Storage::disk('local')->assertExists((string) $enquiry->attachment_path);
    }

    /** @return array<string, array{string, string}> */
    public static function rejectedAttachments(): array
    {
        return [
            'type sniffed from the content' => ['type', 'error_type'],
            'larger than 10 MB' => ['size', 'error_size'],
            'two documents' => ['count', 'error_count'],
            'empty upload' => ['empty', 'error_rejected'],
        ];
    }

    #[DataProvider('rejectedAttachments')]
    public function test_with_attachments_enabled_a_bad_document_is_reported(string $case, string $error): void
    {
        config(['summit.features.attachments_enabled' => true]);
        Storage::fake('local');
        $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n");
        $attachment = match ($case) {
            // A text file renamed to .pdf and declared as a PDF is still a text file.
            'type' => UploadedFile::fake()->createWithContent('contract.pdf', 'plain text, not a document'),
            'size' => UploadedFile::fake()->create('scan.pdf', 10_000, 'application/pdf'),
            'count' => [$pdf('one.pdf'), $pdf('two.pdf')],
            'empty' => UploadedFile::fake()->createWithContent('empty.pdf', ''),
        };

        $this->assertInvalid($this->submit($this->payload(['attachment' => $attachment])), [
            'attachment' => ["en:form.attachment.{$error}"],
        ]);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ------------------------------------------------------------------ without JavaScript

    public function test_without_javascript_validation_errors_redirect_back_to_the_form_with_old_input(): void
    {
        $payload = $this->payload(without: ['name']);

        $response = $this->submitWithoutJavaScript($payload);

        $response->assertStatus(303);
        $location = (string) $response->headers->get('Location');
        $this->assertSame('/en/', parse_url($location, PHP_URL_PATH));
        $this->assertSame('consultation-form', parse_url($location, PHP_URL_FRAGMENT));
        $response->assertSessionHasErrors(['name' => 'en:form.name.error']);
        $response->assertSessionHas('enquiry_status', 'invalid');
        $response->assertSessionHasInput('summary', $payload['summary']);
        $response->assertSessionHasInput('phone', $payload['phone']);
        $this->assertNull(session()->getOldInput('idempotency_key'));
        $this->assertSame(0, Enquiry::count());
    }

    public function test_without_javascript_a_success_redirects_to_the_form_with_the_reference(): void
    {
        $response = $this->submitWithoutJavaScript($this->payload(), 'ar');

        $response->assertStatus(303);
        $this->assertSame('/ar/', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
        $reference = Enquiry::sole()->reference;
        $response->assertSessionHas('enquiry_reference', $reference);
        $response->assertSessionHas('enquiry_status', 'received');
    }

    public function test_without_javascript_a_failure_redirects_back_with_the_values_kept(): void
    {
        $this->turnstilePasses = false;

        $response = $this->submitWithoutJavaScript($this->payload());

        $response->assertStatus(303);
        $response->assertSessionHas('enquiry_status', 'failed');
        $response->assertSessionHasInput('name', 'Jane Example');
        $response->assertSessionMissing('enquiry_reference');
        $this->assertNull(session()->getOldInput('idempotency_key'));
    }

    // ------------------------------------------------------------------ privacy

    public function test_the_summary_and_deadline_details_are_encrypted_at_rest(): void
    {
        $payload = $this->payload();
        $this->submit($payload)->assertCreated();

        $raw = DB::table('enquiries')->first();
        $this->assertNotSame($payload['summary'], $raw->summary);
        $this->assertStringNotContainsString('profit share', $raw->summary);
        $this->assertSame($payload['summary'], Crypt::decryptString($raw->summary));
        $this->assertNotSame($payload['deadline_details'], $raw->deadline_details);
        $this->assertSame($payload['deadline_details'], Crypt::decryptString($raw->deadline_details));
    }

    public function test_the_ip_address_is_stored_only_as_a_keyed_hash(): void
    {
        $this->submit($this->payload())->assertCreated();

        $raw = (array) DB::table('enquiries')->first();
        $this->assertSame(hash_hmac('sha256', '127.0.0.1', (string) config('app.key')), $raw['ip_hash']);
        $this->assertStringNotContainsString('127.0.0.1', implode('|', array_map('strval', $raw)));
    }

    public function test_the_mail_subject_carries_only_the_reference_and_matter_code(): void
    {
        $payload = $this->payload();
        $reference = $this->submit($payload)->json('reference');

        Mail::assertQueued(EnquiryReceived::class, function (EnquiryReceived $mail) use ($reference, $payload) {
            $mail->assertHasSubject("{$reference} · S04");
            foreach (['Jane', 'jane@example.com', '501234567', '123 4567', 'profit'] as $personal) {
                $this->assertStringNotContainsString($personal, $mail->envelope()->subject);
            }
            // The body lists the submitted fields for the firm.
            $mail->assertSeeInText('name: Jane Example');
            $mail->assertSeeInText('phone: +971501234567');
            $mail->assertSeeInText('email: jane@example.com');
            $mail->assertSeeInText('matter_type: S04');
            $mail->assertSeeInText('deadline_date: 2026-12-01');
            $mail->assertSeeInText($payload['summary']);

            return true;
        });
    }

    public function test_without_a_matter_type_the_subject_is_the_reference_alone(): void
    {
        $reference = $this->submit($this->payload(['matter_type' => '']))->json('reference');

        Mail::assertQueued(EnquiryReceived::class, fn (EnquiryReceived $mail) => $mail->hasSubject($reference));
    }

    public function test_logs_carry_only_the_reference_and_status(): void
    {
        $logs = $this->captureLogs();

        $reference = $this->submit($this->payload())->json('reference');

        $this->assertCount(1, $logs->getRecords());
        $this->assertSame(['reference' => $reference, 'status' => 'new'], $logs->getRecords()[0]['context']);
    }

    public function test_a_storage_failure_is_a_500_that_leaks_no_details_to_the_visitor_or_the_log(): void
    {
        $logs = $this->captureLogs();
        // The insert now fails with a QueryException whose message embeds every bound value.
        Schema::table('enquiries', fn ($table) => $table->dropColumn('consent_text'));

        $response = $this->submit($this->payload());

        $this->assertFailed($response, 500);
        $logged = json_encode(array_map(fn ($record) => [$record['message'], $record['context']], $logs->getRecords()));
        $this->assertStringContainsString('Enquiry could not be stored', (string) $logged);
        foreach (['Jane', 'jane@example.com', '501234567', 'profit share', 'Dubai Courts', '127.0.0.1', 'insert into'] as $detail) {
            $this->assertStringNotContainsString($detail, (string) $logged);
            $this->assertStringNotContainsString($detail, (string) $response->getContent());
        }
    }

    public function test_a_mail_failure_does_not_undo_an_accepted_enquiry(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Mail transport unavailable'));

        $this->assertAccepted($this->submit($this->payload()));
        $this->assertSame(1, Enquiry::count());
    }

    // ------------------------------------------------------------------ reference

    public function test_the_reference_is_dated_in_uae_time_and_not_sequential(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 21:30:00', 'UTC')); // 01:30 on 10 November in Dubai

        $references = [];
        for ($i = 1; $i <= 5; $i++) {
            $references[] = $this->submit($this->payload())->json('reference');
        }

        foreach ($references as $reference) {
            $this->assertStringStartsWith('SLC-261110-', $reference);
            $this->assertMatchesRegularExpression(self::REFERENCE_PATTERN, $reference);
        }
        $this->assertCount(5, array_unique($references));
    }
}
