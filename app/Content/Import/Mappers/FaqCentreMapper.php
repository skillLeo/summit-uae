<?php

namespace App\Content\Import\Mappers;

use App\Content\Docx\Block;
use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 05_FAQ_Centre*.docx → P05 page copy, the FAQ categories (cat-01…cat-12), the questions
 * (faq-01…faq-74) and the FAQ interface strings (faq.*).
 *
 * Anchors on the English red markers that exist in all three files ("Page hero |",
 * "FAQ search controls |", "Category NN |", "FAQ-NN |", "P05-Lnnn |" and the interface role notes
 * "Input label.", "Reset button." …). Category and question IDs are the id="cat-NN" / id="faq-NN"
 * stated in those notes. Link targets are the IDs of the red "-> ID:" destinations (P07, S01, G02,
 * A04, cat-01 …), never the /ar/ fallback URLs; official references and the WhatsApp, telephone
 * and email handlers keep their real hyperlinks.
 */
final class FaqCentreMapper extends Mapper
{
    /** FAQ interface strings, keyed by the red role note that precedes each one. */
    private const UI = [
        'faq.search_label' => '/^Input label\b/',
        'faq.search_placeholder' => '/^Input placeholder\b/',
        'faq.search_submit' => '/^Submit button\b/',
        'faq.search_reset' => '/^Reset button\b/',
        'faq.no_results' => '/^No results message\b/',
        'faq.expand_all' => '/^Expand all button\b/',
        'faq.collapse_all' => '/^Collapse all button\b/',
        'faq.copy_link' => '/^Copy question link button\b/',
        'faq.link_copied' => '/^Successful copy live message\b/',
        'faq.copy_failed' => '/^Copy failure live message\b/',
    ];

    /** Contact handlers have no page route, so their real hyperlink is the target. */
    private const HANDLERS = ['WA01' => 'https://wa.me/', 'TEL' => 'tel:', 'EMAIL' => 'mailto:'];

    private const CATEGORY = '/^Category (\d{2}) \|/';

    private const QUESTION = '/^FAQ-(\d{2}) \|/';

    private const LINK = '/^P05-L\d{3} \|/';

    private const RELATED = '/^Related links\b/';

    private const CONSULTATION = '/^Final consultation section \|/';

    /** @var array<string, string> link ID => official URL, for the current locale */
    private array $official = [];

    public function sources(): array
    {
        return ['faq' => '05_FAQ_Centre'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['faq'];
        $this->official = [];
        $ui = [];
        $page = [];
        $categories = [];
        $faqs = [];

        // ---------------------------------------------------------------- breadcrumb + hero
        $page['breadcrumb'] = [
            $this->link($c, '/^P05-L001 \|/', 'P05.breadcrumb.0', 'P01'),
            ['label' => $c->take('P05.breadcrumb.1')],
        ];
        $c->expectNote('/^Page hero \|/');
        $page['h1'] = $c->takeHeading(1, 'P05.h1');
        $page['content']['hero'] = [
            'lead' => $c->take('P05.hero.lead'),
            'paragraphs' => $c->takeParagraphs('P05.hero.paragraphs', '/^P05-L002 \|/'),
            'cta' => $this->link($c, '/^P05-L002 \|/', 'P05.hero.cta', 'P07'),
        ];

        // ---------------------------------------------------------------- search controls
        $c->expectNote('/^FAQ search controls \|/');
        $page['content']['search'] = ['heading' => $c->takeHeading(2, 'P05.search.heading')];
        foreach (self::UI as $key => $role) {
            $c->expectNote($role);
            $ui[$key] = $c->take('ui.'.$key);
        }

        // ---------------------------------------------------------------- category jump menu
        $c->expectNote('/jump menu \|/');
        $menu = ['heading' => $c->takeHeading(2, 'P05.category_menu.heading'), 'items' => []];
        $end = $c->indexOfNote(self::CATEGORY);
        while (($note = $c->findNote(self::LINK, $end)) !== null) {
            $menu['items'][] = $this->linkAfter($c, $note, 'P05.category_menu.items.'.count($menu['items']));
        }
        $page['content']['category_menu'] = $menu;

        // ---------------------------------------------------------------- categories, questions, related links
        $stated = [];
        $members = [];
        $back = null;
        while (($note = $c->findNote(self::CATEGORY, $c->indexOfNote(self::CONSULTATION))) !== null) {
            preg_match(self::CATEGORY, $note->note, $m);
            $code = $this->anchor($note, 'cat', (int) $m[1], $locale);
            if (isset($categories[$code])) {
                throw new ImportException($c->where($note)." duplicate category {$code}");
            }
            $categories[$code] = [
                'anchor' => $code,
                'position' => (int) $m[1],
                'name' => $c->takeHeading(2, "faq_categories.{$code}.name"),
            ];
            if (preg_match('/FAQ-(\d{2}) through FAQ-(\d{2})/', $note->note, $r)) {
                $stated[$code] = range((int) $r[1], (int) $r[2]);
            }

            $members[$code] = [];
            $end = $this->boundary($c, [self::RELATED, self::CATEGORY, self::CONSULTATION]);
            while (($q = $c->findNote(self::QUESTION, $end)) !== null) {
                preg_match(self::QUESTION, $q->note, $m);
                $number = (int) $m[1];
                $faq = $this->anchor($q, 'faq', $number, $locale);
                if (isset($faqs[$faq])) {
                    throw new ImportException($c->where($q)." duplicate question {$faq}");
                }
                $question = $c->takeHeading(3, "faqs.{$faq}.question");
                if (! preg_match('/^(\d+)\.\s/u', $question, $n) || (int) $n[1] !== $number) {
                    $this->report->add('source-conflict', "faqs.{$faq}", "the question heading is not numbered {$number}: {$question}", $locale);
                }
                $faqs[$faq] = [
                    'anchor' => $faq,
                    'category' => $code,
                    'position' => $number,
                    'question' => $question,
                    'answer' => $this->answer($c, "faqs.{$faq}.answer"),
                ];
                $members[$code][] = $number;
            }

            // Contextual links beneath the answers. The closing "back to categories" link repeats
            // in every category and becomes one interface string.
            $categories[$code]['links'] = [];
            $end = $this->boundary($c, [self::CATEGORY, self::CONSULTATION]);
            while (($l = $c->findNote(self::LINK, $end)) !== null) {
                if ($this->destination($c, $l) === ['fragment', 'faq-top']) {
                    $label = $c->takeLink('ui.faq.back_to_categories')['label'];
                    $back ??= $label;
                    if ($label !== $back) {
                        $this->report->add('note', "ui.faq.back_to_categories ({$code})", 'label differs from the first category: '.$label, $locale);
                    }

                    continue;
                }
                $categories[$code]['links'][] = $this->linkAfter($c, $l, "faq_categories.{$code}.links.".count($categories[$code]['links']));
            }
        }
        if ($categories === [] || $faqs === []) {
            throw new ImportException($c->where().' no FAQ categories or questions found');
        }
        if ($back !== null) {
            $ui['faq.back_to_categories'] = $back;
        }

        foreach ($members as $code => $numbers) {
            if (isset($stated[$code]) && $numbers !== $stated[$code]) {
                $this->report->add('source-conflict', "faq_categories.{$code}", 'the category note states FAQ-'.$this->span($stated[$code]).'; the file contains FAQ-'.$this->span($numbers), $locale);
            }
        }
        $positions = array_column($faqs, 'position');
        if ($positions !== range(1, count($faqs))) {
            $this->report->add('source-conflict', 'faqs', 'question numbers are not 1…'.count($faqs).' in source order: '.implode(', ', $positions), $locale);
        }
        $this->report->add('note', 'P05 questions', 'The approved H3 question headings include their own sequence number ("1." …); render them verbatim without adding a list counter.', $locale);
        $targets = array_column($menu['items'], 'target');
        if ($targets !== array_keys($categories)) {
            $this->report->add('source-conflict', 'P05.category_menu', 'jump-menu targets ('.implode(', ', $targets).') do not match the categories in page order ('.implode(', ', array_keys($categories)).')', $locale);
        }
        foreach ($menu['items'] as $item) {
            $name = $categories[$item['target']]['name'] ?? null;
            if ($name !== null && $name !== $item['label']) {
                $this->report->add('note', "P05.category_menu {$item['target']}", "menu label \"{$item['label']}\" differs from the category heading \"{$name}\"", $locale);
            }
        }

        // ---------------------------------------------------------------- closing consultation section
        $c->expectNote(self::CONSULTATION);
        $page['content']['consultation'] = [
            'heading' => $c->takeHeading(2, 'P05.consultation.heading'),
            'paragraphs' => $c->takeParagraphs('P05.consultation.paragraphs', self::LINK),
            'cta' => $this->link($c, '/^P05-L060 \|/', 'P05.consultation.cta', 'P07'),
            'whatsapp' => $this->link($c, '/^P05-L061 \|/', 'P05.consultation.whatsapp', 'WA01'),
            'phone' => $this->link($c, '/^P05-L062 \|/', 'P05.consultation.phone', 'TEL'),
            'email' => $this->link($c, '/^P05-L063 \|/', 'P05.consultation.email', 'EMAIL'),
        ];

        // ---------------------------------------------------------------- visible legal scope
        $c->expectNote('/^Visible legal scope \|/');
        $scope = [
            'heading' => $c->takeHeading(2, 'P05.scope.heading'),
            'paragraphs' => $c->takeParagraphs('P05.scope.paragraphs', self::LINK),
            'links' => [],
        ];
        $end = $c->indexOfNote('/^WhatsApp\./');
        while (($note = $c->findNote(self::LINK, $end)) !== null) {
            $scope['links'][] = $this->linkAfter($c, $note, 'P05.scope.links.'.count($scope['links']));
        }
        $page['content']['scope'] = $scope;

        // ---------------------------------------------------------------- WhatsApp draft + metadata
        $c->expectNote('/^WhatsApp\./');
        $page['whatsapp_draft'] = $c->take('P05.whatsapp_draft');
        if ($this->whatsappDraft($page['content']['consultation']['whatsapp']['target']) !== $page['whatsapp_draft']) {
            $this->report->add('source-conflict', 'WA01', 'The encoded P05 WhatsApp link does not decode to the black draft text', $locale);
        }
        $c->expectNote('/^SEO title\b/');
        $page['seo_title'] = $c->take('P05.seo_title');
        $c->expectNote('/^Meta description\b/');
        $page['meta_description'] = $c->take('P05.meta_description');

        // ---------------------------------------------------------------- red appendix cross-checks
        // The designer's category map and official reference map are compared with what was
        // mapped above, so a disagreement inside the handoff is reported rather than guessed.
        while (($note = $c->findNote('/^cat-\d{2} \|/')) !== null) {
            if (preg_match('/^(cat-\d{2}) \|.*?FAQ-(\d{2}) through FAQ-(\d{2})/', $note->note, $m)
                && ($members[$m[1]] ?? []) !== range((int) $m[2], (int) $m[3])) {
                $this->report->add('source-conflict', "faq_categories.{$m[1]}", "the implementation map states FAQ-{$m[2]} through FAQ-{$m[3]}; the file contains FAQ-".$this->span($members[$m[1]] ?? []), $locale);
            }
        }
        $references = [];
        while (($note = $c->findNote('/^R\d{2} \| Category cat-\d{2} \|/')) !== null) {
            if (preg_match('/^R\d{2} \| Category (cat-\d{2}) \|.*\|\s*(https?:\/\/\S+)$/', $note->note, $m)) {
                $references[$m[1]][] = $m[2];
            }
        }
        if ($references !== []) {
            foreach ($categories as $code => $category) {
                $external = array_values(array_filter(array_column($category['links'], 'target'), fn (string $t) => str_starts_with($t, 'http')));
                $expected = $references[$code] ?? [];
                sort($external);
                sort($expected);
                if ($external !== $expected) {
                    $this->report->add('source-conflict', "faq_categories.{$code}.links", 'official links ('.implode(', ', $external).') differ from the reference map ('.implode(', ', $expected).')', $locale);
                }
            }
        }

        // Official URLs stay exactly as supplied; a reviewer should still see which open in another language.
        $foreign = [];
        foreach ($this->official as $id => $url) {
            if (preg_match('#^https?://[^/]+/(ar|en|ru)(?:/|$)#i', $url, $m) && strtolower($m[1]) !== $locale) {
                $foreign[strtolower($m[1])][] = $id;
            }
        }
        foreach ($foreign as $language => $ids) {
            $this->report->add('note', 'P05 official references', 'These official references link to '.strtoupper($language)."-language pages (/{$language}/) in this language file and are kept as supplied: ".implode(', ', $ids), $locale);
        }

        return [
            'ui_strings' => $ui,
            'pages' => ['P05' => $page],
            'faq_categories' => $categories,
            'faqs' => $faqs,
        ];
    }

    /**
     * Answer blocks after a question, up to the next question, related-links or category note.
     *
     * @return list<array{type: string, text?: string, items?: list<string>}>
     */
    private function answer(Cursor $c, string $field): array
    {
        $stop = $this->boundary($c, [self::QUESTION, self::RELATED, self::CATEGORY, self::CONSULTATION]);
        $limit = $stop === null ? PHP_INT_MAX : $c->doc->blocks[$stop]->index;
        $out = [];
        foreach ($c->takeWhile(fn (Block $b) => $b->index < $limit && $b->headingLevel === null, $field) as $b) {
            if ($b->links !== []) {
                throw new ImportException($c->where($b)." {$field} contains a hyperlink; the answer shape has no place for it");
            }
            $type = $b->bullet ? 'ul' : ($b->number !== null ? 'ol' : 'p');
            $last = array_key_last($out);
            if ($type === 'p') {
                $out[] = ['type' => 'p', 'text' => $b->text];
            } elseif ($last !== null && $out[$last]['type'] === $type) {
                $out[$last]['items'][] = $b->text;
            } else {
                $out[] = ['type' => $type, 'items' => [$b->text]];
            }
        }
        if ($out === []) {
            throw new ImportException($c->where()." {$field} is empty");
        }

        return $out;
    }

    /**
     * Expect the red link note matching $marker and take the linked black label after it.
     *
     * @return array{label: string, target: string}
     */
    private function link(Cursor $c, string $marker, string $field, string $expect): array
    {
        $note = $c->expectNote($marker);
        $destination = $this->destination($c, $note)[1];
        if ($destination !== $expect) {
            $this->report->add('source-conflict', strtok($note->note, ' '), "{$field} is expected to link to {$expect}; the note points to {$destination}", $c->locale());
        }

        return $this->linkAfter($c, $note, $field);
    }

    /** @return array{label: string, target: string} */
    private function linkAfter(Cursor $c, Block $note, string $field): array
    {
        $link = $c->takeLink($field);

        return ['label' => $link['label'], 'target' => $this->target($c, $note, $link['href'])];
    }

    /**
     * The destination written in a red link note: "-> S01: /ar/…" (id), "-> #cat-01" or
     * "-> /ar/faq/#faq-top" (fragment), "-> https://…" (url).
     *
     * @return array{0: string, 1: string}
     */
    private function destination(Cursor $c, Block $note): array
    {
        if (! preg_match('/->\s*(\S+)/u', $note->note, $m)) {
            throw new ImportException($c->where($note).' link note has no "->" destination: '.$note->note);
        }
        if (preg_match('/^([A-Z][A-Z0-9]*):$/', $m[1], $id)) {
            return ['id', $id[1]];
        }
        $destination = rtrim($m[1], '.');
        if (preg_match('#^https?://#i', $destination)) {
            return ['url', $destination];
        }
        if (preg_match('/#([A-Za-z0-9_-]+)$/', $destination, $fragment)) {
            return ['fragment', $fragment[1]];
        }
        throw new ImportException($c->where($note).' unrecognised link destination: '.$m[1]);
    }

    /** Stored target: the site-map ID or page anchor; the real URL for official references and contact handlers. */
    private function target(Cursor $c, Block $note, string $href): string
    {
        [$kind, $value] = $this->destination($c, $note);
        $subject = strtok($note->note, ' ');
        if ($kind === 'id' && isset(self::HANDLERS[$value])) {
            $stated = preg_match('/->\s*'.$value.':\s+((?:tel|mailto):\S+?)\.?(?:\s|$)/', $note->note, $m) ? $m[1] : null;
            if (! str_starts_with($href, self::HANDLERS[$value]) || ($stated !== null && $stated !== $href)) {
                $this->report->add('source-conflict', $subject, "{$value} hyperlink {$href} does not match the note", $c->locale());
            }

            return $href;
        }
        if ($kind === 'url') {
            if ($value !== $href) {
                $this->report->add('source-conflict', $subject, "the note gives {$value}; the embedded hyperlink is {$href}", $c->locale());
            }
            $this->official[$subject] = $href;

            return $href;
        }

        return $value;
    }

    /** The id="cat-NN" / id="faq-NN" stated in the red note, or the source-order number when absent. */
    private function anchor(Block $note, string $prefix, int $number, string $locale): string
    {
        if (preg_match('/id="('.$prefix.'-\d{2})"/', $note->note, $m)) {
            return $m[1];
        }
        $code = sprintf('%s-%02d', $prefix, $number);
        $this->report->add('note', $code, "no id=\"{$prefix}-NN\" in the red note; numbered in source order", $locale);

        return $code;
    }

    /**
     * Index of the nearest following note matching any of the patterns, or null.
     *
     * @param  list<string>  $patterns
     */
    private function boundary(Cursor $c, array $patterns): ?int
    {
        $found = array_filter(array_map(fn (string $p) => $c->indexOfNote($p), $patterns), fn (?int $i) => $i !== null);

        return $found === [] ? null : min($found);
    }

    /** @param list<int> $numbers */
    private function span(array $numbers): string
    {
        return $numbers === [] ? 'none' : sprintf('%02d through FAQ-%02d', min($numbers), max($numbers)).(count($numbers) !== max($numbers) - min($numbers) + 1 ? ' (with gaps)' : '');
    }
}
