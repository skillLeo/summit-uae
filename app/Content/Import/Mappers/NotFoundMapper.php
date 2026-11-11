<?php

namespace App\Content\Import\Mappers;

use App\Content\Docx\Block;
use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * L03_404_Page*.docx → pages.U01, the reusable error template served with a real HTTP 404.
 *
 * Anchors on the English red markers present in all three files ("E404 |", "| H1.",
 * "E404-L00n |", "Browser title."). Link targets are the page IDs named in each "-> ID:" marker,
 * never the /ar/ fallback URLs embedded in the black hyperlinks.
 */
final class NotFoundMapper extends Mapper
{
    /** Recovery actions: marker => [field, page ID the marker must point to]. */
    private const ACTIONS = [
        'E404-L001' => ['home_label', 'P01'],
        'E404-L002' => ['services_label', 'P02'],
    ];

    public function sources(): array
    {
        return ['U01' => 'L03_404_Page'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['U01'];
        $page = [];

        // The first black line is the small visible error code, then the H1 and the explanation.
        $c->expectNote('/^E404 \|/');
        $code = $c->takeBlock('U01.code_label');
        if (preg_match('/^\d{3}$/', $code->text) !== 1) {
            throw new ImportException($c->where($code).' expected the visible error code, got "'.$code->text.'"');
        }
        $page['code_label'] = $code->text;
        $c->expectNote('/\| H1\.?$/');
        $page['h1'] = $c->takeHeading(1, 'U01.h1');
        $page['text'] = $c->take('U01.text');

        foreach (self::ACTIONS as $marker => [$field, $expected]) {
            $note = $c->expectNote('/^'.$marker.' \|/');
            $link = $c->takeLink("U01.actions.{$field}");
            $target = $this->target($c, $note, $link['href']);
            if ($target !== $expected) {
                throw new ImportException($c->where($note)." {$marker} points to {$target}, expected {$expected} for actions.{$field}");
            }
            $page['actions'][$field] = $link['label'];
        }

        // Plain lead-in line ("Looking for specific information?") above the recovery links.
        $more = $c->takeBlock('U01.more_heading');
        if ($more->links !== [] || $more->isHeading()) {
            throw new ImportException($c->where($more).' expected the plain lead-in line above the recovery links');
        }
        $page['more_heading'] = $more->text;

        $title = $c->indexOfNote('/^Browser title\b/');
        if ($title === null) {
            throw new ImportException($c->where().' expected the browser-title marker');
        }
        $page['links'] = [];
        while (($note = $c->findNote('/^E404-L\d{3} \|/', $title)) !== null) {
            $link = $c->takeLink('U01.links.'.count($page['links']));
            $page['links'][] = ['label' => $link['label'], 'target' => $this->target($c, $note, $link['href'])];
        }
        if ($page['links'] === []) {
            throw new ImportException($c->where().' expected recovery links after the lead-in line');
        }

        $c->expectNote('/^Browser title\b/');
        $page['seo_title'] = $c->take('U01.seo_title');

        return ['pages' => ['U01' => $page]];
    }

    /**
     * Logical target of a red link marker: "-> P05: /ar/faq/." gives P05 and "-> #case-01" gives
     * case-01. A black hyperlink that leads somewhere other than the marker's fallback path is
     * reported as a source conflict.
     */
    private function target(Cursor $c, Block $note, string $href): string
    {
        $marker = preg_match('/^(\S+) \|/', $note->note, $id) === 1 ? $id[1] : '#'.$note->index;
        if (preg_match('/->\s*([A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*)\s*:\s*(\S+?)\.?(?:\s|$)/', $note->note, $m) === 1) {
            $fragment = parse_url($href, PHP_URL_FRAGMENT);
            $actual = (string) parse_url($href, PHP_URL_PATH).(is_string($fragment) ? '#'.$fragment : '');
            if ($actual !== $m[2]) {
                $this->report->add('source-conflict', "U01 {$marker}", "hyperlink {$href} does not match the marker destination {$m[2]}", $c->locale());
            }

            return $m[1];
        }
        if (preg_match('/->\s*#([A-Za-z0-9_-]+)/', $note->note, $m) === 1) {
            return $m[1];
        }

        throw new ImportException($c->where($note)." {$marker} names no target ID");
    }
}
