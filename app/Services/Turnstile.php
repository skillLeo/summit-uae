<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile server-side verification (config summit.turnstile). Fails closed: a
 * missing token, a rejected token or an unreachable siteverify endpoint all return false.
 * The token, secret and IP never reach the log.
 */
final class Turnstile
{
    /** Cloudflare's documented maximum token length. */
    private const MAX_TOKEN_LENGTH = 2048;

    /**
     * @param  string|null  $idempotencyKey  the enquiry's UUID; Cloudflare then accepts a retried
     *                                       verification of the same token instead of reporting a duplicate
     */
    public function verify(?string $token, ?string $ip, ?string $idempotencyKey = null): bool
    {
        if (! config('summit.turnstile.enabled')) {
            return true;
        }
        if ($token === null || $token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return false;
        }

        $form = array_filter([
            'secret' => (string) config('summit.turnstile.secret_key'),
            'response' => $token,
            'remoteip' => $ip,
            'idempotency_key' => $idempotencyKey,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::asForm()->timeout(5)->post((string) config('summit.turnstile.verify_url'), $form);
        } catch (Throwable $e) {
            Log::warning('Turnstile verification unavailable', ['exception' => $e::class]);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
