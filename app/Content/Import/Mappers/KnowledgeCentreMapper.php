<?php

namespace App\Content\Import\Mappers;

use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 03_Knowledge_Centre*.docx → P03 landing page copy, the guide search/filter strings, the three
 * guide categories (gc-01…gc-03) and the nine complete guides G01–G09 (sections of P03).
 *
 * Anchors on the English red markers shared by all three files ("Knowledge centre hero | H1.",
 * "Guide directory | H2.", "G0n card |", "Full guide G0n", "G0n substantive subsection n | H3.",
 * "Official references | H3." …) and on the numbered link IDs "P03-L001 |" … "P03-L081 |", taken
 * strictly in sequence. The "-> ID:" part of each link note names the destination: internal
 * targets are stored as that ID (P01, P05, S06, G02, WA01, knowledge-top), never as the /ar/…
 * fallback URLs; official references (R01–R18), telephone and email keep their real URL.
 */
final class KnowledgeCentreMapper extends Mapper
{
    private const CATEGORY = '/category label and guide cards \| H3\./';

    private const FINAL = '/^Final consultation section \| H2\./';

    /** Last P03-Lnnn link number taken; reset for every language. */
    private int $link = 0;

    /** @var array<string, string> guide code => registered anchor, from the card link notes */
    private array $anchors = [];

    /** @var array<string, string> related-link kind => first label seen (consistency report) */
    private array $relatedLabels = [];

    public function sources(): array
    {
        return ['kc' => '03_Knowledge_Centre'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['kc'];
        $this->link = 0;
        $this->anchors = [];
        $this->relatedLabels = [];
        $ui = [];
        $page = [];

        // ---------------------------------------------------------------- breadcrumb + hero
        $c->expectNote('/^Breadcrumbs\./');
        $page['breadcrumb'] = [
            $this->link($c, 'P03.breadcrumb.0', 'P01'),
            ['label' => $c->take('P03.breadcrumb.1'), 'target' => null],
        ];
        $c->expectNote('/^Knowledge centre hero \| H1\./');
        $page['h1'] = $c->takeHeading(1, 'P03.h1');
        $page['content']['lead'] = $c->take('P03.lead');
        $page['content']['intro'] = $c->take('P03.intro');

        // ---------------------------------------------------------------- how to use
        $c->expectNote('/^How to use the knowledge centre \| H2\./');
        $page['content']['how_to'] = [
            'heading' => $c->takeHeading(2, 'P03.how_to.heading'),
            'items' => $c->takeBullets('P03.how_to.items'),
        ];

        // ---------------------------------------------------------------- search + filter controls
        $c->expectNote('/^Guide search and category controls \| H2\./');
        $page['content']['find'] = ['heading' => $c->takeHeading(2, 'P03.find.heading')];
        $controls = [
            'resources.search.label' => 'Search field label',
            'resources.search.placeholder' => 'Search placeholder',
            'resources.topic.label' => 'Category field label',
            'resources.topic.all' => 'All category option',
            'resources.clear' => 'Clear filters button',
            'resources.no_results' => 'No results message',
        ];
        foreach ($controls as $key => $marker) {
            $c->expectNote('/^'.$marker.'\b/');
            $ui[$key] = $c->take('ui.'.$key);
        }

        // ---------------------------------------------------------------- directory: categories + cards
        $c->expectNote('/^Guide directory \| H2\./');
        $page['content']['directory'] = ['heading' => $c->takeHeading(2, 'P03.directory.heading')];
        $end = $this->indexOf($c, '/^FAQ and service navigation \| H2\./');
        $categories = [];
        $guides = [];
        while ($c->findNote(self::CATEGORY, $end) !== null) {
            $code = sprintf('gc-%02d', count($categories) + 1);
            $name = $c->takeHeading(3, "guide_categories.{$code}.name");
            $next = $c->indexOfNote(self::CATEGORY);
            $limit = $next !== null && $next < $end ? $next : $end;
            $members = [];
            while (($card = $c->findNote('/^G\d{2} card \|/', $limit)) !== null) {
                $g = substr($card->note, 0, 3);
                $title = $this->card($c, $g, $locale);
                $guides[$g] = [
                    'anchor' => $this->anchors[$g],
                    'category' => $code,
                    'position' => count($guides) + 1,
                    'card_title' => $title,
                    'card_summary' => $c->take("guides.{$g}.card_summary"),
                ];
                $members[] = $g;
            }
            $categories[$code] = ['position' => count($categories) + 1, 'name' => $name, 'guides' => $members];
        }
        if ($categories === [] || $guides === []) {
            throw new ImportException($c->where().' the guide directory has no categories or cards');
        }

        // ---------------------------------------------------------------- FAQ and service navigation
        $c->expectNote('/^FAQ and service navigation \| H2\./');
        $page['content']['faq_services'] = [
            'heading' => $c->takeHeading(2, 'P03.faq_services.heading'),
            'text' => $c->take('P03.faq_services.text'),
            'links' => $this->links($c, 'P03.faq_services.links', $this->indexOf($c, '/^Full guide G\d{2}\b/')),
        ];

        // ---------------------------------------------------------------- nine complete guides
        $final = $this->indexOf($c, self::FINAL);
        $order = [];
        while (($start = $c->findNote('/^Full guide G\d{2}\b/', $final)) !== null) {
            $g = substr($start->note, strlen('Full guide '), 3);
            if (! isset($guides[$g])) {
                throw new ImportException($c->where($start)." full guide {$g} has no directory card");
            }
            $guides[$g]['body'] = $this->guide($c, $g, $locale);
            $order[] = $g;
        }
        $missing = array_diff(array_keys($guides), $order);
        if ($missing !== []) {
            throw new ImportException($c->where().' no full guide for '.implode(', ', $missing));
        }
        if ($order !== array_keys($guides)) {
            $this->report->add('source-conflict', 'P03 guide order', 'Full guides appear as '.implode(', ', $order).' but the directory cards as '.implode(', ', array_keys($guides)), $locale);
        }

        // ---------------------------------------------------------------- final consultation section
        $c->expectNote(self::FINAL);
        $consultation = [
            'heading' => $c->takeHeading(2, 'P03.consultation.heading'),
            'text' => $c->take('P03.consultation.text'),
            'cta' => $this->link($c, 'P03.consultation.cta', 'P07'),
        ];
        $wa = $this->registered($c, 'P03.consultation.whatsapp');
        if ($wa['id'] !== 'WA01') {
            throw new ImportException($c->where()." P03.consultation.whatsapp should link to WA01, the note names {$wa['id']}");
        }
        $consultation['whatsapp'] = ['label' => $wa['label'], 'target' => 'WA01'];
        $consultation['phone'] = $this->link($c, 'P03.consultation.phone', 'TEL');
        $consultation['email'] = $this->link($c, 'P03.consultation.email', 'EMAIL');
        $consultation['notice'] = $c->take('P03.consultation.notice');
        $page['content']['consultation'] = $consultation;

        // ---------------------------------------------------------------- visible legal scope
        $c->expectNote('/^Visible legal scope \| H2\./');
        $page['content']['scope'] = [
            'heading' => $c->takeHeading(2, 'P03.scope.heading'),
            'paragraphs' => $c->takeParagraphs('P03.scope.paragraphs'),
            'link' => $this->link($c, 'P03.scope.link', 'P08'),
        ];

        // ---------------------------------------------------------------- WhatsApp draft + metadata
        $c->expectNote('/^WhatsApp implementation\./');
        $page['whatsapp_draft'] = $c->take('P03.whatsapp_draft');
        if ($this->whatsappDraft($wa['href']) !== $page['whatsapp_draft']) {
            $this->report->add('source-conflict', 'P03 WA01', 'The encoded WhatsApp link does not decode to the black draft text', $locale);
        }
        $c->expectNote('/^SEO title\./');
        $page['seo_title'] = $c->take('P03.seo_title');
        $c->expectNote('/^Meta description\./');
        $page['meta_description'] = $c->take('P03.meta_description');

        $left = $c->indexOfNote('/^P03-L\d{3} \|/');
        if ($left !== null) {
            throw new ImportException($c->where().' link note #'.$left.' was never taken');
        }
        $this->reportArabicSources($guides, $locale);

        return [
            'ui_strings' => $ui,
            'pages' => ['P03' => $page],
            'guide_categories' => $categories,
            'guides' => $guides,
        ];
    }

    // ------------------------------------------------------------------ guide sections

    /** Directory card: the linked title registers the guide's anchor (P03-L002 … P03-L010). */
    private function card(Cursor $c, string $g, string $locale): string
    {
        $l = $this->registered($c, "guides.{$g}.card_title");
        if ($l['id'] !== $g) {
            throw new ImportException($c->where()." card {$g} links to {$l['id']}");
        }
        $anchor = str_contains($l['dest'], '#') ? substr($l['dest'], strrpos($l['dest'], '#') + 1) : '';
        if (preg_match('/^[a-z0-9-]+$/', $anchor) !== 1) {
            throw new ImportException($c->where()." card {$g} names no anchor");
        }
        if (parse_url($l['href'], PHP_URL_FRAGMENT) !== $anchor) {
            $this->report->add('source-conflict', "guides.{$g}.anchor", "Card note names #{$anchor} but the card title links to {$l['href']}", $locale);
        }
        $this->anchors[$g] = $anchor;

        return $l['label'];
    }

    /**
     * One complete guide, in source order: H2 title, introduction, intended readers, the
     * substantive H3 subsections, further points, preparation steps, documents, common mistakes,
     * visible FAQ (H4 questions), official references and related links.
     *
     * @return array<string, mixed>
     */
    private function guide(Cursor $c, string $g, string $locale): array
    {
        $f = "guides.{$g}.body";
        $section = $c->expectNote('/^Website section of P03\b/');
        if (preg_match('/Set id="([a-z0-9-]+)"/', $section->note, $m) === 1 && $m[1] !== $this->anchors[$g]) {
            $this->report->add('source-conflict', "guides.{$g}.anchor", "Card anchor #{$this->anchors[$g]} differs from the section id #{$m[1]}", $locale);
        }

        $c->expectNote('/ guide title \| H2\./');
        $body = [
            'title' => $c->takeHeading(2, "{$f}.title"),
            'intro' => $this->blocks($c, "{$f}.intro"),
        ];

        $c->expectNote('/^Intended readers \| H3\./');
        $body['audience'] = $this->section($c, "{$f}.audience");

        $limit = $this->indexOf($c, '/^Additional practical considerations \| H3\./');
        $body['sections'] = [];
        while ($c->findNote('/^'.$g.' substantive subsection \d+ \| H3\./', $limit) !== null) {
            $body['sections'][] = $this->section($c, "{$f}.sections.".count($body['sections']));
        }

        $c->expectNote('/^Additional practical considerations \| H3\./');
        $body['considerations'] = $this->section($c, "{$f}.considerations");

        $c->expectNote('/^File preparation steps \| H3\./');
        $body['steps'] = [
            'heading' => $c->takeHeading(3, "{$f}.steps.heading"),
            'items' => $c->takeNumbered("{$f}.steps.items"),
        ];

        $c->expectNote('/^Documents for review \| H3\./');
        $body['documents'] = [
            'heading' => $c->takeHeading(3, "{$f}.documents.heading"),
            'items' => $c->takeBullets("{$f}.documents.items"),
        ];

        $c->expectNote('/^Common mistakes \| H3\./');
        $body['mistakes'] = [
            'heading' => $c->takeHeading(3, "{$f}.mistakes.heading"),
            'items' => $c->takeBullets("{$f}.mistakes.items"),
        ];

        $c->expectNote('/^Visible guide FAQ \| H3\./');
        $faqs = ['heading' => $c->takeHeading(3, "{$f}.faqs.heading"), 'items' => []];
        $limit = $this->indexOf($c, '/^Official references \| H3\./');
        while ($c->findNote('/^'.$g.' FAQ question \d+ \| H4\./', $limit) !== null) {
            $i = count($faqs['items']);
            $faqs['items'][] = [
                'question' => $c->takeHeading(4, "{$f}.faqs.items.{$i}.question"),
                'answer' => $c->take("{$f}.faqs.items.{$i}.answer"),
            ];
        }
        $body['faqs'] = $faqs;

        $c->expectNote('/^Official references \| H3\./');
        $body['sources'] = [
            'heading' => $c->takeHeading(3, "{$f}.sources.heading"),
            'blocks' => $this->blocks($c, "{$f}.sources.blocks"),
            'items' => $this->links($c, "{$f}.sources.items", $this->indexOf($c, '/^Contextual links and enquiry \| H3\./')),
        ];

        $c->expectNote('/^Contextual links and enquiry \| H3\./');
        $body['related'] = [
            'heading' => $c->takeHeading(3, "{$f}.related.heading"),
            'items' => $this->links($c, "{$f}.related.items", $this->indexOf($c, '/^Full guide G\d{2}\b|^Final consultation section \| H2\./')),
        ];
        foreach ($body['related']['items'] as $item) {
            $this->checkRelatedLabel($g, $item, $locale);
        }

        return $body;
    }

    /** @return array{heading: string, blocks: list<array<string, mixed>>} */
    private function section(Cursor $c, string $field): array
    {
        return [
            'heading' => $c->takeHeading(3, "{$field}.heading"),
            'blocks' => $this->blocks($c, "{$field}.blocks"),
        ];
    }

    /**
     * Prose up to the next heading or linked label, in source order. A paragraph becomes
     * {type: p, text}; consecutive bullets or numbered items become {type: ul|ol, items}.
     *
     * @return list<array{type: string, text?: string, items?: list<string>}>
     */
    private function blocks(Cursor $c, string $field): array
    {
        $out = [];
        while (($next = $c->peekPublic()) !== null && $next->headingLevel === null && $next->links === []) {
            $b = $c->nextPublic();
            $type = $b->bullet ? 'ul' : ($b->number !== null ? 'ol' : 'p');
            $last = array_key_last($out);
            if ($type !== 'p' && $last !== null && $out[$last]['type'] === $type) {
                $c->consume($b, "{$field}.{$last}.items.".count($out[$last]['items']));
                $out[$last]['items'][] = $b->text;
            } elseif ($type === 'p') {
                $c->consume($b, "{$field}.".count($out));
                $out[] = ['type' => 'p', 'text' => $b->text];
            } else {
                $c->consume($b, "{$field}.".count($out).'.items.0');
                $out[] = ['type' => $type, 'items' => [$b->text]];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ registered links

    /**
     * Every registered link (P03-Lnnn) whose note comes before block index $before.
     *
     * @return list<array<string, string>>
     */
    private function links(Cursor $c, string $field, int $before): array
    {
        $out = [];
        while (($i = $c->indexOfNote('/^P03-L\d{3} \|/')) !== null && $i < $before) {
            $out[] = $this->link($c, "{$field}.".count($out));
        }

        return $out;
    }

    /**
     * The next registered link as stored content: {label, target} for internal destinations,
     * {label, url, ref} for official references (R01–R18), {label, url} for telephone and email.
     *
     * @return array<string, string>
     */
    private function link(Cursor $c, string $field, ?string $expect = null): array
    {
        $l = $this->registered($c, $field);
        if ($expect !== null && $l['id'] !== $expect) {
            throw new ImportException($c->where()." {$field} should link to {$expect}, the note names {$l['id']}");
        }
        $external = preg_match('/^R\d{2}$/', $l['id']) === 1 || in_array($l['id'], ['TEL', 'EMAIL'], true);
        if ($external) {
            if (rawurldecode($l['dest']) !== rawurldecode($l['href'])) {
                $this->report->add('source-conflict', "P03 {$l['code']}", "The note names {$l['dest']} but the label links to {$l['href']}", $c->locale());
            }

            return str_starts_with($l['id'], 'R')
                ? ['label' => $l['label'], 'url' => $l['href'], 'ref' => $l['id']]
                : ['label' => $l['label'], 'url' => $l['href']];
        }

        $expected = $this->anchors[$l['id']] ?? ($l['fragment'] ? $l['id'] : null);
        if ($expected !== null && parse_url($l['href'], PHP_URL_FRAGMENT) !== $expected) {
            $this->report->add('source-conflict', "P03 {$l['code']}", "Link to {$l['id']} should target #{$expected} but links to {$l['href']}", $c->locale());
        }

        return ['label' => $l['label'], 'target' => $l['id']];
    }

    /**
     * Take the next "P03-Lnnn | … -> ID: destination." note and the black label it describes.
     *
     * @return array{code: string, id: string, fragment: bool, dest: string, label: string, href: string}
     */
    private function registered(Cursor $c, string $field): array
    {
        $code = sprintf('P03-L%03d', ++$this->link);
        $note = $c->expectNote('/^'.$code.' \|/');
        if (preg_match('/->\s*(#?)([A-Za-z0-9-]+):\s*(\S*)/', $note->note, $m) !== 1) {
            throw new ImportException($c->where($note)." {$code} names no destination ID");
        }
        $link = $c->takeLink($field);

        return [
            'code' => $code,
            'id' => $m[2],
            'fragment' => $m[1] === '#',
            'dest' => rtrim($m[3], '.'),
            'label' => $link['label'],
            'href' => $link['href'],
        ];
    }

    // ------------------------------------------------------------------ checks

    /** The repeated related-link labels (service, FAQ, consultation, back to top) should not drift. */
    private function checkRelatedLabel(string $g, array $item, string $locale): void
    {
        $target = $item['target'] ?? '';
        $kind = match (true) {
            preg_match('/^S\d{2}$/', $target) === 1 => 'service',
            in_array($target, ['P05', 'P07', 'knowledge-top'], true) => $target,
            default => null,
        };
        if ($kind === null) {
            return;
        }
        $this->relatedLabels[$kind] ??= $item['label'];
        if ($this->relatedLabels[$kind] !== $item['label']) {
            $this->report->add('note', "guides.{$g}.body.related", "Related {$kind} label differs from the first guide: {$item['label']}", $locale);
        }
    }

    /** Official references that point to Arabic-language pages from the English or Russian file. */
    private function reportArabicSources(array $guides, string $locale): void
    {
        if ($locale === 'ar') {
            return;
        }
        $refs = [];
        foreach ($guides as $guide) {
            foreach ($guide['body']['sources']['items'] as $item) {
                if (isset($item['url']) && preg_match('#^https?://[^/]+/ar/#', $item['url']) === 1) {
                    $refs[$item['ref'] ?? $item['url']] = true;
                }
            }
        }
        if ($refs !== []) {
            $this->report->add('note', 'P03 official references', 'These official references link to Arabic-language pages (/ar/) in this language file and are kept as supplied: '.implode(', ', array_keys($refs)), $locale);
        }
    }

    private function indexOf(Cursor $c, string $pattern): int
    {
        return $c->indexOfNote($pattern) ?? throw new ImportException($c->where()." expected a designer note matching {$pattern}");
    }
}
