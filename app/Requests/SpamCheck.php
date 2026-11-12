<?php

namespace App\Requests;

use App\Models\Enquiry;
use App\Settings\RequestSettings;
use Throwable;

/**
 * The spam rules of the public form (docs/briefs/design-2.1-and-admin-cms.md §10.6). Each rule that fires adds its
 * weight and its code; from config('cms.requests.spam_threshold') (60) on, the request is stored as spam. Spam is
 * answered exactly like a success (the sender learns nothing), never notified, and never counted in a figure.
 *
 * - honeypot   the hidden "website" field is filled (100: always spam, as before)
 * - too_fast   the form reports it was sent sooner than the minimum fill time after it opened (60). Only Design 2's
 *              form reports the time; without it the rule does not apply.
 * - links      more links in the name, summary and deadline details than allowed (60); a link in the name (60)
 * - burst      more accepted requests than allowed from the same hashed IP within 24 hours (40, never spam alone)
 *
 * The rate limit per hashed IP (5 in 10 minutes, a 429) stays in front of all of this (EnquiryController).
 */
final class SpamCheck
{
    public const WEIGHTS = ['honeypot' => 100, 'too_fast' => 60, 'links' => 60, 'name_link' => 60, 'burst' => 40];

    private const LINK = '~(?:https?://|www\.|\b[a-z0-9-]+\.(?:com|net|org|ru|xyz|top|info|biz|io|co|shop|site|online|link|click)\b)~iu';

    /**
     * @param  array<string, mixed>  $attributes  StoreEnquiryRequest::enquiryAttributes()
     * @return array{score: int, reasons: list<string>, spam: bool}
     */
    public static function evaluate(array $attributes, bool $honeypot, ?int $elapsedMs, string $ipHash): array
    {
        $settings = self::settings();
        $reasons = [];

        if ($honeypot) {
            $reasons[] = 'honeypot';
        }
        if ($elapsedMs !== null && $elapsedMs >= 0 && $elapsedMs < $settings['min_fill'] * 1000) {
            $reasons[] = 'too_fast';
        }
        $text = implode("\n", array_filter([$attributes['summary'] ?? null, $attributes['deadline_details'] ?? null]));
        if (self::links($text) > $settings['max_links']) {
            $reasons[] = 'links';
        }
        if (self::links((string) ($attributes['name'] ?? '')) > 0) {
            $reasons[] = 'name_link';
        }
        if ($settings['burst'] > 0) {
            $recent = Enquiry::query()->withTrashed()->where('ip_hash', $ipHash)
                ->where('created_at', '>=', now()->subDay())->where('status', '!=', Enquiry::STATUS_SPAM)->count();
            if ($recent >= $settings['burst']) {
                $reasons[] = 'burst';
            }
        }

        $score = min(100, array_sum(array_map(fn (string $r) => self::WEIGHTS[$r], $reasons)));

        return ['score' => $score, 'reasons' => $reasons, 'spam' => $score >= (int) config('cms.requests.spam_threshold', 60)];
    }

    public static function links(string $text): int
    {
        return (int) preg_match_all(self::LINK, $text);
    }

    /** @return array{min_fill: int, max_links: int, burst: int} */
    private static function settings(): array
    {
        try {
            $s = app(RequestSettings::class);

            return ['min_fill' => max(0, $s->spam_min_fill_seconds), 'max_links' => max(0, $s->spam_max_links), 'burst' => max(0, $s->spam_burst_per_day)];
        } catch (Throwable) {
            return ['min_fill' => 3, 'max_links' => 2, 'burst' => 3];
        }
    }
}
