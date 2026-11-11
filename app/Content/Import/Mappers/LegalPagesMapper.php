<?php

namespace App\Content\Import\Mappers;

use App\Content\Docx\Block;
use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 08_Legal_Notice, L01_Privacy_Policy and L02_Terms_and_Conditions → pages.P08, pages.L01 and
 * pages.L02, plus the cookie-preference interface strings from the privacy file (ui_strings
 * "cookies.*", shown only when optional tools are actually configured).
 *
 * Page shape:
 *   breadcrumb        [{label, target: 'P01'}, {label: current page, target: null}]
 *   h1, last_updated  ("Last updated: …" verbatim), seo_title, meta_description
 *   whatsapp_draft    only if a file supplies a black draft (none of the current files do)
 *   content           {intro: [paragraph…], sections: [{heading, blocks: [{type: 'p', text} |
 *                     {type: 'ul'|'ol', items: […]}]}], links: [{label, target}]}
 *
 * Link targets are the logical IDs from the red "-> ID:" markers (P01, P07, P08, L01, L02, EMAIL,
 * TEL), never the /ar/ fallback URLs in the black hyperlinks. Anchors: "{CODE}-L00n |",
 * "| H1.", "Last updated line", "SEO title", "Meta description" and the cookie-interface role notes.
 */
final class LegalPagesMapper extends Mapper
{
    /** Cookie-preference strings in source order: ui_strings key => red role marker. */
    private const COOKIE_STRINGS = [
        'cookies.banner_title' => '/^Banner title\b/',
        'cookies.banner_text' => '/^Banner text\b/',
        'cookies.accept_optional' => '/^Accept all\b/',
        'cookies.reject_optional' => '/^Reject all\b/',
        'cookies.customise' => '/^Open preferences\b/',
        'cookies.panel_title' => '/^Preference panel title\b/',
        'cookies.necessary_title' => '/^Necessary category title\b/',
        'cookies.necessary_text' => '/^Necessary category explanation\b/',
        'cookies.analytics_title' => '/^Analytics category title\b/',
        'cookies.analytics_text' => '/^Analytics category explanation\b/',
        'cookies.advertising_title' => '/^Advertising category title\b/',
        'cookies.advertising_text' => '/^Advertising category explanation\b/',
        'cookies.column_provider' => '/^Provider details column\b/',
        'cookies.column_purpose' => '/^Purpose details column\b/',
        'cookies.column_name' => '/^Storage identifier details column\b/',
        'cookies.column_duration' => '/^Duration details column\b/',
        'cookies.save' => '/^Save preferences button\b/',
        'cookies.cancel' => '/^Cancel and close\b/',
        'cookies.saved' => '/^Success live message\b/',
        'cookies.save_failed' => '/^Failure live message\b/',
        'cookies.footer_button' => '/^Persistent footer button\b/',
    ];

    public function sources(): array
    {
        return [
            'P08' => '08_Legal_Notice',
            'L01' => 'L01_Privacy_Policy',
            'L02' => 'L02_Terms_and_Conditions',
        ];
    }

    public function map(array $docs, string $locale): array
    {
        $pages = [];
        foreach ($docs as $code => $c) {
            $pages[$code] = $this->page($c, $code);
        }
        $this->reviewNumbering($pages, $locale);

        return [
            'pages' => $pages,
            'ui_strings' => $this->cookieStrings($docs['L01']),
        ];
    }

    /**
     * Reviewer notes: the approved H2 headings carry their own sequence numbers, and the Arabic
     * copy may write numbers in Arabic-Indic digits. Both are kept verbatim.
     *
     * @param  array<string, array<string, mixed>>  $pages
     */
    private function reviewNumbering(array $pages, string $locale): void
    {
        $numbered = [];
        $first = null;
        foreach ($pages as $code => $page) {
            $headings = array_column($page['content']['sections'], 'heading');
            if (count(preg_grep('/^\p{Nd}+\.\s/u', $headings) ?: []) === count($headings)) {
                $numbered[] = $code;
                $first ??= preg_replace('/^(\p{Nd}+\.).*$/su', '$1', $headings[0]);
            }
        }
        if ($numbered !== []) {
            $this->report->add('note', implode(', ', $numbered).' sections',
                "The approved H2 section headings include their own sequence number (\"{$first}\" …); render them verbatim without adding a list counter.", $locale);
        }

        $eastern = array_keys(array_filter($pages, fn (array $p) => preg_match('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $p['last_updated'].implode(' ', array_column($p['content']['sections'], 'heading'))) === 1));
        if ($eastern !== []) {
            $this->report->add('note', implode(', ', $eastern).' digits',
                'The approved copy writes the last-updated date and section numbers in Arabic-Indic digits (e.g. "'.$pages[$eastern[0]]['last_updated'].'"); kept verbatim, as converting them to Western digits would change approved text.', $locale);
        }
    }

    /** @return array<string, mixed> */
    private function page(Cursor $c, string $code): array
    {
        $page = [];

        // ---------------------------------------------------------------- breadcrumb + heading
        $note = $c->expectNote('/^'.$code.'-L001 \|/');
        $home = $c->takeLink("{$code}.breadcrumb.0");
        $current = $c->takeBlock("{$code}.breadcrumb.1");
        if ($current->links !== [] || $current->isHeading()) {
            throw new ImportException($c->where($current).' expected the plain current-page breadcrumb text');
        }
        $page['breadcrumb'] = [
            ['label' => $home['label'], 'target' => $this->target($c, $code, $note, $home['href'])],
            ['label' => $current->text, 'target' => null],
        ];
        $c->expectNote('/\| H1\.?$/');
        $page['h1'] = $c->takeHeading(1, "{$code}.h1");
        $c->expectNote('/^Last updated line\b/');
        $page['last_updated'] = $c->take("{$code}.last_updated");

        // ---------------------------------------------------------------- body
        // The body ends at the first contact/cross-link marker (or the SEO title if a page has none).
        $end = $c->indexOfNote('/^(?:'.$code.'-L\d{3} \||SEO title\b)/');
        $seo = $c->indexOfNote('/^SEO title\b/');
        if ($end === null || $seo === null) {
            throw new ImportException($c->where()." {$code} has no SEO title marker");
        }
        $content = [
            'intro' => array_map(fn (array $b) => $b['text'], $this->blocks($c, "{$code}.intro", $end, paragraphsOnly: true)),
            'sections' => [],
            'links' => [],
        ];
        while (($next = $c->peekPublic()) !== null && $next->index < $end && $next->isHeading(2)) {
            $i = count($content['sections']);
            $content['sections'][] = [
                'heading' => $c->takeHeading(2, "{$code}.sections.{$i}.heading"),
                'blocks' => $this->blocks($c, "{$code}.sections.{$i}.blocks", $end),
            ];
        }
        if ($content['sections'] === []) {
            throw new ImportException($c->where()." {$code} has no H2 sections");
        }

        // ---------------------------------------------------------------- contact and cross-links
        while (($note = $c->findNote('/^'.$code.'-L\d{3} \|/', $seo)) !== null) {
            $link = $c->takeLink("{$code}.links.".count($content['links']));
            $content['links'][] = ['label' => $link['label'], 'target' => $this->target($c, $code, $note, $link['href'])];
        }

        // ---------------------------------------------------------------- metadata
        $c->expectNote('/^SEO title\b/');
        $page['seo_title'] = $c->take("{$code}.seo_title");
        $c->expectNote('/^Meta description\b/');
        $page['meta_description'] = $c->take("{$code}.meta_description");

        // A page-specific WhatsApp draft is optional; the lookup does not move the reading position.
        $resume = $c->position();
        if ($c->findNote('/^WhatsApp draft\b/') !== null) {
            $page['whatsapp_draft'] = $c->take("{$code}.whatsapp_draft");
        }
        $c->seek($resume);

        $page['content'] = $content;

        return $page;
    }

    /**
     * Paragraphs, bullet lists and numbered lists until the next heading, linked line or $end.
     *
     * @return list<array{type: string, text?: string, items?: list<string>}>
     */
    private function blocks(Cursor $c, string $field, int $end, bool $paragraphsOnly = false): array
    {
        $accept = fn (?Block $b) => $b !== null && $b->index < $end && $b->headingLevel === null && $b->links === []
            && (! $paragraphsOnly || (! $b->bullet && $b->number === null));
        $out = [];
        while ($accept($b = $c->peekPublic())) {
            $n = count($out);
            if (! $b->bullet && $b->number === null) {
                $out[] = ['type' => 'p', 'text' => $c->take("{$field}.{$n}")];

                continue;
            }
            $bullet = $b->bullet;
            $items = [];
            while ($accept($b = $c->peekPublic()) && ($bullet ? $b->bullet : $b->number !== null)) {
                $items[] = $c->take("{$field}.{$n}.items.".count($items));
            }
            $out[] = ['type' => $bullet ? 'ul' : 'ol', 'items' => $items];
        }

        return $out;
    }

    /** @return array<string, string> */
    private function cookieStrings(Cursor $c): array
    {
        $c->expectNote('/^Cookie and Privacy Preference Interface\b/');
        $ui = [];
        foreach (self::COOKIE_STRINGS as $key => $marker) {
            $c->expectNote($marker);
            $ui[$key] = $c->take('ui.'.$key);
        }

        // The banner and panel link back to this policy.
        $note = $c->expectNote('/^L01-L007 \|/');
        $link = $c->takeLink('ui.cookies.policy_link');
        $target = $this->target($c, 'L01', $note, $link['href']);
        if ($target !== 'L01') {
            throw new ImportException($c->where($note)." L01-L007 points to {$target}, expected the privacy policy L01");
        }
        $ui['cookies.policy_link'] = $link['label'];

        return $ui;
    }

    /**
     * Logical target of a red link marker: "-> P07: /en/#consultation-form." gives P07 and
     * "-> EMAIL: mailto:…" gives EMAIL. A black hyperlink that leads somewhere other than the
     * marker's fallback destination is reported as a source conflict.
     */
    private function target(Cursor $c, string $code, Block $note, string $href): string
    {
        $marker = preg_match('/^(\S+) \|/', $note->note, $id) === 1 ? $id[1] : '#'.$note->index;
        if (preg_match('/->\s*([A-Z][A-Z0-9]*(?:-[A-Z0-9]+)*)\s*:\s*(\S+?)\.?(?:\s|$)/', $note->note, $m) !== 1) {
            throw new ImportException($c->where($note)." {$marker} names no target ID");
        }
        if (preg_match('/^(?:mailto|tel):/', $m[2]) === 1) {
            $actual = $href;
        } else {
            $fragment = parse_url($href, PHP_URL_FRAGMENT);
            $actual = (string) parse_url($href, PHP_URL_PATH).(is_string($fragment) ? '#'.$fragment : '');
        }
        if ($actual !== $m[2]) {
            $this->report->add('source-conflict', "{$code} {$marker}", "hyperlink {$href} does not match the marker destination {$m[2]}", $c->locale());
        }

        return $m[1];
    }
}
