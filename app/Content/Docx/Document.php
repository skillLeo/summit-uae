<?php

namespace App\Content\Docx;

final class Document
{
    /**
     * @param  list<Block>  $blocks
     */
    public function __construct(
        public readonly string $path,
        public readonly string $locale,
        public readonly array $blocks,
    ) {}

    public function filename(): string
    {
        return basename($this->path);
    }

    /** @return list<Block> */
    public function publicBlocks(): array
    {
        return array_values(array_filter($this->blocks, fn (Block $b) => $b->isPublic()));
    }

    /** @return list<Block> */
    public function notes(): array
    {
        return array_values(array_filter($this->blocks, fn (Block $b) => $b->note !== ''));
    }

    /** Annotated plain-text rendering used by `summit:docx-dump` and the designer-notes export. */
    public function toAnnotatedText(): string
    {
        $lines = [];
        foreach ($this->blocks as $b) {
            $tag = $b->headingLevel !== null && $b->isPublic() ? 'H'.$b->headingLevel : ($b->bullet ? '•' : ($b->number !== null ? '#'.$b->number : 'P'));
            if ($b->cell !== null) {
                $tag .= sprintf(' [t%d r%d c%d]', $b->cell['t'], $b->cell['r'], $b->cell['c']);
            }
            $text = $b->text;
            foreach ($b->links as $link) {
                $text .= ' <'.$link['href'].'>';
            }
            $parts = array_filter([$text, $b->note !== '' ? '[[RED: '.$b->note.']]' : '']);
            $lines[] = sprintf('%04d %-16s %s', $b->index, $tag, implode(' ', $parts));
        }

        return implode("\n", $lines)."\n";
    }
}
