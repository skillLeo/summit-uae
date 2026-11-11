<?php

namespace App\Content\Import;

/**
 * Maps one family of DOCX files (for one locale at a time) to content records.
 *
 * map() returns records grouped by dataset, e.g.
 *   [
 *     'ui_strings' => ['nav.home' => 'Home'],
 *     'pages'      => ['P01' => ['h1' => '…', 'content' => [...]]],
 *     'services'   => ['S01' => ['title' => '…']],
 *   ]
 * Records from different mappers are deep-merged per locale; setting the same scalar field
 * twice with different values is an import error. Datasets and their non-translatable fields
 * are declared in ContentSchema.
 */
abstract class Mapper
{
    public Report $report;

    /** @return array<string, string> source key => DOCX filename prefix (same prefix in all locales) */
    abstract public function sources(): array;

    /**
     * @param  array<string, Cursor>  $docs  keyed like sources()
     * @return array<string, array<string, mixed>>
     */
    abstract public function map(array $docs, string $locale): array;

    /** Short identifier used in reports. */
    public function name(): string
    {
        return class_basename(static::class);
    }

    /**
     * Split "Employment Law laborlawuae.com" into label and domain.
     *
     * @return array{label: string, domain: string}
     */
    protected function splitDomainLabel(string $text): array
    {
        if (preg_match('/^(.*\S)\s+([a-z0-9.-]+\.[a-z]{2,})$/iu', $text, $m)) {
            return ['label' => $m[1], 'domain' => $m[2]];
        }

        return ['label' => $text, 'domain' => ''];
    }

    /** Decode the ?text= value of a wa.me link. */
    protected function whatsappDraft(string $href): ?string
    {
        $query = parse_url($href, PHP_URL_QUERY);
        if (! is_string($query)) {
            return null;
        }
        parse_str($query, $params);

        return isset($params['text']) && is_string($params['text']) ? $params['text'] : null;
    }
}
