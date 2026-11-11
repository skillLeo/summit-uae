<?php

namespace App\Content\Import;

/**
 * Findings collected during an import run; rendered into docs/content-diff.md.
 */
final class Report
{
    /** @var list<array{type: string, locale: ?string, subject: string, detail: string}> */
    private array $entries = [];

    /**
     * Types used by mappers:
     *  - label-mismatch   the same field has different approved labels in two source files
     *  - reuse            approved copy is reused from another source file
     *  - parity           EN/AR/RU structure differs
     *  - source-conflict  the source files disagree on a fact (URL, draft text, count…)
     *  - note             anything else a reviewer should see
     */
    public function add(string $type, string $subject, string $detail, ?string $locale = null): void
    {
        $this->entries[] = compact('type', 'locale', 'subject', 'detail');
    }

    /** @return list<array{type: string, locale: ?string, subject: string, detail: string}> */
    public function entries(?string $type = null): array
    {
        return $type === null
            ? $this->entries
            : array_values(array_filter($this->entries, fn ($e) => $e['type'] === $type));
    }
}
