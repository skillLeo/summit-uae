<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Services\Turnstile;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * POST /{locale}/enquiries: the consultation form's server contract (docs/enquiries.md).
 *
 * Order: field validation (StoreEnquiryRequest) → idempotency key → replay of an accepted key →
 * rate limit → Turnstile → store (a filled honeypot stores status "spam") → queue the firm's
 * notification. Success is reported only after the row is committed. Privacy: logs carry the
 * reference and status only; exception messages are never logged, because a QueryException
 * embeds the SQL bindings, i.e. the visitor's details.
 */
final class EnquiryController extends Controller
{
    use DetectsConcurrencyErrors, DetectsLostConnections;

    private const ATTACHMENT_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** Fresh references tried when one collides with an existing enquiry (≈1e9 values per day). */
    private const REFERENCE_ATTEMPTS = 3;

    public function store(StoreEnquiryRequest $request, Turnstile $turnstile): JsonResponse|RedirectResponse
    {
        $key = $request->idempotencyKey();
        if ($key === null) {
            $this->log('notice', 'Enquiry rejected: missing or malformed idempotency key', ['status' => 'failed']);

            return $this->failed($request, 422);
        }

        $attributes = $request->enquiryAttributes();
        $ipHash = Enquiry::hashIp($request->ip());
        $limiterKey = 'enquiries:'.$ipHash;

        try {
            $existing = Enquiry::query()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                return $this->replay($request, $existing, $attributes);
            }

            if (RateLimiter::tooManyAttempts($limiterKey, $this->maxAttempts())) {
                $this->log('notice', 'Enquiry rejected: rate limited', ['status' => 'rate_limited']);

                return $this->rateLimited($request, RateLimiter::availableIn($limiterKey));
            }

            if (! $turnstile->verify($request->turnstileToken(), $request->ip(), $key)) {
                $this->log('notice', 'Enquiry rejected: Turnstile verification failed', ['status' => 'failed']);

                return $this->failed($request, 422);
            }

            $enquiry = $this->persist($request, $key, $attributes, $ipHash);
            if (! $enquiry->wasRecentlyCreated) {
                // A concurrent request carrying the same key committed first.
                return $this->replay($request, $enquiry, $attributes);
            }
        } catch (Throwable $e) {
            return $this->serverError($request, $e);
        }

        // Committed. From here on nothing may turn the acceptance into an error response.
        rescue(fn () => RateLimiter::hit($limiterKey, $this->decaySeconds()), report: false);
        $this->log('info', 'Enquiry received', ['reference' => $enquiry->reference, 'status' => $enquiry->status]);
        if ($enquiry->status === Enquiry::STATUS_NEW) {
            $this->notify($enquiry);
        }

        return $this->received($request, $enquiry, 201);
    }

    // ------------------------------------------------------------------ storage

    /** @param  array<string, mixed>  $attributes  StoreEnquiryRequest::enquiryAttributes() */
    private function persist(StoreEnquiryRequest $request, string $key, array $attributes, string $ipHash): Enquiry
    {
        $status = $request->honeypotFilled() ? Enquiry::STATUS_SPAM : Enquiry::STATUS_NEW;
        $attributes += [
            'idempotency_key' => $key,
            'locale' => app()->getLocale(),
            'status' => $status,
            'consent_at' => now(),
            'consent_text' => ui(Enquiry::CONSENT_LABEL_KEY),
            'ip_hash' => $ipHash,
        ];
        // A document is kept only for a genuine enquiry, and only while attachments are enabled.
        $file = $status === Enquiry::STATUS_NEW ? $request->attachment() : null;

        for ($attempt = 1; ; $attempt++) {
            $reference = Enquiry::newReference();
            $stored = $file !== null ? $this->storeAttachment($file, $reference) : [];
            try {
                return DB::transaction(fn () => Enquiry::query()->create(['reference' => $reference] + $attributes + $stored));
            } catch (UniqueConstraintViolationException $e) {
                $this->discard($stored);
                $winner = Enquiry::query()->where('idempotency_key', $key)->first();
                if ($winner !== null) {
                    return $winner;
                }
                if ($attempt >= self::REFERENCE_ATTEMPTS) {
                    throw $e;
                }
            } catch (Throwable $e) {
                $this->discard($stored);

                throw $e;
            }
        }
    }

    /** @return array{attachment_path: string, attachment_mime: string, attachment_size: int} */
    private function storeAttachment(UploadedFile $file, string $reference): array
    {
        $mime = (string) StoreEnquiryRequest::sniffMime($file);
        $name = Str::random(40).'.'.(self::ATTACHMENT_EXTENSIONS[$mime] ?? 'bin');
        $path = $file->storeAs('enquiries/'.$reference, $name, Enquiry::ATTACHMENT_DISK);
        if ($path === false) {
            throw new RuntimeException('The attachment could not be written to the private disk.');
        }

        return ['attachment_path' => $path, 'attachment_mime' => $mime, 'attachment_size' => (int) $file->getSize()];
    }

    /** @param  array<string, mixed>  $stored */
    private function discard(array $stored): void
    {
        if (isset($stored['attachment_path'])) {
            rescue(fn () => Storage::disk(Enquiry::ATTACHMENT_DISK)->deleteDirectory(dirname($stored['attachment_path'])), report: false);
        }
    }

    private function notify(Enquiry $enquiry): void
    {
        try {
            Mail::to((string) config('summit.enquiries.inbox'))->queue(new EnquiryReceived($enquiry));
        } catch (Throwable $e) {
            $this->log('error', 'Enquiry notification could not be queued', [
                'reference' => $enquiry->reference,
                'status' => $enquiry->status,
                'exception' => $e::class,
            ]);
        }
    }

    // ------------------------------------------------------------------ responses

    /** @param  array<string, mixed>  $attributes */
    private function replay(StoreEnquiryRequest $request, Enquiry $existing, array $attributes): JsonResponse|RedirectResponse
    {
        if (! $existing->isSameSubmission($attributes)) {
            // The key was already used for different content: never answer with another enquiry's reference.
            $this->log('notice', 'Enquiry rejected: idempotency key reused for different content', ['status' => 'failed']);

            return $this->failed($request, 422);
        }
        $this->log('info', 'Enquiry replayed', ['reference' => $existing->reference, 'status' => $existing->status]);

        return $this->received($request, $existing, 200);
    }

    private function received(StoreEnquiryRequest $request, Enquiry $enquiry, int $status): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->to(StoreEnquiryRequest::formUrl(), 303)->with([
                'enquiry_status' => 'received',
                'enquiry_reference' => $enquiry->reference,
            ]);
        }

        return response()->json([
            'status' => 'received',
            'reference' => $enquiry->reference,
            'message' => $this->message('form.state.success'),
        ], $status);
    }

    private function failed(StoreEnquiryRequest $request, int $status): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['status' => 'failed', 'message' => $this->message('form.state.failure')], $status)
            : $this->backToForm($request, 'failed');
    }

    private function rateLimited(StoreEnquiryRequest $request, int $retryAfter): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(
                ['status' => 'rate_limited', 'message' => $this->message('form.state.rate_limited')],
                429,
                ['Retry-After' => (string) max(1, $retryAfter)],
            )
            : $this->backToForm($request, 'rate_limited');
    }

    private function serverError(StoreEnquiryRequest $request, Throwable $e): JsonResponse|RedirectResponse
    {
        $this->log('error', 'Enquiry could not be stored', [
            'status' => 'failed',
            'exception' => $e::class,
            'code' => $e->getCode(),
            'location' => basename($e->getFile()).':'.$e->getLine(),
        ]);

        // 503 when retrying shortly can help (lost connection, lock, deadlock); 500 otherwise.
        $transient = $this->causedByLostConnection($e) || $this->causedByConcurrencyError($e);

        return $this->failed($request, $transient ? 503 : 500);
    }

    /** Without JavaScript: back to the form with the visitor's values and the outcome flashed. */
    private function backToForm(StoreEnquiryRequest $request, string $status): RedirectResponse
    {
        return redirect()->to(StoreEnquiryRequest::formUrl(), 303)
            ->withInput($request->except(StoreEnquiryRequest::NOT_FLASHED))
            ->with('enquiry_status', $status);
    }

    /** Approved copy for a state; empty rather than an exception if the content store is down. */
    private function message(string $key): string
    {
        return (string) rescue(fn () => ui($key), '', report: false);
    }

    /** @param  array<string, mixed>  $context  reference and status (plus the exception class on errors) */
    private function log(string $level, string $message, array $context): void
    {
        rescue(fn () => Log::log($level, $message, $context), report: false);
    }

    private function maxAttempts(): int
    {
        return (int) config('summit.enquiries.rate_limit.max', 5);
    }

    private function decaySeconds(): int
    {
        return (int) config('summit.enquiries.rate_limit.decay_seconds', 600);
    }
}
