<?php

namespace App\Content\Import\Mappers;

use App\Content\Import\Cursor;
use App\Content\Import\ImportException;
use App\Content\Import\Mapper;

/**
 * 06_About_Us*.docx → P06 page copy (breadcrumb, hero, story, approach, method, team
 * introduction, scope, confidentiality, closing consultation section, WA01 draft, metadata) and
 * the three full team profiles team-ahmed, team-mohamed and team-sarah in their mandatory order.
 *
 * Anchors on the red markers shared by all three files: the link IDs "P06-L001 |" … "P06-L021 |",
 * the profile IDs "TEAM-0n |" / "PHOTO TEAM-0n" and the "<section> | H2." labels. Link
 * destinations are stored as the ID named in the red note (P07, S02, WA01) or its page fragment
 * (#team-ahmed → team-ahmed), never as the retained /ar/ fallback URLs.
 */
final class AboutMapper extends Mapper
{
    /** Profile code (= anchor) => portrait file in package/portraits; Sarah Zaki's portrait has not been supplied. */
    private const TEAM = [
        'team-ahmed' => 'ahmed-mohammadi',
        'team-mohamed' => 'dr-mohamed-raouf',
        'team-sarah' => null,
    ];

    /** Contextual links that close each profile. */
    private const PROFILE_LINKS = [
        'team-ahmed' => ['P06-L009'],
        'team-mohamed' => ['P06-L010', 'P06-L011', 'P06-L012'],
        'team-sarah' => ['P06-L013', 'P06-L014'],
    ];

    private const PRINCIPLES = 6;

    private const STEPS = 4;

    /** Red marker in front of each profile's H4 focus/expertise list. */
    private const AREAS = '/areas of focus \| H4\./';

    public function sources(): array
    {
        return ['about' => '06_About_Us'];
    }

    public function map(array $docs, string $locale): array
    {
        $c = $docs['about'];
        $content = [];
        $members = [];

        // ---------------------------------------------------------------- breadcrumb
        $c->expectNote('/^Breadcrumb:/');
        $breadcrumb = [
            $this->link($c, 'P06-L001', 'P06.breadcrumb.0'),
            // The current page is plain text, not a link.
            ['label' => $c->take('P06.breadcrumb.1'), 'target' => null],
        ];

        // ---------------------------------------------------------------- hero
        $c->expectNote('/^About hero \|/');
        $h1 = $c->takeHeading(1, 'P06.h1');
        $content['hero'] = [
            'lead' => $c->take('P06.hero.lead'),
            'paragraphs' => $c->takeParagraphs('P06.hero.paragraphs'),
            'links' => [
                $this->link($c, 'P06-L002', 'P06.hero.links.0'),
                $this->link($c, 'P06-L003', 'P06.hero.links.1'),
            ],
        ];

        // ---------------------------------------------------------------- story
        $content['story'] = [
            'heading' => $this->section($c, 'Purpose and company story', 'P06.story.heading'),
            'paragraphs' => $c->takeParagraphs('P06.story.paragraphs'),
        ];

        // ---------------------------------------------------------------- approach (six principles)
        $approach = ['heading' => $this->section($c, 'How the firm works', 'P06.approach.heading'), 'principles' => []];
        for ($i = 0; $i < self::PRINCIPLES; $i++) {
            $approach['principles'][] = [
                'title' => $c->takeHeading(3, "P06.approach.principles.{$i}.title"),
                'text' => $c->take("P06.approach.principles.{$i}.text"),
            ];
        }
        $content['approach'] = $approach;

        // ---------------------------------------------------------------- process (four steps)
        $process = ['heading' => $this->section($c, 'Four-step working process', 'P06.process.heading'), 'steps' => []];
        for ($i = 0; $i < self::STEPS; $i++) {
            $process['steps'][] = [
                // The approved H3 carries its own sequence number ("1. …", Arabic "١. …"); kept verbatim.
                'title' => $c->takeHeading(3, "P06.process.steps.{$i}.title"),
                'text' => $c->take("P06.process.steps.{$i}.text"),
            ];
        }
        $process['links'] = [
            $this->link($c, 'P06-L004', 'P06.process.links.0'),
            $this->link($c, 'P06-L005', 'P06.process.links.1'),
        ];
        $content['process'] = $process;
        if (preg_match('/^(\p{Nd}+\.)\s/u', $process['steps'][0]['title'], $m)) {
            $this->report->add('note', 'P06 process steps', 'The approved H3 step titles include their own sequence number ("'.$m[1].'" …); render them verbatim without adding a list counter.', $locale);
        }

        // ---------------------------------------------------------------- team introduction + profile menu
        $teamNote = $c->expectNote('/^TEAM section \|/');
        if (! preg_match('/\bid="([a-z0-9-]+)"/', $teamNote->note, $anchor)) {
            throw new ImportException($c->where($teamNote).' TEAM section note names no id');
        }
        $team = [
            'anchor' => $anchor[1],
            'heading' => $this->section($c, 'Team introduction', 'P06.team.heading'),
            'intro' => $c->take('P06.team.intro'),
            'menu' => [],
        ];
        foreach (array_keys(self::TEAM) as $i => $code) {
            $item = $this->link($c, sprintf('P06-L%03d', 6 + $i), "P06.team.menu.{$i}");
            if (($item['target'] ?? null) !== $code) {
                throw new ImportException($c->where()." team menu item {$i} must jump to #{$code}");
            }
            $team['menu'][] = $item;
        }
        $content['team'] = $team;

        // ---------------------------------------------------------------- full profiles, mandatory order
        $position = 0;
        foreach (self::TEAM as $code => $portrait) {
            $position++;
            $f = "team_members.{$code}";
            $c->expectNote('/^TEAM-0'.$position.' \|/');
            $set = $c->expectNote('/^Set id="'.$code.'"/');
            if (! $set->noteMatches('/\bPosition '.$position.'\b/')) {
                throw new ImportException($c->where($set)." {$code} must be team position {$position}");
            }
            // Portrait alt attribute: accessibility text only, never a visible caption.
            $c->expectNote('/^Image alt attribute for PHOTO TEAM-0'.$position.'\b/');
            $alt = $c->take("{$f}.alt");
            $c->expectNote('/display name \| H3\./');
            $member = [
                'anchor' => $code,
                'position' => $position,
                'portrait' => $portrait,
                'name' => $c->takeHeading(3, "{$f}.name"),
                'role' => $c->take("{$f}.role"),
                'focus' => $c->take("{$f}.focus"),
                'bio' => $c->takeParagraphs("{$f}.bio", self::AREAS),
                'alt' => $alt,
            ];
            $c->expectNote(self::AREAS);
            $member['areas'] = [
                'heading' => $c->takeHeading(4, "{$f}.areas.heading"),
                'items' => $c->takeBullets("{$f}.areas.items"),
            ];
            $c->expectNote('/^Public working languages\b/');
            $member['languages'] = $c->take("{$f}.languages");
            $member['links'] = [];
            foreach (self::PROFILE_LINKS[$code] as $i => $id) {
                $member['links'][] = $this->link($c, $id, "{$f}.links.{$i}");
            }
            $members[$code] = $member;

            $label = $team['menu'][$position - 1]['label'];
            if ($label !== $member['name']) {
                $this->report->add('label-mismatch', "P06 team menu {$code}", "Menu label \"{$label}\" differs from the profile name \"{$member['name']}\"", $locale);
            }
        }

        // ---------------------------------------------------------------- roles and scope
        $content['scope'] = [
            'heading' => $this->section($c, 'Professional roles and scope', 'P06.scope.heading'),
            'paragraphs' => $c->takeParagraphs('P06.scope.paragraphs'),
        ];

        // ---------------------------------------------------------------- engagement and confidentiality
        $content['confidentiality'] = [
            'heading' => $this->section($c, 'Engagement clarity and confidentiality', 'P06.confidentiality.heading'),
            'paragraphs' => $c->takeParagraphs('P06.confidentiality.paragraphs'),
            'links' => [$this->link($c, 'P06-L015', 'P06.confidentiality.links.0')],
        ];

        // ---------------------------------------------------------------- closing consultation section
        $content['cta'] = [
            'heading' => $this->section($c, 'Final consultation section', 'P06.cta.heading'),
            'paragraphs' => $c->takeParagraphs('P06.cta.paragraphs'),
            'consultation' => $this->link($c, 'P06-L016', 'P06.cta.consultation'),
            'whatsapp' => $this->link($c, 'P06-L017', 'P06.cta.whatsapp', $waHref),
            'phone' => $this->link($c, 'P06-L018', 'P06.cta.phone'),
            'email' => $this->link($c, 'P06-L019', 'P06.cta.email'),
            'links' => [
                $this->link($c, 'P06-L020', 'P06.cta.links.0'),
                $this->link($c, 'P06-L021', 'P06.cta.links.1'),
            ],
        ];

        // ---------------------------------------------------------------- WhatsApp draft + metadata
        $c->expectNote('/^WhatsApp draft\b/');
        $draft = $c->take('P06.whatsapp_draft');
        if ($this->whatsappDraft((string) $waHref) !== $draft) {
            $this->report->add('source-conflict', 'P06 WA01', 'The encoded WhatsApp link (P06-L017) does not decode to the black draft text', $locale);
        }
        $c->expectNote('/^SEO title\b/');
        $seoTitle = $c->take('P06.seo_title');
        $c->expectNote('/^Meta description\b/');
        $metaDescription = $c->take('P06.meta_description');

        return [
            'pages' => ['P06' => [
                'h1' => $h1,
                'seo_title' => $seoTitle,
                'meta_description' => $metaDescription,
                'breadcrumb' => $breadcrumb,
                'whatsapp_draft' => $draft,
                'content' => $content,
            ]],
            'team_members' => $members,
        ];
    }

    /** Move past the red "<label> | H2." marker and take the H2 that follows it. */
    private function section(Cursor $c, string $label, string $field): string
    {
        $c->expectNote('/^'.preg_quote($label, '/').' \| H2\./');

        return $c->takeHeading(2, $field);
    }

    /**
     * Take the black linked label that follows the red link marker $id.
     *
     * Internal destinations become the ID named in the red note ("-> S04: …" → S04, "-> WA01: …"
     * → WA01) or its page fragment ("-> #team-ahmed." and "-> /ar/about/#team-ahmed." → team-ahmed).
     * Telephone and e-mail keep their real href. The embedded hyperlink is checked against the
     * destination written in the note; the raw href is passed back through $href.
     *
     * @return array{label: string, target?: string, href?: string}
     */
    private function link(Cursor $c, string $id, string $field, ?string &$href = null): array
    {
        $marker = $c->expectNote('/^'.preg_quote($id, '/').' \|/');
        $link = $c->takeLink($field);
        $href = $link['href'];
        $mismatch = fn (string $expected) => $this->report->add('source-conflict', "P06 {$id}",
            "Embedded hyperlink {$href} does not match the destination {$expected} in the red note", $c->locale());

        if (preg_match('/->\s*(?:TEL|EMAIL):\s*(\S+?)\.?(?=\s|$)/', $marker->note, $m)) {
            if ($href !== $m[1]) {
                $mismatch($m[1]);
            }

            return ['label' => $link['label'], 'href' => $href];
        }
        if (preg_match('/->\s*([A-Z]{1,4}\d{2})\b(?::\s*(\/\S*?)\.?(?=\s|$))?/', $marker->note, $m)) {
            if (($m[2] ?? '') !== '' && ! str_ends_with($href, $m[2])) {
                $mismatch($m[2]);
            }

            return ['label' => $link['label'], 'target' => $m[1]];
        }
        if (preg_match('/->\s*[^\s#]*#([a-z0-9-]+)/', $marker->note, $m)) {
            if (parse_url($href, PHP_URL_FRAGMENT) !== $m[1]) {
                $mismatch('#'.$m[1]);
            }

            return ['label' => $link['label'], 'target' => $m[1]];
        }

        throw new ImportException($c->where($marker)." {$id} names no destination");
    }
}
