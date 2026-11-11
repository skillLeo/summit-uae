<?php

namespace App\Content\Import\Mappers;

use App\Content\Docx\Block;
use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 02_Services_Overview*.docx → P02 hub page copy, the hub card of every service (S01–S11: title,
 * summary, the four "may be relevant if" situations and the card link label, which feed the
 * Matter Navigator) and the shared situations intro line.
 *
 * Anchors on the English red markers that exist in all three files ("Breadcrumbs.", "Hero.",
 * "<Section> | H2.", "Service card S0n |", "P02-Lnn | … -> <ID>:", "SEO title." …), never on
 * black wording. Every black hyperlink is checked against the ID in its marker (source-conflict
 * if, say, card S05 linked to another service's page).
 */
final class ServicesOverviewMapper extends Mapper
{
    private const SERVICES = ['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];

    public function sources(): array
    {
        return ['hub' => '02_Services_Overview'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['hub'];
        $page = [];
        $content = [];
        $services = [];
        $ui = [];

        // ---------------------------------------------------------------- breadcrumb + hero
        $c->expectNote('/^Breadcrumbs\./');
        $note = $c->expectNote(self::linkTo('P01'));
        $home = $c->takeLink('P02.breadcrumb.0');
        $this->checkHref($c, $note, 'P01', $home['href']);
        $page['breadcrumb'] = [$home['label'], $this->paragraph($c, 'P02.breadcrumb.1')];
        $c->expectNote('/^Hero\./');
        $page['h1'] = $c->takeHeading(1, 'P02.h1');
        $content['intro'] = $this->paragraph($c, 'P02.intro');

        // ---------------------------------------------------------------- we start with the file
        $c->expectNote(self::section('How matters are assessed'));
        $content['start'] = [
            'heading' => $c->takeHeading(2, 'P02.start.heading'),
            'text' => $this->paragraph($c, 'P02.start.text'),
            'items' => $c->takeBullets('P02.start.items'),
        ];

        // ---------------------------------------------------------------- eleven service cards
        $c->expectNote(self::section('Eleven service cards'));
        $content['choose'] = ['heading' => $c->takeHeading(2, 'P02.choose.heading')];
        $situationsIntro = null;
        $linkLabel = null;
        foreach (self::SERVICES as $code) {
            $c->expectNote('/^Service card '.$code.' \|/');
            $services[$code]['hub_title'] = $c->takeHeading(3, "services.{$code}.hub_title");
            $services[$code]['hub_summary'] = $this->paragraph($c, "services.{$code}.hub_summary");
            $intro = $this->paragraph($c, "ui.service.situations_intro#{$code}");
            $situationsIntro ??= $intro;
            if ($intro !== $situationsIntro) {
                $this->report->add('label-mismatch', "P02 card {$code}", 'The "may be relevant if" line differs from the first card: '.$intro, $locale);
            }
            $services[$code]['situations'] = $c->takeBullets("services.{$code}.situations", expect: 4);
            $note = $c->expectNote(self::linkTo($code));
            $link = $c->takeLink("services.{$code}.hub_link_label");
            $this->checkHref($c, $note, $code, $link['href']);
            $services[$code]['hub_link_label'] = $link['label'];
            $linkLabel ??= $services[$code]['hub_link_label'];
            if ($services[$code]['hub_link_label'] !== $linkLabel) {
                $this->report->add('note', "P02 card {$code}", 'Card link label differs from the first card: '.$services[$code]['hub_link_label'], $locale);
            }
        }
        $ui['service.situations_intro'] = $situationsIntro;

        // ---------------------------------------------------------------- overlapping matters
        $c->expectNote(self::section('Overlapping matters'));
        $content['overlap'] = [
            'heading' => $c->takeHeading(2, 'P02.overlap.heading'),
            'paragraphs' => $c->takeParagraphs('P02.overlap.paragraphs'),
        ];

        // ---------------------------------------------------------------- before requesting
        $c->expectNote(self::section('Preparing an enquiry'));
        $content['before'] = [
            'heading' => $c->takeHeading(2, 'P02.before.heading'),
            'items' => $c->takeBullets('P02.before.items'),
        ];

        // ---------------------------------------------------------------- service selection FAQ
        $c->expectNote(self::section('Service selection FAQ'));
        $faq = ['heading' => $c->takeHeading(2, 'P02.faq.heading'), 'items' => []];
        $end = $this->sectionIndex($c, 'Knowledge and FAQ links');
        while (($next = $c->peekPublic()) !== null && $next->index < $end && $next->isHeading(3)) {
            $i = count($faq['items']);
            $faq['items'][] = [
                'question' => $c->takeHeading(3, "P02.faq.items.{$i}.question"),
                'answer' => $this->paragraph($c, "P02.faq.items.{$i}.answer"),
            ];
        }
        $content['faq'] = $faq;

        // ---------------------------------------------------------------- knowledge and FAQ links
        $c->expectNote(self::section('Knowledge and FAQ links'));
        $resources = ['heading' => $c->takeHeading(2, 'P02.resources.heading'), 'links' => []];
        foreach ($this->linkList($c, '/^P02-L\d+ \|/', $this->sectionIndex($c, 'Final consultation CTA'), 'P02.resources.links') as $link) {
            $resources['links'][] = ['label' => $link['label'], 'target' => $link['target']];
        }
        $content['resources'] = $resources;

        // ---------------------------------------------------------------- final consultation CTA
        $c->expectNote(self::section('Final consultation CTA'));
        $heading = $c->takeHeading(2, 'P02.cta.heading');
        $text = $this->paragraph($c, 'P02.cta.text');
        $note = $c->expectNote(self::linkTo('P07'));
        $request = $c->takeLink('P02.cta.request_label');
        $this->checkHref($c, $note, 'P07', $request['href']);
        $c->expectNote(self::linkTo('WA01'));
        $wa = $c->takeLink('P02.cta.whatsapp_label');
        $c->expectNote(self::linkTo('TEL'));
        $phone = $c->takeLink('P02.cta.phone');
        $c->expectNote(self::linkTo('EMAIL'));
        $email = $c->takeLink('P02.cta.email');
        $content['cta'] = [
            'heading' => $heading,
            'text' => $text,
            'note' => $this->paragraph($c, 'P02.cta.note'),
            'request_label' => $request['label'],
            'whatsapp_label' => $wa['label'],
            'phone' => $phone['label'],
            'email' => $email['label'],
        ];
        if ($phone['href'] !== 'tel:'.preg_replace('/[^\d+]/', '', $phone['label']) || $email['href'] !== 'mailto:'.$email['label']) {
            $this->report->add('source-conflict', 'P02 contact', 'The phone or email link target does not match its black label', $locale);
        }

        // ---------------------------------------------------------------- scope of service
        $c->expectNote(self::section('Visible professional scope'));
        $scope = [
            'heading' => $c->takeHeading(2, 'P02.scope.heading'),
            'paragraphs' => $c->takeParagraphs('P02.scope.paragraphs'),
        ];
        $note = $c->expectNote(self::linkTo('P08'));
        $legal = $c->takeLink('P02.scope.link_label');
        $this->checkHref($c, $note, 'P08', $legal['href']);
        $scope['link_label'] = $legal['label'];
        $content['scope'] = $scope;

        // ---------------------------------------------------------------- WhatsApp draft + metadata
        $c->expectNote('/^Shared WhatsApp implementation\./');
        $page['whatsapp_draft'] = $this->paragraph($c, 'P02.whatsapp_draft');
        if (! str_starts_with($wa['href'], 'https://wa.me/971524666191') || $this->whatsappDraft($wa['href']) !== $page['whatsapp_draft']) {
            $this->report->add('source-conflict', 'P02 WA01', 'The WhatsApp link does not decode to the black draft text', $locale);
        }
        $c->expectNote('/^SEO title\./');
        $page['seo_title'] = $this->paragraph($c, 'P02.seo_title');
        $c->expectNote('/^Meta description\./');
        $page['meta_description'] = $this->paragraph($c, 'P02.meta_description');

        $page['content'] = $content;

        return [
            'pages' => ['P02' => $page],
            'services' => $services,
            'ui_strings' => $ui,
        ];
    }

    /** Red H2 marker of a hub section, e.g. "Overlapping matters | H2. Keep every paragraph …". */
    private static function section(string $marker): string
    {
        return '/^'.preg_quote($marker, '/').'[^|]*\| H2\./';
    }

    /** The red note introducing a P02 link to $target ("P02-L15 | … -> P07: …"). */
    private static function linkTo(string $target): string
    {
        return '/^P02-L\d+ \|.*?->\s*'.preg_quote($target, '/').':/s';
    }

    private function sectionIndex(Cursor $c, string $marker): int
    {
        return $c->indexOfNote(self::section($marker))
            ?? throw new ImportException($c->where()." expected the \"{$marker} | H2\" designer note");
    }

    /** The next public block, which must be a plain paragraph (not a heading, list item or link). */
    private function paragraph(Cursor $c, string $field): string
    {
        $b = $c->peekPublic();
        if ($b === null || $b->headingLevel !== null || $b->bullet || $b->number !== null || $b->links !== []) {
            throw new ImportException($c->where($b)." expected a plain paragraph for {$field}");
        }

        return $c->take($field);
    }

    /**
     * Consecutive linked labels, each introduced by its own red link note, before block $end.
     * Stops at the first public block that is not preceded by a matching note.
     *
     * @return list<array{label: string, href: string, target: string}>
     */
    private function linkList(Cursor $c, string $notePattern, int $end, string $field): array
    {
        $links = [];
        while (true) {
            $at = $c->indexOfNote($notePattern);
            $next = $c->peekPublic();
            if ($at === null || $at >= $end || ($next !== null && $next->index < $at)) {
                return $links;
            }
            $note = $c->expectNote($notePattern);
            $link = $c->takeLink($field.'.'.count($links));
            $target = self::target($c, $note);
            $this->checkHref($c, $note, $target, $link['href']);
            $links[] = ['label' => $link['label'], 'href' => $link['href'], 'target' => $target];
        }
    }

    /** Reports a black hyperlink that does not lead where its red link marker says (see hrefMatches()). */
    private function checkHref(Cursor $c, Block $note, string $target, string $href): void
    {
        if (! self::hrefMatches($target, $href)) {
            $this->report->add('source-conflict', strtok($note->note, ' ')." -> {$target}", "The black hyperlink {$href} does not lead to the destination registered for {$target} in config/summit.php", $c->locale());
        }
    }

    /**
     * Internal IDs (S, G, P, L, A) against the site registry in config/summit.php, under any
     * language prefix (the retained /ar/ fallbacks are expected). Same rules as ServicePagesMapper.
     */
    private static function hrefMatches(string $target, string $href): bool
    {
        $path = (string) parse_url($href, PHP_URL_PATH);
        $fragment = (string) parse_url($href, PHP_URL_FRAGMENT);
        $page = fn (mixed $registered): bool => is_string($registered)
            && preg_match('#^/(en|ar|ru)/'.preg_quote($registered, '#').'$#', $path) === 1;

        if (preg_match('/^S\d{2}$/', $target) === 1) {
            $slug = config("summit.services.{$target}");

            return is_string($slug) && $page("practice-areas/{$slug}/") && $fragment === '';
        }
        if (preg_match('/^G\d{2}$/', $target) === 1) {
            return $page(config('summit.pages.P03.path')) && $fragment === config("summit.guides.{$target}");
        }
        if (preg_match('/^A\d{2}$/', $target) === 1) {
            $anchor = config("summit.anchors.{$target}");

            return is_array($anchor) && $page(config("summit.pages.{$anchor['page']}.path")) && $fragment === $anchor['fragment'];
        }
        if ($target === 'P07') {
            // Release rule: until the standalone Contact page ships, P07 is the homepage form (A01).
            return ($page('') && $fragment === config('summit.anchors.A01.fragment'))
                || ($page(config('summit.pages.P07.path')) && $fragment === '');
        }

        return preg_match('/^[PL]\d{2}$/', $target) === 1 && $page(config("summit.pages.{$target}.path")) && $fragment === '';
    }

    /** Internal destination ID from a link note: "-> P03: /ar/resources/" gives P03. */
    private static function target(Cursor $c, Block $note): string
    {
        if (! preg_match('/->\s*#?([A-Za-z0-9_-]+):/', $note->note, $m) || ! preg_match('/^[PSGAL]\d{2}$/', $m[1])) {
            throw new ImportException($c->where($note).' expected an internal destination ID in the link note');
        }

        return $m[1];
    }
}
