<?php

namespace App\Content\Docx;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Minimal, dependency-free OOXML reader for the Summit content handoff.
 *
 * It resolves each run's effective colour (run → character style → paragraph style chain →
 * document defaults) so that red designer instructions can be separated from black public copy.
 */
final class DocxReader
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @var array<string, array{based: ?string, color: ?string, outline: ?int}> */
    private array $styles = [];

    private ?string $defaultColor = null;

    /** @var array<string, string> */
    private array $rels = [];

    private int $index = 0;

    private int $tableCount = 0;

    public function read(string $path, string $locale): Document
    {
        if (! is_file($path)) {
            throw new RuntimeException("DOCX not found: {$path}");
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Cannot open DOCX: {$path}");
        }

        try {
            $this->styles = [];
            $this->defaultColor = null;
            $this->rels = [];
            $this->index = 0;
            $this->tableCount = 0;

            $styles = $zip->getFromName('word/styles.xml');
            if ($styles !== false) {
                $this->loadStyles($this->load($styles));
            }
            $rels = $zip->getFromName('word/_rels/document.xml.rels');
            if ($rels !== false) {
                $this->loadRels($this->load($rels));
            }
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                throw new RuntimeException("word/document.xml missing in {$path}");
            }
            $doc = $this->load($xml);
        } finally {
            $zip->close();
        }

        $xp = $this->xpath($doc);
        $body = $xp->query('/w:document/w:body')->item(0);
        $blocks = [];
        if ($body instanceof DOMElement) {
            $this->walk($body, $xp, $blocks, null);
        }

        return new Document($path, $locale, $blocks);
    }

    private function load(string $xml): DOMDocument
    {
        // Office parts never need a DTD; refusing them rules out entity expansion and XXE.
        if (str_contains($xml, '<!DOCTYPE') || str_contains($xml, '<!ENTITY')) {
            throw new RuntimeException('DTD or entity declarations are not allowed in DOCX parts.');
        }
        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);

        return $dom;
    }

    private function xpath(DOMDocument $doc): DOMXPath
    {
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('w', self::W);
        $xp->registerNamespace('r', self::R);

        return $xp;
    }

    private function loadStyles(DOMDocument $doc): void
    {
        $xp = $this->xpath($doc);
        $default = $xp->query('/w:styles/w:docDefaults/w:rPrDefault/w:rPr/w:color')->item(0);
        if ($default instanceof DOMElement) {
            $this->defaultColor = $default->getAttributeNS(self::W, 'val') ?: null;
        }
        foreach ($xp->query('/w:styles/w:style') as $style) {
            /** @var DOMElement $style */
            $id = $style->getAttributeNS(self::W, 'styleId');
            $based = $xp->query('w:basedOn', $style)->item(0);
            $color = $xp->query('w:rPr/w:color', $style)->item(0);
            $outline = $xp->query('w:pPr/w:outlineLvl', $style)->item(0);
            $this->styles[$id] = [
                'based' => $based instanceof DOMElement ? $based->getAttributeNS(self::W, 'val') : null,
                'color' => $color instanceof DOMElement ? ($color->getAttributeNS(self::W, 'val') ?: null) : null,
                'outline' => $outline instanceof DOMElement ? (int) $outline->getAttributeNS(self::W, 'val') : null,
            ];
        }
    }

    private function loadRels(DOMDocument $doc): void
    {
        foreach ($doc->getElementsByTagName('Relationship') as $rel) {
            /** @var DOMElement $rel */
            $this->rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
    }

    private function styleValue(?string $id, string $key): string|int|null
    {
        $seen = [];
        while ($id !== null && isset($this->styles[$id]) && ! isset($seen[$id])) {
            $seen[$id] = true;
            if ($this->styles[$id][$key] !== null) {
                return $this->styles[$id][$key];
            }
            $id = $this->styles[$id]['based'];
        }

        return null;
    }

    /**
     * @param  list<Block>  $blocks
     */
    private function walk(DOMElement $parent, DOMXPath $xp, array &$blocks, ?array $cell): void
    {
        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::W) {
                continue;
            }
            switch ($node->localName) {
                case 'p':
                    $block = $this->paragraph($node, $xp, $cell);
                    if ($block !== null) {
                        $blocks[] = $block;
                    }
                    foreach ($xp->query('.//w:txbxContent', $node) as $box) {
                        $this->walk($box, $xp, $blocks, $cell);
                    }
                    break;
                case 'tbl':
                    $t = $this->tableCount++;
                    foreach ($xp->query('w:tr', $node) as $r => $row) {
                        foreach ($xp->query('w:tc', $row) as $c => $tc) {
                            $this->walk($tc, $xp, $blocks, ['t' => $t, 'r' => $r, 'c' => $c]);
                        }
                    }
                    break;
                case 'sdt':
                    $content = $xp->query('w:sdtContent', $node)->item(0);
                    if ($content instanceof DOMElement) {
                        $this->walk($content, $xp, $blocks, $cell);
                    }
                    break;
            }
        }
    }

    private function paragraph(DOMElement $p, DOMXPath $xp, ?array $cell): ?Block
    {
        $styleEl = $xp->query('w:pPr/w:pStyle', $p)->item(0);
        $style = $styleEl instanceof DOMElement ? $styleEl->getAttributeNS(self::W, 'val') : 'Normal';
        $paragraphColor = $this->styleValue($style, 'color') ?? $this->defaultColor;
        $outline = $this->styleValue($style, 'outline');

        $black = '';
        $red = '';
        $links = [];

        // Runs may sit directly in the paragraph or inside hyperlinks, insertions, smart tags, fields…
        foreach ($xp->query('.//w:r[not(ancestor::w:txbxContent)]', $p) as $run) {
            /** @var DOMElement $run */
            $text = $this->runText($run);
            if ($text === '') {
                continue;
            }
            $color = null;
            $colorEl = $xp->query('w:rPr/w:color', $run)->item(0);
            if ($colorEl instanceof DOMElement) {
                $color = $colorEl->getAttributeNS(self::W, 'val') ?: null;
            }
            if ($color === null) {
                $rStyle = $xp->query('w:rPr/w:rStyle', $run)->item(0);
                if ($rStyle instanceof DOMElement) {
                    $color = $this->styleValue($rStyle->getAttributeNS(self::W, 'val'), 'color');
                }
            }
            $color ??= $paragraphColor;

            if (self::isRed($color)) {
                $red .= $text;

                continue;
            }

            $black .= $text;
            $href = $this->hrefFor($run);
            if ($href !== null) {
                $last = array_key_last($links);
                if ($last !== null && $links[$last]['href'] === $href && $links[$last]['open']) {
                    $links[$last]['text'] .= $text;
                } else {
                    $links[] = ['text' => $text, 'href' => $href, 'open' => true];
                }
            } elseif ($links !== []) {
                $links[array_key_last($links)]['open'] = false;
            }
        }

        $black = self::clean($black);
        $red = self::clean($red);
        if ($black === '' && $red === '') {
            return null;
        }

        $bullet = false;
        $number = null;
        if (preg_match('/^[•·▪◦●\x{2022}]\s*/u', $black, $m)) {
            $bullet = true;
            $black = ltrim(mb_substr($black, mb_strlen($m[0])));
        } elseif (preg_match('/^(\d{1,2})[.)]\s+/u', $black, $m) && $outline === null) {
            $number = (int) $m[1];
            $black = mb_substr($black, mb_strlen($m[0]));
        }

        $links = array_map(fn ($l) => ['text' => self::clean($l['text']), 'href' => $l['href']], $links);

        return new Block(
            index: $this->index++,
            style: $style,
            headingLevel: $outline !== null ? ((int) $outline) + 1 : null,
            text: $black,
            note: $red,
            links: array_values(array_filter($links, fn ($l) => $l['text'] !== '')),
            cell: $cell,
            bullet: $bullet,
            number: $number,
        );
    }

    private function runText(DOMElement $run): string
    {
        $out = '';
        foreach ($run->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->namespaceURI !== self::W) {
                continue;
            }
            $out .= match ($child->localName) {
                't' => $child->textContent,
                'tab' => "\t",
                'br', 'cr' => "\n",
                'noBreakHyphen' => "\u{2011}",
                'softHyphen' => "\u{00AD}",
                default => '',
            };
        }

        return $out;
    }

    private function hrefFor(DOMElement $run): ?string
    {
        $parent = $run->parentNode;
        while ($parent instanceof DOMElement && $parent->localName !== 'p') {
            if ($parent->localName === 'hyperlink' && $parent->namespaceURI === self::W) {
                $rid = $parent->getAttributeNS(self::R, 'id');
                if ($rid !== '' && isset($this->rels[$rid])) {
                    return $this->rels[$rid];
                }
                $anchor = $parent->getAttributeNS(self::W, 'anchor');

                return $anchor !== '' ? '#'.$anchor : null;
            }
            $parent = $parent->parentNode;
        }

        return null;
    }

    public static function isRed(?string $hex): bool
    {
        if ($hex === null || strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return false;
        }
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        return $r >= 0x90 && $g <= 0x60 && $b <= 0x60;
    }

    /** Trim and normalise only whitespace runs; the approved wording itself is never altered. */
    public static function clean(string $text): string
    {
        $text = str_replace(["\r", "\t"], ['', ' '], $text);
        $text = preg_replace('/[ \x{00A0}]{2,}/u', ' ', $text) ?? $text;

        // Unicode-aware trim: PHP's trim() works on bytes and would split multi-byte characters
        // such as Cyrillic "Р" (D0 A0) when given U+00A0 in its character list.
        return preg_replace('/^[\s\x{00A0}\x{200B}\x{FEFF}]+|[\s\x{00A0}\x{200B}\x{FEFF}]+$/u', '', $text) ?? $text;
    }
}
