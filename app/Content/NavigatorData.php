<?php

namespace App\Content;

use App\Support\SiteUrl;

/**
 * Matter Navigator data, built only from approved copy:
 * 44 situations (P02 "This service may be relevant if:"), and per service the documents,
 * first steps, mistakes and related guide from its S01–S11 page. Used for the no-JS
 * server render and as inline JSON for the browser (no network calls).
 */
final class NavigatorData
{
    public function __construct(private readonly ContentRepository $content) {}

    /** @return list<array<string, mixed>> */
    public function services(): array
    {
        $out = [];
        foreach ($this->content->services() as $s) {
            $sections = $s['sections'] ?? [];
            // Every guide the service page links under "Related Topics and Services", with that
            // page's approved link label (S01 links two: commercial debt and dishonoured cheques).
            $guides = [];
            foreach ($s['related_guides'] ?? [] as $guideCode) {
                $label = null;
                foreach ($sections['related']['links'] ?? [] as $link) {
                    if (($link['target'] ?? null) === $guideCode) {
                        $label = $link['label'];
                        break;
                    }
                }
                $guides[] = [
                    'code' => $guideCode,
                    'label' => $label ?? (content()->guide($guideCode)['home_title'] ?? ''),
                    'url' => SiteUrl::guide($guideCode),
                ];
            }
            $out[] = [
                'code' => $s['code'],
                'title' => $s['title'] ?? '',
                'url' => SiteUrl::service($s['code']),
                'situations' => array_values($s['situations'] ?? []),
                'documents' => array_values($s['documents'] ?? []),
                'documents_note' => $s['documents_note'] ?? '',
                'first_steps' => array_values($s['first_steps'] ?? []),
                'mistakes' => array_values($s['mistakes'] ?? []),
                'headings' => [
                    'documents' => $sections['documents']['heading'] ?? '',
                    'first_steps' => $sections['next_step']['first_steps_heading'] ?? '',
                    'mistakes' => $sections['next_step']['mistakes_heading'] ?? '',
                ],
                'guides' => $guides,
            ];
        }

        return $out;
    }

    /**
     * Parse a situation reference such as "S05-2" (service code + 1-based situation index).
     *
     * @return array{service: string, index: int}|null
     */
    public static function parse(?string $ref): ?array
    {
        if ($ref === null || ! preg_match('/^(S(?:0[1-9]|1[01]))-([1-9])$/', $ref, $m)) {
            return null;
        }

        return ['service' => $m[1], 'index' => (int) $m[2]];
    }

    /** @return array<string, mixed> the compact payload embedded as JSON */
    public function payload(): array
    {
        return [
            'services' => $this->services(),
            'audiences' => config('summit.features.navigator_audience_step') ? config('summit.navigator_audiences') : null,
            'storageKey' => 'summit.navigator.v1',
        ];
    }
}
