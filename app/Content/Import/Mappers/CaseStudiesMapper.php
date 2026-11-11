<?php

namespace App\Content\Import\Mappers;

use App\Content\Docx\Block;
use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 04_Case_Studies*.docx → P04 page copy and the six full case stories (case-01 … case-06).
 *
 * Anchors on the English red markers that exist in all three files: section markers ending in
 * "| H1." / "| H2." / "| H3.", "Case 0n", id="case-0n" and the "P04-Lnnn |" link markers. A link's
 * target is the ID named after "->" in its marker (P01, S01, G03, P07, case-01, cases-top, WA01,
 * TEL, EMAIL …), never the Arabic fallback URL of the black hyperlink. The current breadcrumb
 * crumb has no marker and therefore no target (null): it is plain text.
 *
 * Story layout follows the designer's "Story layout" note: title, subject line, opening
 * paragraphs, substantive H3 sections, outcome, practical takeaway. The outcome is the last H3
 * section before the "Practical takeaway" marker and keeps every paragraph, including its limits.
 */
final class CaseStudiesMapper extends Mapper
{
    private const CASES = ['case-01', 'case-02', 'case-03', 'case-04', 'case-05', 'case-06'];

    /** "P04-L008 | Debt recovery and cheque cases -> S01: /ar/practice-areas/…. Same tab." */
    private const LINK = '/^P04-L\d{3} \|/';

    private const H2 = '/\| H2\.$/';

    private const H3 = '/\| H3\.$/';

    private const TAKEAWAY = '/^Practical takeaway \|/';

    /** Directory anchor on the page H1 that every story's back link returns to. */
    private const TOP = 'cases-top';

    public function sources(): array
    {
        return ['cases' => '04_Case_Studies'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['cases'];
        $page = [];
        $cases = [];

        // ---------------------------------------------------------------- breadcrumb + hero
        $c->expectNote('/^Breadcrumb\b/');
        $home = $this->linkTo($c, 'P01', 'P04.breadcrumb.0');
        $page['breadcrumb'] = [
            $home,
            ['label' => $this->text($c, 'P04.breadcrumb.1'), 'target' => null],
        ];
        $c->expectNote('/hero \| H1\./');
        $page['h1'] = $c->takeHeading(1, 'P04.h1');
        $page['content']['intro'] = [
            'lead' => $this->text($c, 'P04.intro.lead'),
            'paragraphs' => $this->paragraphs($c, 'P04.intro.paragraphs'),
        ];

        // ---------------------------------------------------------------- directory cards
        $c->expectNote('/directory cards \| H2\./');
        $page['content']['directory'] = ['heading' => $c->takeHeading(2, 'P04.directory.heading')];
        foreach (self::CASES as $i => $code) {
            $card = $this->linkTo($c, $code, "case_studies.{$code}.card_title");
            $cases[$code] = [
                'anchor' => $code,
                'position' => $i + 1,
                'card_title' => $card['label'],
                'card_summary' => $this->text($c, "case_studies.{$code}.card_summary"),
            ];
        }

        // ---------------------------------------------------------------- six full stories
        $backLabel = null;
        foreach (self::CASES as $i => $code) {
            $story = $this->story($c, $code, $i + 1);
            $cases[$code] += $story;
            if ($story['title'] !== $cases[$code]['card_title']) {
                $this->report->add('label-mismatch', "case_studies.{$code}", 'Directory card title differs from the story H2: '.$cases[$code]['card_title'], $locale);
            }
            $backLabel ??= $story['body']['back_link']['label'];
            if ($story['body']['back_link']['label'] !== $backLabel) {
                $this->report->add('note', "case_studies.{$code}", 'Back-to-directory label differs from the first story: '.$story['body']['back_link']['label'], $locale);
            }
        }

        // ---------------------------------------------------------------- page FAQ
        $c->expectNote('/FAQ \| H2\./');
        $faq = ['heading' => $c->takeHeading(2, 'P04.faq.heading'), 'items' => []];
        $faqEnd = $c->indexOfNote(self::LINK);
        while ($c->findNote('/^FAQ question \d+ \|/', $faqEnd) !== null) {
            $k = count($faq['items']);
            $faq['items'][] = [
                'question' => $c->takeHeading(3, "P04.faq.items.{$k}.question"),
                'answer' => $this->text($c, "P04.faq.items.{$k}.answer"),
            ];
        }
        if ($faq['items'] === []) {
            throw new ImportException($c->where().' P04 FAQ has no questions');
        }
        $faq['link'] = $this->linkTo($c, 'P05', 'P04.faq.link');
        $page['content']['faq'] = $faq;

        // ---------------------------------------------------------------- final consultation section
        $c->expectNote('/consultation section \| H2\./');
        $cta = [
            'heading' => $c->takeHeading(2, 'P04.cta.heading'),
            'body' => $this->text($c, 'P04.cta.body'),
            'consultation' => $this->linkTo($c, 'P07', 'P04.cta.consultation'),
        ];
        $waHref = '';
        $cta['whatsapp'] = $this->linkTo($c, 'WA01', 'P04.cta.whatsapp', $waHref);
        $cta['phone'] = $this->linkTo($c, 'TEL', 'P04.cta.phone');
        $cta['email'] = $this->linkTo($c, 'EMAIL', 'P04.cta.email');
        $cta['notice'] = $this->text($c, 'P04.cta.notice');
        $page['content']['cta'] = $cta;

        // ---------------------------------------------------------------- publication and professional scope
        $c->expectNote('/professional scope \| H2\./');
        $page['content']['scope'] = [
            'heading' => $c->takeHeading(2, 'P04.scope.heading'),
            'body' => $this->text($c, 'P04.scope.body'),
            'link' => $this->linkTo($c, 'P08', 'P04.scope.link'),
        ];

        // ---------------------------------------------------------------- WhatsApp draft + metadata
        $c->expectNote('/^WhatsApp\. /');
        $page['whatsapp_draft'] = $this->text($c, 'P04.whatsapp_draft');
        if ($this->whatsappDraft($waHref) !== $page['whatsapp_draft']) {
            $this->report->add('source-conflict', 'WA01', 'The encoded P04 WhatsApp link does not decode to the black P04 draft text', $locale);
        }
        $c->expectNote('/^SEO title\b/');
        $page['seo_title'] = $this->text($c, 'P04.seo_title');
        $c->expectNote('/^Meta description\b/');
        $page['meta_description'] = $this->text($c, 'P04.meta_description');

        return [
            'pages' => ['P04' => $page],
            'case_studies' => $cases,
        ];
    }

    /**
     * One full story, from its "Case 0n" marker to its back link.
     *
     * @return array{title: string, matter: string, body: array<string, mixed>}
     */
    private function story(Cursor $c, string $code, int $n): array
    {
        $c->expectNote('/^Case '.sprintf('%02d', $n).'\b/');
        $id = $c->expectNote('/id="case-\d{2}"/');
        preg_match('/id="(case-\d{2})"/', $id->note, $m);
        if ($m[1] !== $code) {
            throw new ImportException($c->where($id)." story {$n} sets id=\"{$m[1]}\", expected {$code}");
        }
        $c->expectNote('/full story \| H2\./');
        $title = $c->takeHeading(2, "case_studies.{$code}.title");

        // Everything of this story lies before the next H2 marker (next story or the page FAQ).
        $end = $c->indexOfNote(self::H2);
        $this->noteBefore($c, '/^Case subject label\b/', $end, "case_studies.{$code}.matter");
        $matter = $this->text($c, "case_studies.{$code}.matter");
        $narrative = $this->paragraphs($c, "case_studies.{$code}.body.narrative");

        // Substantive H3 sections up to the practical takeaway; the last of them is the outcome.
        $takeawayAt = $c->indexOfNote(self::TAKEAWAY);
        if ($takeawayAt === null || ($end !== null && $takeawayAt > $end)) {
            throw new ImportException($c->where()." story {$code} has no practical takeaway marker");
        }
        $sections = [];
        $outcome = null;
        while ($c->findNote(self::H3, $takeawayAt) !== null) {
            if ($c->indexOfNote(self::H3) === $takeawayAt) {
                $outcome = $this->section($c, "case_studies.{$code}.body.outcome");
            } else {
                $sections[] = $this->section($c, "case_studies.{$code}.body.sections.".count($sections));
            }
        }
        if ($outcome === null) {
            throw new ImportException($c->where()." story {$code} has no outcome section before its practical takeaway");
        }
        $c->expectNote(self::TAKEAWAY);
        $takeaway = $this->section($c, "case_studies.{$code}.body.takeaway");

        // Related service / guide links, then the link back to the directory (#cases-top).
        $this->noteBefore($c, '/^Related service and guide links \|/', $end, "case_studies.{$code}.body.related");
        $related = ['heading' => $c->takeHeading(3, "case_studies.{$code}.body.related.heading"), 'links' => []];
        while (true) {
            $note = $this->noteBefore($c, self::LINK, $end, "case_studies.{$code}.body.back_link");
            if ($this->target($c, $note) === self::TOP) {
                $back = $this->linkAfter($c, $note, "case_studies.{$code}.body.back_link");
                break;
            }
            $related['links'][] = $this->linkAfter($c, $note, "case_studies.{$code}.body.related.links.".count($related['links']));
        }

        return [
            'title' => $title,
            'matter' => $matter,
            'body' => [
                'narrative' => $narrative,
                'sections' => $sections,
                'outcome' => $outcome,
                'takeaway' => $takeaway,
                'related' => $related,
                'back_link' => $back,
            ],
        ];
    }

    /** @return array{heading: string, paragraphs: list<string>} */
    private function section(Cursor $c, string $field): array
    {
        return [
            'heading' => $c->takeHeading(3, $field.'.heading'),
            'paragraphs' => $this->paragraphs($c, $field.'.paragraphs'),
        ];
    }

    /** The next public block, which must be a plain paragraph (not a heading, list item or link). */
    private function text(Cursor $c, string $field): string
    {
        $b = $c->takeBlock($field);
        if ($b->headingLevel !== null || $b->bullet || $b->number !== null || $b->links !== []) {
            throw new ImportException($c->where($b)." expected a plain paragraph for {$field}");
        }

        return $b->text;
    }

    /** @return list<string> one or more consecutive plain paragraphs */
    private function paragraphs(Cursor $c, string $field): array
    {
        $items = $c->takeParagraphs($field);
        if ($items === []) {
            throw new ImportException($c->where()." expected at least one paragraph for {$field}");
        }

        return $items;
    }

    private function noteBefore(Cursor $c, string $pattern, ?int $before, string $field): Block
    {
        return $c->findNote($pattern, $before)
            ?? throw new ImportException($c->where()." expected a designer note matching {$pattern} for {$field}");
    }

    /**
     * The next link marker, which must name $target, and the black linked label after it.
     *
     * @return array{label: string, target: string}
     */
    private function linkTo(Cursor $c, string $target, string $field, ?string &$href = null): array
    {
        $note = $c->expectNote(self::LINK);
        $found = $this->target($c, $note);
        if ($found !== $target) {
            throw new ImportException($c->where($note)." link marker for {$field} names {$found}, expected {$target}");
        }

        return $this->linkAfter($c, $note, $field, $href);
    }

    /**
     * The black linked label that follows a link marker, with the marker's target ID.
     *
     * @return array{label: string, target: string}
     */
    private function linkAfter(Cursor $c, Block $note, string $field, ?string &$href = null): array
    {
        $link = $c->takeLink($field);
        $target = $this->target($c, $note);
        $href = $link['href'];
        if (! $this->hrefMatches($target, $link['label'], $href)) {
            $this->report->add('source-conflict', "{$field} -> {$target}", 'The black hyperlink does not lead to the target named in its link marker: '.$href, $c->locale());
        }

        return ['label' => $link['label'], 'target' => $target];
    }

    /** Target ID after "->": "-> S01: …" gives S01, "-> /ar/case-studies/#case-01: …" gives case-01. */
    private function target(Cursor $c, Block $note): string
    {
        if (preg_match('/->\s*(?:[^\s:]*#)?([A-Za-z0-9][A-Za-z0-9-]*):/', $note->note, $m) !== 1) {
            throw new ImportException($c->where($note).' link marker names no target ID');
        }

        return $m[1];
    }

    /** Sanity check of the black hyperlink against the registry entry of the marker's target ID. */
    private function hrefMatches(string $target, string $label, string $href): bool
    {
        if ($target === self::TOP || str_starts_with($target, 'case-')) {
            return str_ends_with($href, '#'.$target);
        }
        if (preg_match('/^S\d{2}$/', $target) === 1) {
            $slug = config("summit.services.{$target}");

            return is_string($slug) && str_contains($href, '/'.$slug.'/');
        }
        if (preg_match('/^G\d{2}$/', $target) === 1) {
            $anchor = config("summit.guides.{$target}");

            return is_string($anchor) && str_ends_with($href, '#'.$anchor);
        }

        if (preg_match('/^P\d{2}$/', $target) === 1) {
            $path = config("summit.pages.{$target}.path");

            return match (true) {
                ! is_string($path) => false,
                $path === '' => preg_match('#/(en|ar|ru)/$#', $href) === 1,
                // P07 release rule: until the Contact page ships, P07 is the homepage form.
                $target === 'P07' => str_ends_with($href, '#'.config('summit.anchors.A01.fragment')) || str_contains($href, '/'.$path),
                default => str_contains($href, '/'.$path),
            };
        }

        return match ($target) {
            'WA01' => str_starts_with($href, 'https://wa.me/'.config('summit.contact.whatsapp_number').'?'),
            'TEL' => $href === 'tel:'.preg_replace('/[^\d+]/', '', $label),
            'EMAIL' => $href === 'mailto:'.$label,
            default => false,
        };
    }
}
