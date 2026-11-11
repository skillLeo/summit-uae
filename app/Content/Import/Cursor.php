<?php

namespace App\Content\Import;

use App\Content\Docx\Block;
use App\Content\Docx\Document;

/**
 * Forward-only reader over a DOCX block stream that records which field consumed which block.
 *
 * Mappers anchor on the red designer markers (identical English markers exist in all three
 * language files, e.g. "SECTION 02 |", "Card S03 |", "H-L013 |", "FIELD phone |") and then take
 * the following black blocks. Every public block that is never consumed is reported as unmapped
 * approved copy in docs/content-diff.md, so nothing can be silently lost.
 */
final class Cursor
{
    private int $pos = 0;

    /** @var array<int, string> block index => field path */
    private array $consumed = [];

    /** @var list<Block> */
    private array $blocks;

    public function __construct(public readonly Document $doc)
    {
        $this->blocks = $doc->blocks;
    }

    public function locale(): string
    {
        return $this->doc->locale;
    }

    public function position(): int
    {
        return $this->pos;
    }

    public function seek(int $position): void
    {
        $this->pos = max(0, min($position, count($this->blocks)));
    }

    public function eof(): bool
    {
        return $this->pos >= count($this->blocks);
    }

    public function peek(): ?Block
    {
        return $this->blocks[$this->pos] ?? null;
    }

    /** Peek at the next public block without moving or consuming. */
    public function peekPublic(): ?Block
    {
        for ($i = $this->pos; $i < count($this->blocks); $i++) {
            if ($this->blocks[$i]->isPublic()) {
                return $this->blocks[$i];
            }
        }

        return null;
    }

    /**
     * Move to just after the next block whose red note matches $pattern.
     * Searches forward from the current position.
     */
    public function expectNote(string $pattern): Block
    {
        $found = $this->findNote($pattern);
        if ($found === null) {
            throw new ImportException($this->where()." expected a designer note matching {$pattern}");
        }

        return $found;
    }

    /** Like expectNote() but returns null (and does not move) when no note matches. */
    public function findNote(string $pattern, ?int $before = null): ?Block
    {
        $end = $before ?? count($this->blocks);
        for ($i = $this->pos; $i < $end; $i++) {
            if ($this->blocks[$i]->noteMatches($pattern)) {
                $this->pos = $i + 1;

                return $this->blocks[$i];
            }
        }

        return null;
    }

    /** Index of the next note matching $pattern (without moving), or null. */
    public function indexOfNote(string $pattern): ?int
    {
        for ($i = $this->pos; $i < count($this->blocks); $i++) {
            if ($this->blocks[$i]->noteMatches($pattern)) {
                return $i;
            }
        }

        return null;
    }

    /** Next public block, skipping pure designer notes. */
    public function nextPublic(): Block
    {
        while ($this->pos < count($this->blocks)) {
            $b = $this->blocks[$this->pos++];
            if ($b->isPublic()) {
                return $b;
            }
        }
        throw new ImportException($this->where().' unexpected end of document');
    }

    /** Take the next public block's text into $field. */
    public function take(string $field): string
    {
        return $this->takeBlock($field)->text;
    }

    public function takeBlock(string $field): Block
    {
        $b = $this->nextPublic();
        $this->consume($b, $field);

        return $b;
    }

    public function takeHeading(int $level, string $field): string
    {
        $b = $this->nextPublic();
        if (! $b->isHeading($level)) {
            throw new ImportException($this->where($b)." expected H{$level} for {$field}, got ".$this->describe($b));
        }
        $this->consume($b, $field);

        return $b->text;
    }

    /**
     * Take a black linked label.
     *
     * @return array{label: string, href: string}
     */
    public function takeLink(string $field): array
    {
        $b = $this->nextPublic();
        $href = $b->href();
        if ($href === null) {
            throw new ImportException($this->where($b)." expected a linked label for {$field}, got ".$this->describe($b));
        }
        $this->consume($b, $field);

        return ['label' => $b->text, 'href' => $href];
    }

    /**
     * Consume consecutive public blocks (notes in between are skipped) while $accept returns true.
     *
     * @param  callable(Block): bool  $accept
     * @return list<Block>
     */
    public function takeWhile(callable $accept, string $field): array
    {
        $out = [];
        while (true) {
            $save = $this->pos;
            $next = $this->peekPublic();
            if ($next === null || ! $accept($next)) {
                $this->pos = $save;
                break;
            }
            $b = $this->nextPublic();
            $this->consume($b, $field.'.'.count($out));
            $out[] = $b;
        }

        return $out;
    }

    /** @return list<string> */
    public function takeBullets(string $field, ?int $expect = null): array
    {
        $items = array_map(fn (Block $b) => $b->text, $this->takeWhile(fn (Block $b) => $b->bullet, $field));
        $this->assertCount($items, $expect, $field);

        return $items;
    }

    /** @return list<string> */
    public function takeNumbered(string $field, ?int $expect = null): array
    {
        $items = array_map(fn (Block $b) => $b->text, $this->takeWhile(fn (Block $b) => $b->number !== null, $field));
        $this->assertCount($items, $expect, $field);

        return $items;
    }

    /**
     * Plain paragraphs (not headings, bullets, numbered items or links) until the next heading or
     * the next note matching $stopNote.
     *
     * @return list<string>
     */
    public function takeParagraphs(string $field, ?string $stopNote = null, ?int $expect = null): array
    {
        $limit = $stopNote !== null ? $this->indexOfNote($stopNote) : null;
        $items = array_map(fn (Block $b) => $b->text, $this->takeWhile(
            fn (Block $b) => ($limit === null || $b->index < $this->blocks[$limit]->index)
                && $b->headingLevel === null && ! $b->bullet && $b->number === null && $b->links === [],
            $field,
        ));
        $this->assertCount($items, $expect, $field);

        return $items;
    }

    public function consume(Block $block, string $field): void
    {
        $this->consumed[$block->index] = $field;
    }

    public function isConsumed(Block $block): bool
    {
        return isset($this->consumed[$block->index]);
    }

    /** @return list<Block> */
    public function unconsumedPublic(): array
    {
        return array_values(array_filter($this->blocks, fn (Block $b) => $b->isPublic() && ! isset($this->consumed[$b->index])));
    }

    /** @return array<int, string> */
    public function ledger(): array
    {
        return $this->consumed;
    }

    /** @param list<mixed> $items */
    private function assertCount(array $items, ?int $expect, string $field): void
    {
        if ($expect !== null && count($items) !== $expect) {
            throw new ImportException($this->where()." expected {$expect} items for {$field}, found ".count($items));
        }
    }

    public function where(?Block $b = null): string
    {
        $b ??= $this->peek();

        return sprintf('[%s %s #%s]', $this->doc->locale, $this->doc->filename(), $b?->index ?? 'eof');
    }

    private function describe(Block $b): string
    {
        $kind = $b->headingLevel !== null ? 'H'.$b->headingLevel : ($b->bullet ? 'bullet' : 'paragraph');

        return $kind.' "'.mb_strimwidth($b->text, 0, 70, '…').'"';
    }
}
