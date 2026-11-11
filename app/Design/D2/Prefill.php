<?php

namespace App\Design\D2;

use App\Support\SiteUrl;

/**
 * Consultation-wizard prefill (docs/d2/engineering.md §8). Every "Request a Legal Consultation" that
 * carries context (hero tool card, Matter Navigator, calculators, team cards, service pages) uses it:
 * on the page that holds the wizard the browser dispatches the d2:prefill event; elsewhere it links to
 * the consultation form with these query parameters, which the wizard validates and applies.
 */
final class Prefill
{
    public const KEYS = ['service', 'situation', 'audience', 'consultant', 'tool'];

    /** @param array<string, string|null> $params */
    public static function url(array $params = [], ?string $locale = null): string
    {
        $query = array_filter(array_intersect_key($params, array_flip(self::KEYS)), fn ($v) => is_string($v) && $v !== '');
        $base = config('summit.contact_page_released') ? SiteUrl::page('P07', $locale) : SiteUrl::home($locale);

        return $base.($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986)).'#consultation-form';
    }

    /**
     * The validated prefill from the current request (no-JS path and cross-page links). Unknown,
     * malformed or array values are dropped (Design 1 review SEC-02).
     *
     * @return array{service?: string, situation?: string, audience?: string, consultant?: string, tool?: string}
     */
    public static function fromRequest(): array
    {
        $q = request()->query();
        $out = [];
        $service = is_string($q['service'] ?? null) ? $q['service'] : null;
        if ($service !== null && array_key_exists($service, config('summit.services'))) {
            $out['service'] = $service;
        }
        $situation = is_string($q['situation'] ?? null) ? $q['situation'] : null;
        if ($situation !== null && preg_match('/^(S(0[1-9]|1[01]))-([1-4])$/', $situation, $m) === 1) {
            $out['situation'] = $situation;
            $out['service'] ??= $m[1];
        }
        $audience = is_string($q['audience'] ?? null) ? $q['audience'] : null;
        if ($audience !== null && array_key_exists($audience, config('audience_map.hero'))) {
            $out['audience'] = $audience;
        }
        $consultant = is_string($q['consultant'] ?? null) ? $q['consultant'] : null;
        if ($consultant !== null && preg_match('/^team-[a-z]+$/', $consultant) === 1 && content()->record('team_members', $consultant) !== null) {
            $out['consultant'] = $consultant;
        }
        $tool = is_string($q['tool'] ?? null) ? $q['tool'] : null;
        if ($tool !== null && in_array($tool, ['gratuity', 'rent'], true)) {
            $out['tool'] = $tool;
        }

        return $out;
    }
}
