<?php

namespace App\Content\Import\Mappers;

use App\Content\Docx\Block;
use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 02_S01…02_S11*.docx → the eleven full service pages (S01–S11): breadcrumb, hero, every H2
 * section in page order, the documents checklist, first steps and mistakes, FAQ, related links
 * (with the G01–G09 guide IDs the Matter Navigator uses), official sources, the related
 * specialist site (X01–X06), the consultation CTA, the scope notice, the WA01 draft and metadata.
 *
 * Anchors on the English red markers that exist in all three files ("Breadcrumbs.", "Hero.",
 * "<Section> | H2.", "FAQ S0n-Q0n.", "S0n-Lnn | … -> <ID>:", "SEO title." …), never on black
 * wording. Internal link targets are stored as the IDs named in those notes, not as the /ar/
 * fallback URLs; every black hyperlink is checked against its ID (source-conflict if it leads
 * elsewhere). FAQ IDs must run Sxx-Q01… in page order.
 */
final class ServicePagesMapper extends Mapper
{
    private const SERVICES = ['S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09', 'S10', 'S11'];

    /** Red H2 markers ("<marker> … | H2. Keep every paragraph and list item.") in page order. */
    private const SECTIONS = [
        'who_for' => 'Audience',
        'matters' => 'Service scope',
        'practical' => 'Practical situations',
        'process' => 'Workflow',
        'documents' => 'Document checklist',
        'next_step' => 'Practical first steps',
        'faq' => 'Visible FAQ',
        'related' => 'Contextual internal links',
        'sources' => 'Primary legal',
        'cta' => 'Final consultation CTA',
        'scope' => 'Visible professional scope',
    ];

    /** @var array<string, true> structural notes already reported (they are the same in every language) */
    private array $noted = [];

    /** @var array<string, list<string>> page language of an official source link => REF IDs, for the current locale */
    private array $foreignSources = [];

    public function sources(): array
    {
        $sources = [];
        foreach (self::SERVICES as $code) {
            $sources[$code] = '02_'.$code;
        }

        return $sources;
    }

    public function map(array $docs, string $locale): array
    {
        $services = [];
        $this->foreignSources = [];
        foreach (self::SERVICES as $code) {
            $services[$code] = $this->page($docs[$code], $code, $locale);
        }
        // The two "Before Taking the Next Step" lists are split by H3 position (no red marker per
        // H3); every page shares the same pair of headings, so a page that differs may be swapped.
        $shared = $services[self::SERVICES[0]]['sections']['next_step'];
        foreach ($services as $code => $service) {
            foreach (['first_steps_heading', 'mistakes_heading'] as $key) {
                if ($service['sections']['next_step'][$key] !== $shared[$key]) {
                    $this->report->add('label-mismatch', "{$code} next_step.{$key}", 'Differs from '.self::SERVICES[0].' ("'.$shared[$key].'"): "'.$service['sections']['next_step'][$key].'". first_steps and mistakes are taken in H3 order; check they are not swapped.', $locale);
                }
            }
        }
        foreach ($this->foreignSources as $language => $refs) {
            $this->report->add('note', 'S01–S11 official sources', 'These official source links point to '.strtoupper($language).'-language pages (/'.$language.'/) in this language file and are kept as supplied: '.implode(', ', $refs), $locale);
        }

        return ['services' => $services];
    }

    /** @return array<string, mixed> */
    private function page(Cursor $c, string $code, string $locale): array
    {
        $f = "services.{$code}";
        $sections = [];

        // ---------------------------------------------------------------- breadcrumb + hero
        $c->expectNote('/^Breadcrumbs\./');
        $breadcrumb = [];
        foreach (['P01', 'P02'] as $i => $target) {
            $note = $c->expectNote(self::linkTo($code, $target));
            $link = $c->takeLink("{$f}.breadcrumb.{$i}");
            $this->checkHref($c, $note, $target, $link['href']);
            $breadcrumb[] = $link['label'];
        }
        $breadcrumb[] = $this->paragraph($c, "{$f}.breadcrumb.2");
        $c->expectNote('/^Hero\./');
        $h1 = $c->takeHeading(1, "{$f}.h1");
        $intro = $this->paragraph($c, "{$f}.intro");

        // ---------------------------------------------------------------- who it is for + scope
        foreach (['who_for', 'matters'] as $key) {
            $c->expectNote(self::section($key));
            $sections[$key] = [
                'heading' => $c->takeHeading(2, "{$f}.sections.{$key}.heading"),
                'items' => $c->takeBullets("{$f}.sections.{$key}.items"),
            ];
        }

        // ---------------------------------------------------------------- practical situations
        $c->expectNote(self::section('practical'));
        $practical = ['heading' => $c->takeHeading(2, "{$f}.sections.practical.heading"), 'items' => []];
        $end = $this->sectionIndex($c, 'process');
        while (($next = $c->peekPublic()) !== null && $next->index < $end && $next->isHeading(3)) {
            $i = count($practical['items']);
            $item = [
                'title' => $c->takeHeading(3, "{$f}.sections.practical.items.{$i}.title"),
                'paragraphs' => $c->takeParagraphs("{$f}.sections.practical.items.{$i}.paragraphs", self::section('process')),
                'bullets' => $c->takeBullets("{$f}.sections.practical.items.{$i}.bullets"),
            ];
            if ($item['bullets'] !== []) {
                $this->noteOnce($code, 'Practical situation '.($i + 1)." is a bulleted list; stored in sections.practical.items.{$i}.bullets (every item carries a bullets list, usually empty).");
            }
            $practical['items'][] = $item;
        }
        $sections['practical'] = $practical;

        // ---------------------------------------------------------------- how we handle your matter
        $c->expectNote(self::section('process'));
        $process = ['heading' => $c->takeHeading(2, "{$f}.sections.process.heading")];
        $lead = $c->takeParagraphs("{$f}.sections.process.intro", self::section('documents'));
        if (count($lead) > 1) {
            throw new ImportException($c->where().' expected at most one paragraph before the workflow steps');
        }
        $process['intro'] = $lead[0] ?? null;
        if ($process['intro'] !== null) {
            $this->noteOnce($code, 'The workflow has a lead paragraph before its numbered steps; stored in sections.process.intro (null on the other pages).');
        }
        $process['steps'] = $this->steps($c, "{$f}.sections.process.steps", $locale, $code);
        $sections['process'] = $process;

        // ---------------------------------------------------------------- documents checklist
        $c->expectNote(self::section('documents'));
        $sections['documents'] = ['heading' => $c->takeHeading(2, "{$f}.sections.documents.heading")];
        $documents = $c->takeBullets("{$f}.documents");
        if (count($documents) !== 4) {
            $this->report->add('note', "{$code} documents", 'Expected 4 documents in the checklist, found '.count($documents), $locale);
        }
        $documentsNote = $this->paragraph($c, "{$f}.documents_note");

        // ---------------------------------------------------------------- first steps + mistakes
        $c->expectNote(self::section('next_step'));
        $sections['next_step'] = [
            'heading' => $c->takeHeading(2, "{$f}.sections.next_step.heading"),
            'first_steps_heading' => $c->takeHeading(3, "{$f}.sections.next_step.first_steps_heading"),
        ];
        $firstSteps = $c->takeBullets("{$f}.first_steps");
        $sections['next_step']['mistakes_heading'] = $c->takeHeading(3, "{$f}.sections.next_step.mistakes_heading");
        $mistakes = $c->takeBullets("{$f}.mistakes");

        // ---------------------------------------------------------------- FAQ
        $c->expectNote(self::section('faq'));
        $faq = ['heading' => $c->takeHeading(2, "{$f}.sections.faq.heading"), 'items' => []];
        $end = $this->sectionIndex($c, 'related');
        while (($note = $c->findNote('/^FAQ '.$code.'-Q\d+\b/', $end)) !== null) {
            preg_match('/^FAQ ('.$code.'-Q\d+)/', $note->note, $m);
            $i = count($faq['items']);
            $faq['items'][] = [
                'id' => $m[1],
                'question' => $c->takeHeading(3, "{$f}.sections.faq.items.{$i}.question"),
                'answer' => $this->paragraph($c, "{$f}.sections.faq.items.{$i}.answer"),
            ];
        }
        $ids = array_column($faq['items'], 'id');
        if ($ids !== array_map(fn (int $i) => sprintf('%s-Q%02d', $code, $i + 1), array_keys($ids))) {
            $this->report->add('source-conflict', "{$code} FAQ", 'The FAQ IDs in the red notes do not run '.$code.'-Q01, '.$code.'-Q02 … in page order: '.implode(', ', $ids), $locale);
        }
        $sections['faq'] = $faq;

        // ---------------------------------------------------------------- related topics and services
        $c->expectNote(self::section('related'));
        $related = ['heading' => $c->takeHeading(2, "{$f}.sections.related.heading"), 'links' => []];
        foreach ($this->linkList($c, '/^'.$code.'-L\d+ \|/', $this->sectionIndex($c, 'sources'), "{$f}.sections.related.links") as $link) {
            if (! preg_match('/^[PSGAL]\d{2}$/', $link['target'])) {
                throw new ImportException($c->where().' expected an internal destination ID for a related link, got '.$link['target']);
            }
            $related['links'][] = ['label' => $link['label'], 'target' => $link['target']];
        }
        $sections['related'] = $related;
        $guides = array_values(array_unique(array_filter(
            array_column($related['links'], 'target'),
            fn (string $target) => preg_match('/^G0[1-9]$/', $target) === 1,
        )));
        if ($guides === []) {
            $this->noteOnce($code, 'No knowledge-centre guide (G01–G09) is linked under the related topics; related_guides is empty.');
        }

        // ---------------------------------------------------------------- official sources + specialist site
        $c->expectNote(self::section('sources'));
        $sources = ['heading' => $c->takeHeading(2, "{$f}.sections.sources.heading"), 'links' => []];
        $end = $this->sectionIndex($c, 'cta');
        foreach ($this->linkList($c, self::linkTo($code, $code.'-REF\d+', false), $end, "{$f}.sections.sources.links") as $link) {
            $sources['links'][] = ['label' => $link['label'], 'url' => $link['href']];
            if (preg_match('#^/(ar|en|ru)(/|$)#', (string) parse_url($link['href'], PHP_URL_PATH), $m) && $m[1] !== $locale) {
                $this->foreignSources[$m[1]][] = $link['target'];
            }
        }
        $sections['sources'] = $sources;

        $specialistCode = null;
        $sections['specialist'] = null;
        $site = self::linkTo($code, 'X0\d', false);
        $at = $c->indexOfNote($site);
        if ($at !== null && $at < $end) {
            $specialistIntro = $c->takeParagraphs("{$f}.sections.specialist.intro", $site, expect: 1)[0];
            $note = $c->expectNote($site);
            $link = $c->takeLink("{$f}.sections.specialist.label");
            $split = $this->splitDomainLabel($link['label']);
            $specialistCode = self::target($c, $note);
            $this->checkHref($c, $note, $specialistCode, $link['href']);
            $sections['specialist'] =['intro' => $specialistIntro, 'label' => $split['label'], 'domain' => $split['domain']];
            if ($split['domain'] === '' || preg_replace('/^www\./', '', (string) parse_url($link['href'], PHP_URL_HOST)) !== $split['domain']) {
                $this->report->add('source-conflict', "{$code} {$specialistCode}", 'The specialist link target does not match the domain in its black label', $locale);
            }
        } else {
            $this->noteOnce($code, 'No related specialist site (X01–X06) is linked; specialist and sections.specialist are null.');
        }

        // ---------------------------------------------------------------- final consultation CTA
        $c->expectNote(self::section('cta'));
        $heading = $c->takeHeading(2, "{$f}.sections.cta.heading");
        $text = $this->paragraph($c, "{$f}.sections.cta.text");
        $note = $c->expectNote(self::linkTo($code, 'P07'));
        $request = $c->takeLink("{$f}.sections.cta.request_label");
        $this->checkHref($c, $note, 'P07', $request['href']);
        $c->expectNote(self::linkTo($code, 'WA01'));
        $wa = $c->takeLink("{$f}.sections.cta.whatsapp_label");
        $c->expectNote(self::linkTo($code, 'TEL'));
        $phone = $c->takeLink("{$f}.sections.cta.phone");
        $c->expectNote(self::linkTo($code, 'EMAIL'));
        $email = $c->takeLink("{$f}.sections.cta.email");
        $sections['cta'] = [
            'heading' => $heading,
            'text' => $text,
            'note' => $this->paragraph($c, "{$f}.sections.cta.note"),
            'request_label' => $request['label'],
            'whatsapp_label' => $wa['label'],
            'phone' => $phone['label'],
            'email' => $email['label'],
        ];
        if ($phone['href'] !== 'tel:'.preg_replace('/[^\d+]/', '', $phone['label']) || $email['href'] !== 'mailto:'.$email['label']) {
            $this->report->add('source-conflict', "{$code} contact", 'The phone or email link target does not match its black label', $locale);
        }

        // ---------------------------------------------------------------- scope of service
        $c->expectNote(self::section('scope'));
        $scope = [
            'heading' => $c->takeHeading(2, "{$f}.sections.scope.heading"),
            'paragraphs' => $c->takeParagraphs("{$f}.sections.scope.paragraphs"),
        ];
        $note = $c->expectNote(self::linkTo($code, 'P08'));
        $legal = $c->takeLink("{$f}.sections.scope.link_label");
        $this->checkHref($c, $note, 'P08', $legal['href']);
        $scope['link_label'] = $legal['label'];
        $sections['scope'] = $scope;

        // ---------------------------------------------------------------- WhatsApp draft + metadata
        $c->expectNote('/^Shared WhatsApp implementation\./');
        $draft = $this->paragraph($c, "{$f}.whatsapp_draft");
        if (! str_starts_with($wa['href'], 'https://wa.me/971524666191') || $this->whatsappDraft($wa['href']) !== $draft) {
            $this->report->add('source-conflict', "{$code} WA01", 'The WhatsApp link does not decode to the black draft text', $locale);
        }
        $c->expectNote('/^SEO title\./');
        $seoTitle = $this->paragraph($c, "{$f}.seo_title");
        $c->expectNote('/^Meta description\./');
        $metaDescription = $this->paragraph($c, "{$f}.meta_description");

        return [
            'matter_value' => $code,
            'h1' => $h1,
            'intro' => $intro,
            'breadcrumb' => $breadcrumb,
            'documents' => $documents,
            'documents_note' => $documentsNote,
            'first_steps' => $firstSteps,
            'mistakes' => $mistakes,
            'related_guides' => $guides,
            'specialist' => $specialistCode,
            'seo_title' => $seoTitle,
            'meta_description' => $metaDescription,
            'whatsapp_draft' => $draft,
            'sections' => $sections,
        ];
    }

    /** Numbered workflow steps; the approved numbering must run 1, 2, 3 … */
    private function steps(Cursor $c, string $field, string $locale, string $code): array
    {
        $blocks = $c->takeWhile(fn (Block $b) => $b->number !== null, $field);
        if ($blocks === []) {
            throw new ImportException($c->where()." expected numbered steps for {$field}");
        }
        if (array_map(fn (Block $b) => $b->number, $blocks) !== range(1, count($blocks))) {
            $this->report->add('source-conflict', "{$code} workflow", 'The workflow steps are not numbered 1 to '.count($blocks), $locale);
        }

        return array_map(fn (Block $b) => $b->text, $blocks);
    }

    /** Red H2 marker of a page section, e.g. "Workflow | H2. Keep every paragraph and list item.". */
    private static function section(string $key): string
    {
        return '/^'.preg_quote(self::SECTIONS[$key], '/').'[^|]*\| H2\./';
    }

    /** The red note introducing a link of page $code to $target ("S05-L11 | … -> P07: …"). */
    private static function linkTo(string $code, string $target, bool $quote = true): string
    {
        return '/^'.$code.'-L\d+ \|.*?->\s*'.($quote ? preg_quote($target, '/') : $target).':/s';
    }

    private function sectionIndex(Cursor $c, string $key): int
    {
        return $c->indexOfNote(self::section($key))
            ?? throw new ImportException($c->where().' expected the "'.self::SECTIONS[$key].' | H2" designer note');
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
        if (! self::hrefMatches($target, $note, $href)) {
            $this->report->add('source-conflict', strtok($note->note, ' ')." -> {$target}", "The black hyperlink {$href} does not lead to the destination registered for {$target} in config/summit.php or stated in the marker", $c->locale());
        }
    }

    /**
     * Internal IDs (S, G, P, L, A) are checked against the site registry in config/summit.php,
     * under any language prefix (the retained /ar/ fallbacks are expected); official sources and
     * specialist sites (REF, X) against the URL written in the marker.
     */
    private static function hrefMatches(string $target, Block $note, string $href): bool
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
        if (preg_match('/^[PL]\d{2}$/', $target) === 1) {
            return $page(config("summit.pages.{$target}.path")) && $fragment === '';
        }

        // "-> S05-REF1: https://dubailand.gov.ae/ar/. Open a new tab …"
        return preg_match('/->\s*'.preg_quote($target, '/').':\s*(\S+?)\.?(?:\s|$)/', $note->note, $m) === 1 && $m[1] === $href;
    }

    /** Destination ID named in a link note: "-> G04: /ar/resources/#property-delay" gives G04. */
    private static function target(Cursor $c, Block $note): string
    {
        if (! preg_match('/->\s*#?([A-Za-z0-9_-]+):/', $note->note, $m)) {
            throw new ImportException($c->where($note).' expected a destination ID in the link note');
        }

        return $m[1];
    }

    /** Structural findings are identical in EN, AR and RU (parity is checked separately): report once. */
    private function noteOnce(string $code, string $detail): void
    {
        if (! isset($this->noted[$code.$detail])) {
            $this->noted[$code.$detail] = true;
            $this->report->add('note', $code, $detail);
        }
    }
}
