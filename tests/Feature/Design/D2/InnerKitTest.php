<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Prefill;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Design 2 inner-page kit (resources/views/components/d2/inner/*; owner: inner-kit): head, prose, layout,
 * toc, progress, cta-card and related, the generic fallback template for every page and service, and the
 * styleguide's inner area. Components are rendered directly with approved content.
 */
final class InnerKitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function render(string $blade, array $data = [], string $locale = 'en'): string
    {
        app()->setLocale($locale);

        return Blade::render($blade, $data);
    }

    private function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    // ------------------------------------------------------------------ head

    public function test_head_renders_breadcrumbs_title_intro_and_slots_without_a_large_title(): void
    {
        $html = $this->render('<x-d2.inner.head :title="$t" :crumbs="$c" :intro="$i"><x-slot:meta><p class="m">M</p></x-slot:meta><x-slot:actions><a href="#x">A</a></x-slot:actions></x-d2.inner.head>', [
            't' => 'Privacy Policy',
            'c' => [['label' => 'Home', 'href' => SiteUrl::home()], ['label' => 'Privacy Policy', 'href' => null]],
            'i' => ['First paragraph.', '', 'Second paragraph.'],
        ]);
        $this->assertStringContainsString('<nav class="crumbs" aria-label="'.e(proposed('nav.breadcrumb')).'">', $html);
        $this->assertStringContainsString('<a class="crumbs__link" href="'.SiteUrl::home().'">Home</a>', $html);
        $this->assertStringContainsString('<span class="crumbs__current" aria-current="page">Privacy Policy</span>', $html);
        $this->assertStringContainsString('<h1 class="inner-head__title" id="page-title">Privacy Policy</h1>', $html);
        $this->assertSame(2, substr_count($html, 'class="inner-head__intro"'), 'empty paragraphs are dropped');
        $this->assertStringContainsString('<div class="inner-head__meta"><p class="m">M</p></div>', $html);
        $this->assertStringContainsString('<div class="inner-head__actions"><a href="#x">A</a></div>', $html);
        // Design 2.1 (brief §3.6 rules 5, 7 and 8): no icon object, no eyebrow, no large-title collapse and no
        // bar title cut with an ellipsis; the current crumb is plain text that wraps.
        $this->assertStringNotContainsString('data-large-title', $html);
        $this->assertStringNotContainsString('<template', $html);
        $this->assertStringNotContainsString('bar-title', $html);
        $this->assertStringNotContainsString('inner-head__object', $html);
        $this->assertStringNotContainsString('eyebrow', $html);
    }

    public function test_head_options_document_size_long_words_and_specimen_tag(): void
    {
        $html = $this->render('<x-d2.inner.head title="Debt Recovery" size="document" />');
        $this->assertStringContainsString('<header class="inner-head inner-head--document">', $html);
        $this->assertStringContainsString('<h1 class="inner-head__title" id="page-title">Debt Recovery</h1>', $html, 'a short title keeps the scale without hyphenation');

        // A word wider than a 360 px phone's column at the 40 px Display size may hyphenate (never shrink below the scale).
        $long = $this->render('<x-d2.inner.head title="Политика конфиденциальности" />', [], 'ru');
        $this->assertStringContainsString('class="inner-head__title inner-head__title--fit"', $long);

        $specimen = $this->render('<x-d2.inner.head title="T" title-tag="p" />');
        $this->assertStringNotContainsString('<h1', $specimen);
        $this->assertStringContainsString('<p class="inner-head__title" id="page-title">T</p>', $specimen);
    }

    public function test_head_isolates_latin_runs_on_arabic_pages(): void
    {
        $html = $this->render('<x-d2.inner.head title="قضايا USDT و VARA" />', [], 'ar');
        $this->assertStringContainsString('<bdi dir="ltr">USDT</bdi>', $html);
        $this->assertStringContainsString('<bdi dir="ltr">VARA</bdi>', $html);
    }

    // ------------------------------------------------------------------ prose

    public function test_prose_renders_canonical_sections_with_ids_and_verbatim_numbering(): void
    {
        $sections = content()->page('P08')['content']['sections'];
        $html = $this->render('<x-d2.inner.prose :sections="$s" />', ['s' => $sections]);
        foreach ($sections as $i => $section) {
            $id = sprintf('section-%02d', $i + 1);
            $this->assertStringContainsString('<section class="prose__section prose__section--default" id="'.$id.'">', $html);
            $this->assertStringContainsString('<h2 class="prose__heading">'.e($section['heading']).'</h2>', $html);
            foreach ($section['blocks'] as $block) {
                $this->assertStringContainsString('<p>'.e($block['text']).'</p>', $html);
            }
        }
        $this->assertStringNotContainsString('<ol', $html, 'the numbered headings get no list counter');

        $own = $this->render('<x-d2.inner.prose :sections="$s" id-prefix="x" :level="3" />', ['s' => [['heading' => 'A', 'id' => 'team', 'blocks' => ['p']], ['heading' => 'B', 'blocks' => ['q']]]]);
        $this->assertStringContainsString('id="team"', $own, 'a stored anchor is kept');
        $this->assertStringContainsString('id="x-02"', $own, 'ids follow the index in the array');
        $this->assertStringContainsString('<h3 class="prose__heading">A</h3>', $own);
    }

    public function test_prose_maps_raw_docx_shapes_in_their_stored_order(): void
    {
        $p07 = content()->page('P07')['content'];
        $p02 = content()->page('P02')['content'];
        $s01 = content()->service('S01');
        $html = $this->render('<x-d2.inner.prose :sections="$s" />', ['s' => [
            $p07['helps'],                                        // heading, items[], note
            $p07['after'],                                        // heading, steps[{title, text}]
            $s01['sections']['practical'],                        // items[{title, paragraphs, bullets}] → H3 sub-sections
            $p02['faq'],                                          // items[{question, answer}] → accordion
            $p02['scope'] + ['variant' => 'note'],                // scope note → callout with an H2
            ['blocks' => [['type' => 'links', 'items' => [['label' => 'Contact Summit', 'target' => 'P07'], ['label' => 'Source', 'url' => 'https://uaelegislation.gov.ae/'], ['label' => '+971 52 466 6191', 'target' => 'TEL']]]]],
        ]]);
        $text = $this->text($html);

        // list + note (callout) after it
        foreach ($p07['helps']['items'] as $item) {
            $this->assertStringContainsString('<li>'.e($item).'</li>', $html);
        }
        $this->assertLessThan(mb_strpos($text, $p07['helps']['note']), mb_strpos($text, end($p07['helps']['items'])));
        $this->assertStringContainsString('callout callout--info callout--sm prose__note', $html);

        // steps: the approved titles carry their own numbers, so no counter is drawn
        $this->assertStringContainsString('prose__steps prose__steps--self', $html);
        foreach ($p07['after']['steps'] as $step) {
            $this->assertStringContainsString(e($step['title']), $html);
            $this->assertStringContainsString(e($step['text']), $html);
        }

        // sub-sections
        $this->assertStringContainsString('<h3 class="prose__subheading">'.e($s01['sections']['practical']['items'][0]['title']).'</h3>', $html);

        // Q&A: Phase A accordion, every approved question and answer
        $this->assertStringContainsString('acc acc--flush prose__qa', $html);
        foreach ($p02['faq']['items'] as $qa) {
            $this->assertStringContainsString(e($qa['question']), $html);
            $this->assertStringContainsString(e($qa['answer']), $html);
        }

        // scope as a callout with a real heading
        $this->assertMatchesRegularExpression('/prose__section--note.*?<h2 class="callout__title">'.preg_quote(e($p02['scope']['heading']), '/').'<\/h2>/s', $html);

        // links: P07 follows the release rule, external opens a new tab; the telephone is the kit's contact channel
        // (Design 2.1: one channel pattern with the CTA panels and the footer), its number left to right
        $this->assertStringContainsString('href="'.SiteUrl::contact().'"', $html);
        $this->assertMatchesRegularExpression('/href="https:\/\/uaelegislation.gov.ae\/"\s+target="_blank" rel="noopener noreferrer"/', $html);
        $this->assertMatchesRegularExpression('#<ul class="channels prose__channels" role="list">\s*<li class="channels__item">\s*<a class="channel channel--phone" href="tel:\+971524666191">\s*<svg[^>]*data-icon="phone"[\s\S]*?<bdi class="channel__text" dir="ltr">\+971 52 466 6191</bdi>#', $html);
        $this->assertStringNotContainsString('link-row--phone', $html, 'the telephone is a channel row, not a bordered link row');

        // Design 2.1 (brief §3.6 rules 3 and 5): a page row has no glyph and no chevron; the external row keeps its ↗
        // at the end; the steps are numbered 1, 2 … (no leading zero).
        preg_match_all('/<a class="link-row link-row--(\w+)"[^>]*>(.*?)<\/a>/s', $html, $rows, PREG_SET_ORDER);
        $kinds = array_column($rows, 2, 1);
        $this->assertSame(['page', 'external'], array_keys($kinds), 'page and external links stay link rows, in the given order');
        $this->assertStringNotContainsString('data-icon', $kinds['page'] ?? 'x');
        $this->assertSame(1, substr_count($kinds['external'] ?? '', 'data-icon='));
        $this->assertStringContainsString('data-icon="external"', $kinds['external'] ?? '');
        $this->assertStringNotContainsString('data-icon="chevron"', $html);
        $this->assertStringNotContainsString('data-icon="arrow"', $html);
    }

    public function test_prose_panel_sections_draw_the_marked_list_of_the_service_pages(): void
    {
        // Design 2.1 (finding: one "mistakes to avoid" treatment): a panel section (the cool band) with a cross per
        // mistake, as on S01–S11, never a warm warning callout with plain bullets.
        $html = $this->render('<x-d2.inner.prose :sections="$s" :level="3" />', ['s' => [
            ['heading' => 'Common Mistakes to Avoid', 'id' => 'g-mistakes', 'variant' => 'panel', 'blocks' => [['type' => 'ul', 'marker' => 'cross', 'items' => ['One.', 'Two.', '']]]],
            ['heading' => 'Useful First Steps', 'id' => 'g-steps', 'blocks' => [['type' => 'ul', 'marker' => 'check', 'items' => ['Keep the contract.']]]],
        ]]);
        $this->assertMatchesRegularExpression('#<section class="prose__section prose__section--panel" id="g-mistakes">\s*<h3 class="prose__heading">Common Mistakes to Avoid</h3>\s*<ul class="svc-list svc-list--plain svc-list--danger prose__marks" role="list">#', $html);
        $this->assertSame(2, substr_count($html, 'data-icon="close"'), 'a cross per mistake (the empty item is dropped)');
        $this->assertMatchesRegularExpression('#id="g-steps">[\s\S]*?<ul class="svc-list svc-list--plain svc-list--success prose__marks" role="list">#', $html);
        $this->assertSame(1, substr_count($html, 'data-icon="check"'));
        $this->assertStringNotContainsString('callout', $html, 'no warning callout');
        $this->assertStringNotContainsString('prose__list', $html, 'no plain bullets');
    }

    public function test_channels_draw_one_row_per_channel_with_the_value_left_to_right(): void
    {
        // Design 2.1 (finding: one contact-channel pattern outside the contact block): the functional glyph, then the
        // page's approved label; a number or an address reads left to right, a label ("Call Us") follows the page.
        $html = $this->render('<x-d2.inner.channels :items="$i" inline />', ['i' => [
            ['kind' => 'phone', 'href' => 'tel:+971524666191', 'text' => '+971 52 466 6191'],
            ['kind' => 'email', 'href' => 'mailto:info@summitlegaluae.com', 'text' => 'info@summitlegaluae.com'],
            ['kind' => 'phone', 'href' => 'tel:+971524666191', 'text' => 'Call Us'],
            ['kind' => 'email', 'href' => 'mailto:info@summitlegaluae.com', 'text' => ''],
            'not a row',
        ]]);
        $this->assertStringContainsString('<ul class="channels channels--inline" role="list">', $html);
        $this->assertSame(3, substr_count($html, '<a class="channel channel--'), 'empty labels and malformed items are dropped');
        $this->assertMatchesRegularExpression('#<a class="channel channel--phone" href="tel:\+971524666191">\s*<svg class="icon channel__icon"[^>]*data-icon="phone"[^>]*>.*?</svg>\s*<bdi class="channel__text" dir="ltr">\+971 52 466 6191</bdi>#s', $html);
        $this->assertStringContainsString('<bdi class="channel__text" dir="ltr">info@summitlegaluae.com</bdi>', $html);
        $this->assertStringContainsString('data-icon="mail"', $html);
        $this->assertStringContainsString('<span class="channel__text">Call Us</span>', $html);
        $this->assertDoesNotMatchRegularExpression('/data-icon="(chevron|arrow|external)/', $html);
        $this->assertSame('', trim($this->render('<x-d2.inner.channels :items="[]" />')));
    }

    public function test_prose_isolates_contact_values_and_skips_empty_blocks(): void
    {
        $html = $this->render('<x-d2.inner.prose :blocks="$b" />', ['b' => ['Write to info@summitlegaluae.com or call +971 52 466 6191.', '', ['type' => 'p', 'text' => '  ']]], 'ar');
        $this->assertStringContainsString('<bdi dir="ltr">info@summitlegaluae.com</bdi>', $html);
        $this->assertStringContainsString('<bdi dir="ltr">+971 52 466 6191</bdi>', $html);
        $this->assertSame(1, substr_count($html, '<p>'));
        $this->assertStringNotContainsString('<script', $this->render('<x-d2.inner.prose :blocks="$b" />', ['b' => ['<script>alert(1)</script>']]), 'escaped');
    }

    // ------------------------------------------------------------------ toc + layout

    public function test_layout_places_the_toc_nav_the_bar_and_a_single_sheet(): void
    {
        $sections = content()->page('L01')['content']['sections'];
        $html = $this->render('<x-d2.inner.layout :toc="$s" main-id="read" as="article"><p>Main</p><x-slot:aside><div class="aside-x">A</div></x-slot:aside></x-d2.inner.layout>@stack(\'sheets\')', ['s' => $sections]);
        $this->assertStringContainsString('inner-layout inner-layout--side inner-layout--toc inner-layout--sticky', $html);
        $this->assertMatchesRegularExpression('/<article class="inner-layout__main"\s+id="read"\s*>/', $html);
        $this->assertMatchesRegularExpression('/<nav class="toc toc--nav" aria-labelledby="toc-nav-title"\s+data-toc\s*>/', $html);
        $this->assertStringContainsString('data-toc-bar', $html);
        $this->assertStringContainsString('data-toc-inline', $html);
        $this->assertSame(1, substr_count($html, 'data-sheet="toc"'));
        $this->assertStringContainsString('<div class="inner-layout__aside"><div class="aside-x">A</div></div>', $html);
        $this->assertSame(3 * count($sections), substr_count($html, 'href="#section-'));
        $this->assertStringContainsString('hidden>', $html, 'the bar button waits for JavaScript');
    }

    public function test_toc_skips_untitled_sections_and_the_static_specimen_has_no_hooks(): void
    {
        $html = $this->render('<x-d2.inner.toc :sections="$s" mode="nav" />', ['s' => [['blocks' => ['intro']], ['heading' => 'One'], ['label' => 'Two', 'id' => 'two']]]);
        $this->assertStringNotContainsString('#section-01', $html);
        $this->assertMatchesRegularExpression('/href="#section-02"\s*>One<\/a>/', $html);
        $this->assertMatchesRegularExpression('/href="#two"\s*>Two<\/a>/', $html);

        $static = $this->render('<x-d2.inner.toc :sections="$s" mode="both" :static="true" current="section-02" />@stack(\'sheets\')', ['s' => [['heading' => 'A'], ['heading' => 'B']]]);
        $this->assertStringNotContainsString('data-toc', $static);
        $this->assertStringNotContainsString('data-sheet', $static);
        $this->assertMatchesRegularExpression('/href="#section-02"\s+aria-current="true"\s*>B</', $static);

        $this->assertSame('', trim($this->render('<x-d2.inner.toc :sections="[]" />')));
    }

    public function test_progress_points_at_its_target(): void
    {
        $this->assertStringContainsString('data-reading-progress="kc-guides" aria-hidden="true"', $this->render('<x-d2.inner.progress target="kc-guides" />'));
        $this->assertStringContainsString('data-reading-progress="main-content"', $this->render('<x-d2.inner.progress />'));
    }

    // ------------------------------------------------------------------ cta card + related

    public function test_cta_card_uses_approved_labels_prefill_and_the_contact_constants(): void
    {
        config(['summit.contact_page_released' => false]);
        $s06 = content()->service('S06');
        $cta = $s06['sections']['cta'];
        $html = $this->render('<x-d2.inner.cta-card :heading="$c[\'heading\']" :text="$c[\'text\']" :note="$c[\'note\']" service="S06" :request-label="$c[\'request_label\']" :whatsapp-label="$c[\'whatsapp_label\']" :whatsapp-draft="$d" :email="true" />', ['c' => $cta, 'd' => $s06['whatsapp_draft']]);

        $this->assertStringContainsString('<aside class="cta-card cta-card--sticky on-light" id="cta-card"', $html);
        $this->assertStringContainsString('<h2 class="cta-card__title" id="cta-card-title">'.e($cta['heading']).'</h2>', $html);
        $this->assertStringContainsString('href="'.e(Prefill::url(['service' => 'S06'])).'"', $html);
        $this->assertStringContainsString(e($cta['request_label']), $html);
        $this->assertStringContainsString('href="https://wa.me/971524666191?text='.rawurlencode($s06['whatsapp_draft']).'"', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
        // telephone and e-mail: the kit's contact channels (glyph + the value left to right, normal figures)
        $this->assertMatchesRegularExpression('#<ul class="channels cta-card__channels" role="list">[\s\S]*?<a class="channel channel--phone" href="tel:\+971524666191">[\s\S]*?<bdi class="channel__text" dir="ltr">\+971 52 466 6191</bdi>#', $html);
        $this->assertMatchesRegularExpression('#<a class="channel channel--email" href="mailto:info@summitlegaluae.com">[\s\S]*?<bdi class="channel__text" dir="ltr">info@summitlegaluae.com</bdi>#', $html);
        $this->assertStringContainsString('data-hours-status', $html);
        // the approved hours line, each time bound to its am/pm (App\Design\D2\Hours::keepTimes(), D11)
        $this->assertStringContainsString(e(\App\Design\D2\Hours::keepTimes(content()->page('P07')['content']['details']['hours'])), $html);
        $this->assertStringContainsString(e($cta['note']), $html);
        $this->assertStringNotContainsString('btn--gold', $html, 'gold is the page hero\'s primary only');
        $this->assertStringContainsString('btn--ink', $html, 'the request is the ink button');

        // heading-level 0 (the service pages' rail card, whose heading is the page's own H2 further down): a label
        // that still names the aside, so heading navigation lists the section once
        $label = $this->render('<x-d2.inner.cta-card heading="Tell Us About Your Matter" :heading-level="0" :phone="false" :hours="false" />');
        $this->assertStringContainsString('aria-labelledby="cta-card-title"', $label);
        $this->assertStringContainsString('<p class="cta-card__title" id="cta-card-title">Tell Us About Your Matter</p>', $label);
        $this->assertDoesNotMatchRegularExpression('/<h[1-6]\b/', $label);

        $plain = $this->render('<x-d2.inner.cta-card :whatsapp="false" :phone="false" :hours="false" service="S99" />');
        $this->assertStringStartsWith('<div class="cta-card', trim($plain));
        $this->assertStringContainsString('href="'.e(Prefill::url()).'"', $plain, 'an unknown service code is not carried');
        $this->assertStringContainsString(e(ui('cta.request_consultation')), $plain);
        $this->assertStringNotContainsString('wa.me', $plain);
        $this->assertStringNotContainsString('tel:', $plain);
    }

    public function test_related_resolves_targets_and_drops_incomplete_items(): void
    {
        $html = $this->render('<x-d2.inner.related heading="Related" :items="$i" />', ['i' => [
            ['label' => 'Guide', 'target' => 'G01', 'text' => 'Approved summary'],
            ['label' => 'Law', 'url' => 'https://uaelegislation.gov.ae/x'],
            ['label' => '', 'target' => 'P02'],
            ['label' => 'No destination'],
        ]]);
        $this->assertStringContainsString('<section class="related related--links related--cols-3"', $html);
        $this->assertMatchesRegularExpression('/<h2 class="related__title" id="related-[a-f0-9]{6}-title">Related<\/h2>/', $html);
        $this->assertStringContainsString('href="'.SiteUrl::guide('G01').'"', $html);
        $this->assertStringContainsString('<span class="link-row__text">Approved summary</span>', $html);
        $this->assertMatchesRegularExpression('/href="https:\/\/uaelegislation.gov.ae\/x"\s+target="_blank" rel="noopener noreferrer"/', $html);
        $this->assertStringNotContainsString('No destination', $html);
        $this->assertSame(2, substr_count($html, 'class="link-row '));
        // Design 2.1: no object glyph or chevron on a row; the external row ends with its ↗ glyph only.
        $this->assertSame(1, substr_count($html, 'data-icon='));
        $this->assertStringContainsString('data-icon="external"', $html);

        $nav = $this->render('<x-d2.inner.related :items="$i" variant="cards" :columns="2" id="r" />', ['i' => [['label' => 'Privacy Policy', 'target' => 'L01']]]);
        $this->assertStringContainsString('<nav class="related related--cards related--cols-2" id="r"', $nav);
        $this->assertStringContainsString('aria-label="'.e(d2p('inner.related_label')).'"', $nav);
        $this->assertStringContainsString('href="'.SiteUrl::page('L01').'"', $nav);
        $this->assertStringNotContainsString('data-icon', $nav, 'text cards: no glyph, no arrow');

        $this->assertSame('', trim($this->render('<x-d2.inner.related :items="[]" />')));
    }

    // ------------------------------------------------------------------ generic template

    public function test_the_generic_template_renders_every_page_and_service_completely(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            app()->setLocale($locale);
            $cases = array_map(fn ($code) => ['page' => $code, 'service' => null], ['P02', 'P03', 'P04', 'P05', 'P06', 'P07', 'P08', 'L01', 'L02']);
            foreach (array_keys(config('summit.services')) as $code) {
                $cases[] = ['page' => null, 'service' => $code];
            }
            foreach ($cases as $case) {
                $record = $case['service'] ? content()->service($case['service']) : content()->page($case['page']);
                $h1 = (string) ($record['h1'] ?? '');
                $html = view('d2::inner.generic', [
                    'h1' => $h1,
                    'intro' => [],
                    'crumbs' => [['label' => ui('nav.home'), 'href' => SiteUrl::home()], ['label' => $h1, 'href' => null]],
                    'pageCode' => $case['page'],
                    'serviceCode' => $case['service'],
                ])->render();
                $label = ($case['page'] ?? $case['service']).' '.$locale;

                $this->assertSame(1, substr_count($html, '<h1'), "{$label}: one H1");
                $this->assertStringContainsString(e($h1).'</h1>', $html, $label);
                $this->assertStringContainsString('class="cta-card', $html, "{$label}: CTA card");
                $this->assertStringContainsString('class="prose', $html, "{$label}: prose");
                $this->assertStringNotContainsString('⟦', $html, "{$label}: no missing content key");

                // Every section heading with content of its own appears as a prose heading.
                $content = $case['service'] ? $record['sections'] : $record['content'];
                foreach (['who_for', 'matters', 'process', 'faq', 'story', 'approach', 'how_to', 'overlap', 'before', 'after', 'helps'] as $key) {
                    if (is_array($content[$key] ?? null) && ! empty($content[$key]['heading'])) {
                        $this->assertStringContainsString(e($content[$key]['heading']), $html, "{$label}: {$key} heading");
                    }
                }
            }
        }

        // A service's specialist line stays in its DOCX place (after the sources), never before the first section.
        app()->setLocale('en');
        $s01 = content()->service('S01');
        $svc = view('d2::inner.generic', ['h1' => $s01['h1'], 'intro' => [$s01['intro']], 'crumbs' => [], 'pageCode' => null, 'serviceCode' => 'S01'])->render();
        $this->assertSame(1, substr_count($svc, e($s01['sections']['specialist']['intro'])));
        $this->assertGreaterThan(strpos($svc, e($s01['sections']['who_for']['heading'])), strpos($svc, e($s01['sections']['specialist']['intro'])));
        $this->assertStringContainsString('href="https://'.$s01['sections']['specialist']['domain'].'/"', $svc);

        // P06: the stored #team anchor survives the fallback (site map A02).
        app()->setLocale('en');
        $about = view('d2::inner.generic', ['h1' => 'About', 'intro' => [], 'crumbs' => [], 'pageCode' => 'P06', 'serviceCode' => null])->render();
        $this->assertStringContainsString('id="team"', $about);
    }

    // ------------------------------------------------------------------ styleguide

    public function test_the_styleguide_shows_the_inner_area_without_a_second_h1(): void
    {
        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/styleguide/")->assertOk()->getContent();
            $start = strpos($html, '<section class="sg-section sg-inner" id="sg-inner">');
            $this->assertNotFalse($start, "{$locale}: inner area");
            $next = strpos($html, '<section class="sg-section', $start + 10);
            $area = substr($html, $start, ($next !== false ? $next : strpos($html, '</main>')) - $start);
            $this->assertStringNotContainsString('<h1', $area);
            $this->assertStringNotContainsString('data-large-title', $area);
            $this->assertStringNotContainsString('lt-demo', $area, 'no large-title specimen (Design 2.1)');
            $this->assertStringNotContainsString('data-toc', $area);
            $this->assertStringNotContainsString('data-reading-progress', $area);
            $this->assertStringContainsString('sheet sheet--static', $area);
            $this->assertStringContainsString('dir="'.($locale === 'ar' ? 'ltr' : 'rtl').'"', $area, 'other-direction specimens');
            $this->assertSame(1, substr_count($html, '<h1'), "{$locale}: one H1 on the styleguide");
        }
    }

    public function test_the_styleguide_direction_specimen_never_links_another_languages_whatsapp_draft(): void
    {
        // Brief 5.2 and 14.2: every WhatsApp link of a page opens that page's WA01. The CTA card of the other direction
        // (#sg-inner-cta-3) speaks the other language, so its WhatsApp button is shown, not live: no href, target or
        // rel, marked data-lint-specimen (the wizard specimen's treatment).
        $text = function (string $url): string {
            parse_str((string) parse_url(html_entity_decode($url), PHP_URL_QUERY), $query);

            return (string) ($query['text'] ?? '');
        };
        foreach (['en', 'ar', 'ru'] as $locale) {
            app()->setLocale($locale);
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/styleguide/")->assertOk()->getContent();
            $start = strpos($html, 'id="sg-inner-cta-3"');
            $this->assertNotFalse($start, "{$locale}: the direction specimen");
            $card = substr($html, $start, strpos($html, '</aside>', $start) - $start);
            $this->assertStringNotContainsString('wa.me', $card, "{$locale}: no live WhatsApp link in the specimen");
            $this->assertMatchesRegularExpression('#<a data-lint-specimen class="btn btn--whatsapp[^"]*cta-card__whatsapp"\s*>#', $card, $locale);
            preg_match_all('#href="(https://(?:wa\.me|api\.whatsapp\.com)/[^"]*)"#', $html, $links);
            $this->assertNotEmpty($links[1], $locale);
            app()->setLocale($locale);
            $own = $text((string) setting('whatsapp_url'));
            $this->assertNotSame('', $own, $locale);
            $this->assertSame([$own], array_values(array_unique(array_map($text, $links[1]))), "{$locale}: every WhatsApp link opens this language's WA01");
        }
    }

    public function test_the_scope_note_has_one_layout_on_every_page(): void
    {
        // Finding (brief §3.4, §3.5): the same scope note had three layouts. It is the kit's one note
        // (.inner-scope): as wide as the block it closes; container-wide notes (.inner-scope--wide: P02, P03, P04)
        // lay out on the page grid from 1024 px, the text on the column line of P02's approved sections.
        $pages = [
            '/en/practice-areas/' => 'pa-scope inner-scope inner-scope--wide',
            '/en/resources/' => 'kc-scope__prose inner-scope inner-scope--wide',
            '/en/case-studies/' => 'cs-scope__prose inner-scope inner-scope--wide',
            (string) parse_url(SiteUrl::service('S06', 'en'), PHP_URL_PATH) => 'svc-prose svc-prose--scope inner-scope',
        ];
        foreach ($pages as $path => $classes) {
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('class="prose prose--md '.$classes.'"', $html, $path);
        }
        $css = (string) file_get_contents(resource_path('css/d2/pages/inner.css'));
        $this->assertMatchesRegularExpression('/\.inner-scope \.callout \{\s*row-gap: var\(--s-4\);\s*padding: var\(--pad-card\);/', $css);
        $this->assertMatchesRegularExpression('/@media \(width >= 1024px\) \{\s*\.inner-scope--wide \.callout \{\s*grid-template-columns: repeat\(8, minmax\(0, 1fr\)\);\s*column-gap: var\(--gutter\);\s*padding-inline: 0;/', $css, 'the 8 columns of .pa-split from 1024');
        $this->assertMatchesRegularExpression('/@media \(width >= 1280px\) \{\s*\.inner-scope--wide \.callout \{\s*grid-template-columns: repeat\(12, minmax\(0, 1fr\)\);/', $css, 'the 12 columns from 1280');
        $service = (string) file_get_contents(resource_path('css/d2/pages/service.css'));
        $this->assertMatchesRegularExpression('/\.pa-split__body \{\s*grid-column: 4 \/ -1;/', $service, 'P02 sections: body from the 4th of 8 columns');
        $this->assertMatchesRegularExpression('/\.pa-split__body \{\s*grid-column: 5 \/ -1;/', $service, 'and from the 5th of 12');
        foreach (['service.css' => '.pa-scope', 'resources.css' => '.kc-scope__prose', 'cases.css' => '.cs-scope__prose'] as $file => $selector) {
            $this->assertStringNotContainsString($selector, (string) file_get_contents(resource_path('css/d2/pages/'.$file)), "{$file}: no page-specific scope layout");
        }
    }

    public function test_the_rail_cta_keeps_the_standard_44_px_buttons(): void
    {
        // Finding (brief §3.5): the rail card's buttons were 47-49 px with 12 px padding. The room the longest approved
        // label needs comes from the card's chrome (under the contents the CTA is a hairline-topped action group), never
        // from the button: no padding or --btn-pad override on its buttons.
        $css = (string) file_get_contents(resource_path('css/d2/pages/inner.css'));
        preg_match('/\.inner-layout__rail:has\(> \.toc--nav \+ \.inner-layout__aside\) \{.*?\n    \}\n/s', $css, $rail);
        $this->assertNotEmpty($rail);
        $this->assertStringNotContainsString('--btn-pad', $rail[0]);
        $this->assertDoesNotMatchRegularExpression('/\.cta-card__actions \.btn \{[^}]*padding/s', $rail[0]);
        $this->assertMatchesRegularExpression('/& \.cta-card \{\s*gap: var\(--s-3\);\s*padding: var\(--s-4\) 0 0;\s*border: 0;\s*border-block-start: var\(--line-w\) solid var\(--line\);/', $rail[0]);
    }
}
