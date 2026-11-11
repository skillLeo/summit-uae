<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Icons;
use App\Design\D2\Prefill;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 service pages S01–S11 and the P02 practice-areas hub (upgrade spec §12 D, §7.4; templates
 * resources/views/designs/d2/inner/{service,practice-areas}.blade.php). Rendered through the real routes with
 * the Design 2 cookie, in all three languages, against the approved imported content.
 */
final class ServicePageTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    /** Banned claims (upgrade spec §12 "Compliance", checklist 12), matched case-insensitively in the page body. */
    private const BANNED = ['law firm', '24/7', 'free consultation', 'years of experience', 'success rate', 'award-winning', 'top-rated'];

    /** @var array<string, string> */
    private array $pages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function d2(string $path): string
    {
        return $this->pages[$path] ??= $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])
            ->get($path)->assertOk()->getContent();
    }

    private function servicePath(string $code, string $locale): string
    {
        return '/'.$locale.'/practice-areas/'.config("summit.services.{$code}").'/';
    }

    /** The <main> element only (the shell carries other areas' copy). */
    private function main(string $html): string
    {
        $start = strpos($html, '<main');
        $end = strpos($html, '</main>');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    /** Text as Blade prints it (escaped). Arabic Latin runs are wrapped in <bdi>, so compare Arabic text without them. */
    private function plain(string $html): string
    {
        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertHasText(string $html, string $text, string $context): void
    {
        $this->assertStringContainsString(
            preg_replace('/\s+/u', ' ', trim($text)),
            preg_replace('/\s+/u', ' ', $this->plain($html)),
            $context
        );
    }

    public function test_every_service_page_renders_its_approved_head_with_prefilled_ctas(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (array_keys(config('summit.services')) as $code) {
                app()->setLocale($locale);
                $service = content()->service($code, $locale);
                $html = $this->d2($this->servicePath($code, $locale));
                $main = $this->main($html);
                $ctx = "{$code} {$locale}";

                $this->assertSame(1, substr_count($html, '<h1'), "one H1 ({$ctx})");
                $this->assertMatchesRegularExpression('#<h1[^>]*>.*?</h1>#s', $main);
                preg_match('#<h1[^>]*>(.*?)</h1>#s', $main, $h1);
                $this->assertSame(trim($service['h1']), trim($this->plain($h1[1])), "approved H1 ({$ctx})");
                $this->assertHasText($main, $service['intro'], "approved intro ({$ctx})");

                // Design 2.1 (brief §3.6 rules 3 and 5): no service object tile, no object glyph and no arrow on the page;
                // the hero's request is the page's one gold action (the hero primary), no other button is gold.
                $this->assertStringNotContainsString('inner-head__object', $main, $ctx);
                $this->assertStringNotContainsString('data-icon="'.Icons::forService($code).'"', $main, $ctx);
                $this->assertStringNotContainsString('data-icon="arrow"', $main, $ctx);
                $this->assertStringNotContainsString('data-icon="file"', $main, $ctx);
                $this->assertSame(1, substr_count($main, 'btn--gold'), "one gold action ({$ctx})");
                // The head holds no nested <header>, so its first </header> closes it.
                $this->assertMatchesRegularExpression('#<header class="inner-head[^"]*\bsvc-head\b[^"]*"[^>]*>(.*?)</header>#s', $main);
                preg_match('#<header class="inner-head[^"]*\bsvc-head\b[^"]*"[^>]*>(.*?)</header>#s', $main, $head);
                $this->assertStringContainsString('btn--gold', $head[1] ?? '', "the gold action is the hero's ({$ctx})");

                // The approved document checklist section: in the reading column (brief §5.3, the DOCX order: after
                // "How We Handle Your Matter"), never in the head; one H2, its four documents and the note.
                $this->assertStringNotContainsString('inner-head__aside', $head[1] ?? '', "no aside in the head ({$ctx})");
                $this->assertStringNotContainsString('id="documents"', $head[1] ?? '', "the checklist is not in the head ({$ctx})");
                $this->assertMatchesRegularExpression('#<section class="svc-sec svc-docs" id="documents"\s+aria-labelledby="documents-title"\s*>\s*<h2 class="prose__heading svc-sec__title" id="documents-title"\s*>#', $main, $ctx);
                $this->assertSame(1, preg_match_all('#<h2[^>]*>\s*'.preg_quote(e($service['sections']['documents']['heading']), '#').'\s*</h2>#', $main), "one H2 for the documents heading ({$ctx})");
                $this->assertHasText($main, $service['sections']['documents']['heading'], "documents heading ({$ctx})");
                foreach ($service['documents'] as $document) {
                    $this->assertHasText($main, $document, "document ({$ctx})");
                }
                $this->assertHasText($main, $service['documents_note'], "documents note ({$ctx})");
                $this->assertSame(1, substr_count($this->plain($main), $this->plain(e($service['documents_note']))), "documents note shown once ({$ctx})");

                // The approved CTA heading is one H2 (the final panel #request); the rail card repeats it as a label that
                // names its aside, so heading navigation lists the section once (brief §5.2).
                $heading = e($service['sections']['cta']['heading']);
                $this->assertSame(1, preg_match_all('#<h2[^>]*>\s*'.preg_quote($heading, '#').'\s*</h2>#', $main), "one H2 for the CTA heading ({$ctx})");
                $this->assertMatchesRegularExpression('#<aside class="cta-card[^"]*" id="svc-cta-card"\s+aria-labelledby="svc-cta-card-title"\s*>\s*<p class="cta-card__title" id="svc-cta-card-title">'.preg_quote($heading, '#').'</p>#', $main, "the rail card's title is a label ({$ctx})");

                // CTAs: the consultation prefilled with the service, WhatsApp with the service's approved draft.
                $consult = e(Prefill::url(['service' => $code], $locale));
                $this->assertStringContainsString('href="'.$consult.'"', $main, $ctx);
                $this->assertStringContainsString('?service='.$code.'#consultation-form', html_entity_decode($consult), $ctx);
                $whatsapp = 'https://wa.me/971524666191?text='.rawurlencode($service['whatsapp_draft']);
                $this->assertStringContainsString('href="'.e($whatsapp).'"', $main, "service WhatsApp draft ({$ctx})");
                $this->assertStringNotContainsString('api.whatsapp.com/send', $main);

                // The controller's SEO decides indexing (service pages are noindex previews): kept.
                $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html, $ctx);
            }
        }
    }

    public function test_every_approved_section_is_rendered_in_docx_order(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (array_keys(config('summit.services')) as $code) {
                app()->setLocale($locale);
                $service = content()->service($code, $locale);
                $s = $service['sections'];
                $text = preg_replace('/\s+/u', ' ', $this->plain($this->main($this->d2($this->servicePath($code, $locale)))));
                $ctx = "{$code} {$locale}";

                $headings = array_values(array_filter([
                    $s['who_for']['heading'], $s['matters']['heading'], $s['practical']['heading'], $s['process']['heading'],
                    $s['documents']['heading'], $s['next_step']['heading'], $s['faq']['heading'], $s['related']['heading'], $s['sources']['heading'],
                    $s['cta']['heading'], $s['scope']['heading'],
                ]));
                $last = -1;
                foreach ($headings as $heading) {
                    $at = mb_strpos($text, preg_replace('/\s+/u', ' ', $heading), $last + 1);
                    $this->assertNotFalse($at, "heading «{$heading}» ({$ctx})");
                    $this->assertGreaterThan($last, $at, "«{$heading}» after the previous section ({$ctx})");
                    $last = $at;
                }

                $items = array_merge(
                    $s['who_for']['items'], $s['matters']['items'], $s['process']['steps'], $service['documents'], [$service['documents_note']],
                    $service['first_steps'], $service['mistakes'],
                    [$s['next_step']['first_steps_heading'], $s['next_step']['mistakes_heading'], $s['cta']['text'], $s['cta']['note']],
                    $s['scope']['paragraphs'], [$s['scope']['link_label']],
                );
                foreach ($s['practical']['items'] as $item) {
                    $items = array_merge($items, [$item['title']], $item['paragraphs'], $item['bullets']);
                }
                foreach ($s['faq']['items'] as $faq) {
                    $items[] = $faq['question'];
                    $items[] = $faq['answer'];
                }
                if ($s['process']['intro']) {
                    $items[] = $s['process']['intro'];
                }
                foreach ($items as $item) {
                    $this->assertStringContainsString(preg_replace('/\s+/u', ' ', trim($item)), $text, "approved copy ({$ctx}): ".mb_substr($item, 0, 60));
                }
            }
        }
    }

    public function test_faqs_situations_and_links_resolve(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (array_keys(config('summit.services')) as $code) {
                app()->setLocale($locale);
                $service = content()->service($code, $locale);
                $s = $service['sections'];
                $html = $this->d2($this->servicePath($code, $locale));
                $main = $this->main($html);
                $ctx = "{$code} {$locale}";

                // FAQ anchors (S01-Q01 → s01-q01), full answers in the HTML.
                foreach ($s['faq']['items'] as $faq) {
                    $this->assertStringContainsString('id="'.strtolower($faq['id']).'"', $main, $ctx);
                }

                // The four Navigator situations open the home Navigator on that starting file (works without JS).
                foreach (array_values($service['situations']) as $i => $situation) {
                    $ref = $code.'-'.($i + 1);
                    $this->assertStringContainsString('href="'.e(SiteUrl::home($locale).'?navigator='.$ref.'#navigator').'"', $main, $ctx);
                    $this->assertHasText($main, $situation, "situation ({$ctx})");
                }

                // Related topics: every approved label, linked to its registered destination.
                foreach ($s['related']['links'] as $link) {
                    $this->assertStringContainsString('href="'.e(SiteUrl::target($link['target'], $locale)).'"', $main, "{$link['target']} ({$ctx})");
                    $this->assertHasText($main, $link['label'], "related label ({$ctx})");
                }

                // Official sources and the specialist site open in a new tab with noopener noreferrer.
                foreach ($s['sources']['links'] as $source) {
                    $this->assertMatchesRegularExpression('#href="'.preg_quote(e($source['url']), '#').'" target="_blank" rel="noopener noreferrer"#', $main, $ctx);
                }
                $site = $service['specialist'] ? content()->record('specialist_sites', $service['specialist'], $locale) : null;
                if ($site) {
                    $this->assertMatchesRegularExpression('#href="'.preg_quote(e($site['url']), '#').'" target="_blank" rel="noopener noreferrer"#', $main, $ctx);
                    $this->assertHasText($main, $s['specialist']['label'], $ctx);
                    $this->assertStringContainsString('dir="ltr">'.$s['specialist']['domain'].'</bdi>', $main, $ctx);
                } else {
                    $this->assertStringNotContainsString('data-specialist', $main, $ctx);
                }

                // Related FAQ centre category (faq_categories.links → this service): kept Design 1 anchors.
                $category = collect(content()->faqCategories($locale))
                    ->first(fn ($c) => collect($c['links'] ?? [])->contains(fn ($l) => ($l['target'] ?? null) === $code));
                $this->assertNotNull($category, $ctx);
                $this->assertStringContainsString('href="'.e(SiteUrl::page('P05', $locale, $category['anchor'])).'"', $main, $ctx);
                foreach (content()->faqs($locale) as $faq) {
                    if ($faq['category'] === $category['anchor']) {
                        $this->assertStringContainsString('href="'.e(SiteUrl::page('P05', $locale, $faq['anchor'])).'"', $main, $ctx);
                    }
                }

                // Legal notice link of the scope section.
                $this->assertStringContainsString('href="'.e(SiteUrl::page('P08', $locale)).'"', $main, $ctx);
            }
        }
    }

    public function test_every_in_page_anchor_resolves(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (array_merge(['P02'], array_keys(config('summit.services'))) as $code) {
                $path = $code === 'P02' ? "/{$locale}/practice-areas/" : $this->servicePath($code, $locale);
                $html = $this->d2($path);
                preg_match_all('/\sid="([^"]+)"/', $html, $ids);
                $ids = array_count_values($ids[1]);
                foreach ($ids as $id => $count) {
                    $this->assertSame(1, $count, "id #{$id} is unique ({$path})");
                }
                preg_match_all('/href="#([^"]+)"/', $html, $hrefs);
                foreach (array_unique($hrefs[1]) as $anchor) {
                    $this->assertArrayHasKey($anchor, $ids, "#{$anchor} exists ({$path})");
                }
            }
        }
    }

    public function test_service_page_table_of_contents_lists_every_section(): void
    {
        app()->setLocale('en');
        $html = $this->d2($this->servicePath('S06', 'en'));
        foreach (['who-for', 'matters', 'practical', 'process', 'documents', 'next-steps', 'situations', 'faq', 'related', 'sources', 'request', 'scope'] as $id) {
            $this->assertStringContainsString('href="#'.$id.'"', $html, $id);
            $this->assertMatchesRegularExpression('/<(section|div|aside)[^>]*\sid="'.$id.'"/', $html, $id);
        }
        // The mobile TOC sheet (inner kit) is pushed to the body-level sheets stack.
        $this->assertStringContainsString('data-sheet="toc"', $html);
        // The kit's sticky side CTA card carries the same prefilled consultation link.
        $this->assertMatchesRegularExpression('#id="svc-cta-card"[\s\S]*?href="'.preg_quote(e(Prefill::url(['service' => 'S06'], 'en')), '#').'"#', $html);
    }

    public function test_arabic_isolates_latin_runs_phone_and_email(): void
    {
        app()->setLocale('ar');
        $html = $this->main($this->d2($this->servicePath('S03', 'ar')));
        $this->assertStringContainsString('<bdi dir="ltr">USDT</bdi>', $html);
        // the final panel's telephone and e-mail: the kit's contact channels, each value one left-to-right run
        $this->assertStringContainsString('<bdi class="channel__text" dir="ltr">+971 52 466 6191</bdi>', $html);
        $this->assertStringContainsString('<bdi class="channel__text" dir="ltr">info@summitlegaluae.com</bdi>', $html);
        $this->assertStringContainsString('href="tel:+971524666191"', $html);
        $this->assertStringContainsString('href="mailto:info@summitlegaluae.com"', $html);
    }

    public function test_the_hub_lists_every_service_with_its_approved_card_copy(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $page = content()->page('P02', $locale);
            $html = $this->d2("/{$locale}/practice-areas/");
            $main = $this->main($html);

            $this->assertSame(1, substr_count($html, '<h1'), $locale);
            $this->assertHasText($main, $page['h1'], $locale);
            $this->assertHasText($main, $page['content']['intro'], $locale);
            foreach (['start', 'choose', 'overlap', 'before', 'faq', 'resources', 'cta', 'scope'] as $key) {
                $this->assertHasText($main, $page['content'][$key]['heading'], "{$key} ({$locale})");
            }
            $this->assertSame(11, substr_count($main, 'class="card card--default card--pad-md card--interactive card--link pa-card"'), $locale);
            foreach (content()->services($locale) as $service) {
                $this->assertStringContainsString('href="'.e(SiteUrl::service($service['code'], $locale)).'"', $main, $locale);
                $this->assertHasText($main, $service['hub_title'], $locale);
                $this->assertHasText($main, $service['hub_summary'], $locale);
                $this->assertHasText($main, $service['hub_link_label'], $locale);
                foreach ($service['situations'] as $situation) {
                    $this->assertHasText($main, $situation, $locale);
                }
            }
            $this->assertHasText($main, ui('service.situations_intro'), $locale);
            // Resources and the legal notice resolve to their pages; the WhatsApp link carries the P02 draft.
            $this->assertStringContainsString('href="'.e(SiteUrl::page('P03', $locale)).'"', $main, $locale);
            $this->assertStringContainsString('href="'.e(SiteUrl::page('P05', $locale)).'"', $main, $locale);
            $this->assertStringContainsString('href="'.e(SiteUrl::page('P08', $locale)).'"', $main, $locale);
            $this->assertStringContainsString(e('https://wa.me/971524666191?text='.rawurlencode($page['whatsapp_draft'])), $main, $locale);
        }
    }

    public function test_the_hub_audience_filter_works_without_javascript(): void
    {
        app()->setLocale('en');
        $all = $this->main($this->d2('/en/practice-areas/'));
        $this->assertSame(0, substr_count($all, ' hidden="hidden"'), 'every card shows by default');
        foreach (['all', 'individual', 'business', 'investor'] as $audience) {
            $this->assertStringContainsString('data-pa-audience="'.$audience.'"', $all);
        }
        // Chips are links (the filter without JavaScript); "All" is the current one.
        $this->assertMatchesRegularExpression('#<a class="chip chip--filter"[^>]*aria-current="true"[^>]*data-pa-audience="all"|<a class="chip chip--filter"[^>]*data-pa-audience="all"[^>]*aria-current="true"#', $all);
        $this->assertStringContainsString('href="'.e(SiteUrl::page('P02', 'en').'?audience=business#services').'"', $all);

        $business = $this->main($this->d2('/en/practice-areas/?audience=business'));
        $mapped = config('audience_map.hero.business');
        foreach (array_keys(config('summit.services')) as $code) {
            preg_match('#<li class="[^"]*pa-card"[^>]*data-service="'.$code.'"[^>]*>#', $business, $card);
            $this->assertNotEmpty($card, $code);
            $this->assertSame(! in_array($code, $mapped, true), str_contains($card[0], 'hidden'), "{$code} visibility for business");
        }

        // Untrusted values: unknown and array values show every card.
        foreach (['/en/practice-areas/?audience=nobody', '/en/practice-areas/?audience[]=business'] as $path) {
            $this->assertSame(0, substr_count($this->main($this->d2($path)), ' hidden="hidden"'), $path);
        }
    }

    public function test_proposed_copy_exists_in_every_language_and_is_marked(): void
    {
        foreach (['service_page', 'practice_areas'] as $area) {
            $en = require lang_path("en/proposed-d2/{$area}.php");
            foreach (['ar', 'ru'] as $locale) {
                $other = require lang_path("{$locale}/proposed-d2/{$area}.php");
                $this->assertSame(array_keys($en), array_keys($other), "{$area} keys ({$locale})");
                foreach ($other as $key => $value) {
                    $this->assertNotSame($en[$key], $value, "{$area}.{$key} is translated ({$locale})");
                }
            }
        }
        // Plurals: Russian 3 forms, Arabic 6.
        $this->assertCount(3, explode('|', (require lang_path('ru/proposed-d2/practice_areas.php'))['result']));
        $this->assertCount(6, explode('|', (require lang_path('ar/proposed-d2/practice_areas.php'))['result']));

        $html = $this->main($this->d2($this->servicePath('S01', 'en')));
        $this->assertMatchesRegularExpression('#<span class="svc-sit__open" data-copy-status="proposed">#', $html);
        $hub = $this->main($this->d2('/en/practice-areas/'));
        $this->assertMatchesRegularExpression('#class="pa-filter__label"[^>]*data-copy-status="proposed"#', $hub);
    }

    public function test_no_banned_claims_on_these_pages(): void
    {
        foreach (self::LOCALES as $locale) {
            foreach (array_merge(['P02'], array_keys(config('summit.services'))) as $code) {
                $path = $code === 'P02' ? "/{$locale}/practice-areas/" : $this->servicePath($code, $locale);
                $text = mb_strtolower($this->plain($this->main($this->d2($path))));
                foreach (self::BANNED as $phrase) {
                    $this->assertStringNotContainsString($phrase, $text, "{$phrase} on {$path}");
                }
            }
        }
    }

    public function test_design_one_keeps_its_own_preview_pages(): void
    {
        $service = $this->get($this->servicePath('S01', 'en'))->assertOk()->getContent();
        $hub = $this->get('/en/practice-areas/')->assertOk()->getContent();
        foreach ([$service, $hub] as $html) {
            $this->assertStringNotContainsString('data-design="2"', $html);
            $this->assertStringNotContainsString('svc-page', $html);
            $this->assertStringNotContainsString('pa-page', $html);
        }
    }

    public function test_the_head_has_no_aside_and_the_checklist_follows_the_process_in_docx_order(): void
    {
        // Phase 2 (brief §5.3, DOCX conformance): the "Documents That Help Us Get Started" card was the head's aside, so
        // it was read before "Who This Service Is For"; the DOCX puts it after "How We Handle Your Matter". The head has
        // no aside now, so the one-grid layout that filled the space under a tall card (.svc-page display: grid,
        // display: contents on the head and the reading layout) is gone: the kit's head, then the kit's reading layout.
        $css = file_get_contents(resource_path('css/d2/pages/service.css'));
        $this->assertDoesNotMatchRegularExpression('/\.svc-page[^{]*\{[^}]*display: (grid|contents)/', $css);
        $this->assertStringNotContainsString('.svc-head .inner-head__aside', $css);

        foreach (self::LOCALES as $locale) {
            foreach (array_keys(config('summit.services')) as $code) {
                app()->setLocale($locale);
                $s = content()->service($code, $locale)['sections'];
                $main = $this->main($this->d2($this->servicePath($code, $locale)));
                $ctx = "{$code} {$locale}";
                $this->assertMatchesRegularExpression('#<div class="svc-page"[^>]*>\s*<header class="inner-head inner-head--document svc-head"[^>]*>\s*<div class="container inner-head__inner">\s*<div class="inner-head__main">#', $main, $ctx);
                $this->assertMatchesRegularExpression('#</header>\s*<div class="inner-layout[^"]*svc-layout[^"]*">\s*<div class="container inner-layout__grid">\s*<div class="inner-layout__side">#', $main, $ctx);
                // In the markup: the process section, then the checklist, then "Before Taking the Next Step".
                $process = strpos($main, 'id="process"');
                $documents = strpos($main, '<section class="svc-sec svc-docs" id="documents"');
                $next = strpos($main, 'id="next-steps"');
                $this->assertNotFalse($process, $ctx);
                $this->assertNotFalse($documents, $ctx);
                $this->assertNotFalse($next, $ctx);
                $this->assertGreaterThan($process, $documents, "the checklist follows the process ({$ctx})");
                $this->assertGreaterThan($documents, $next, "the checklist precedes the next steps ({$ctx})");
                // "On this page" lists it between the two.
                preg_match_all('#href="\#(process|documents|next-steps)"#', $main, $toc);
                $this->assertSame(['process', 'documents', 'next-steps'], array_slice(array_values(array_unique($toc[1])), 0, 3), $ctx);
                $this->assertStringContainsString(e($s['documents']['heading']), $main, $ctx);
            }
        }
    }

    public function test_situation_cards_put_their_action_where_the_kit_does(): void
    {
        // Finding: the situation card's "Show the starting file" sat at the inline end in ink-2; the kit's cards (P02
        // "Service Details", guides, cases) put the action at the inline start in ink. No align-self on the footer of
        // the flex-column card (it moved the line across the card, not down).
        $css = file_get_contents(resource_path('css/d2/pages/service.css'));
        $this->assertMatchesRegularExpression('/\.svc-sit__foot \{\s*justify-content: flex-start;\s*color: var\(--ink\);\s*font-size: var\(--fs-ui\);/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.svc-sit__foot \{[^}]*align-self/', $css);
    }

    public function test_static_panels_card_text_and_narrow_russian_text_follow_the_roles(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/pages/service.css'));
        // "Useful First Steps" and "Mistakes to Avoid": one surface, the band panel of P03's mistakes (no white bordered
        // card beside a band panel: a card reads as a clickable listing, brief §3.5).
        $this->assertMatchesRegularExpression('/\.svc-next__card \{\s*display: grid;\s*gap: var\(--s-4\);\s*align-content: start;\s*padding: var\(--pad-card\);\s*background: var\(--band\);\s*border-radius: var\(--radius-card\);\s*\}/', $css);
        $this->assertStringNotContainsString('.svc-next__card--avoid', $css);
        // Brief §3.2 roles: a situation is card text (Body 17 / 400); P02's list lead-in and its phone disclosure are
        // labels (UI 15 / 500).
        $this->assertMatchesRegularExpression('/\.svc-sit \.card__title \{[^}]*font-size: var\(--fs-body\);\s*font-weight: var\(--fw-regular\);/', $css);
        $this->assertMatchesRegularExpression('/\.pa-card__fit-intro \{[^}]*font-size: var\(--fs-ui\);\s*font-weight: var\(--fw-medium\);\s*line-height: var\(--lh-ui\);/', $css);
        $this->assertMatchesRegularExpression('/\.js \.pa-card__fit-toggle \{[^}]*font-size: var\(--fs-ui\);\s*font-weight: var\(--fw-medium\);/', $css);
        // P02's narrow card summaries: Russian breaks between words only (no run of hyphenated lines).
        $this->assertMatchesRegularExpression('/\.pa-card__summary \{[^}]*hyphens: manual;/', $css);
        $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get(SiteUrl::service('S06', 'en'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<div class="svc-next__card svc-next__card--do">\s*<h3 class="svc-next__title">#', $html);
    }
}
