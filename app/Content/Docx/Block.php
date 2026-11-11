<?php

namespace App\Content\Docx;

/**
 * One paragraph-level unit of a DOCX body, in reading order.
 *
 * `text` holds only the black (public) runs; `note` holds only the red (designer) runs.
 * A block whose `text` is empty is a pure designer note and must never be published.
 */
final class Block
{
    /**
     * @param  list<array{text: string, href: string}>  $links  hyperlinks found in black runs
     * @param  array{t: int, r: int, c: int}|null  $cell  table position when inside a table
     */
    public function __construct(
        public readonly int $index,
        public readonly string $style,
        public readonly ?int $headingLevel,
        public readonly string $text,
        public readonly string $note,
        public readonly array $links = [],
        public readonly ?array $cell = null,
        public readonly bool $bullet = false,
        public readonly ?int $number = null,
    ) {}

    public function isPublic(): bool
    {
        return $this->text !== '';
    }

    public function isNote(): bool
    {
        return $this->text === '' && $this->note !== '';
    }

    public function isHeading(?int $level = null): bool
    {
        if ($this->headingLevel === null || ! $this->isPublic()) {
            return false;
        }

        return $level === null || $this->headingLevel === $level;
    }

    /** First hyperlink target in the black runs, if any. */
    public function href(): ?string
    {
        return $this->links[0]['href'] ?? null;
    }

    /** True when the red note text matches the given regular expression. */
    public function noteMatches(string $pattern): bool
    {
        return $this->note !== '' && preg_match($pattern, $this->note) === 1;
    }

    public function toArray(): array
    {
        return array_filter([
            'i' => $this->index,
            'style' => $this->style,
            'h' => $this->headingLevel,
            'text' => $this->text,
            'note' => $this->note,
            'links' => $this->links,
            'cell' => $this->cell,
            'bullet' => $this->bullet ?: null,
            'number' => $this->number,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
