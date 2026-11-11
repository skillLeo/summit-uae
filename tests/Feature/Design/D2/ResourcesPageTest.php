<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Prefill;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 Legal Knowledge Centre (P03, d2::inner.resources; upgrade spec §7.4 "Guide page", §12 Phase D):
 * the approved page copy, the nine complete guides as anchored sections (the registered anchors of
 * config('summit.guides')), every in-page link resolving, the search controls, the contents, the related
 * service and consultation links, in all three languages.
 */
final class ResourcesPageTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    /** @var array<string, string> rendered page per locale */
    private array $pages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function html(string $locale): string
    {
        return $this->pages[$locale] ??= $this->withUnencryptedCookies(['summit_design' => '2'])
            ->get("/{$locale}/resources/")->assertOk()->getContent();
    }

    /** The visible text of a fragment: tags removed, entities decoded, whitespace collapsed. */
    private function text(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#si', ' ', $html);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    private function main(string $locale): string
    {
        $html = $this->html($locale);
        $start = strpos($html, '<main');
        $end = strpos($html, '</main>');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    private function squash(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** The HTML of a guide's <article> (up to the next guide or the end of the reading layout). */
    private function article(string $locale, string $code): string
    {
        $html = $this->html($locale);
        $at = strpos($html, 'data-kc-guide="'.$code.'"');
        $this->assertNotFalse($at, "guide {$code} is rendered ({$locale})");
        $open = strrpos(substr($html, 0, $at), '<article');
        $next = strpos($html, 'data-kc-guide="', $at + 20);
        $end = $next !== false ? strrpos(substr($html, 0, $next), '<article') : strpos($html, '</main>', $at);

        return substr($html, $open, $end - $open);
    }

    /** @return list<string> every approved string of a guide body (titles, blocks, lists, Q&A, links) */
    private function strings(mixed $value): array
    {
        $out = [];
        if (is_string($value)) {
            return trim($value) !== '' ? [$value] : [];
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (in_array($key, ['type', 'target', 'url', 'ref', 'id'], true)) {
                    continue;
                }
                array_push($out, ...$this->strings($item));
            }
        }

        return $out;
    }

    public function test_page_renders_the_approved_head_once_per_language(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->html($locale);
            $page = content()->page('P03', $locale);
            $this->assertStringContainsString('data-design="2"', $html, $locale);
            $this->assertSame(1, substr_count($html, '<h1'), "one H1 ({$locale})");
            $this->assertMatchesRegularExpression('/<h1[^>]* id="knowledge-top"/', $html, "the H1 is the #knowledge-top target ({$locale})");
            $main = $this->text($this->main($locale));
            foreach ([$page['h1'], $page['content']['lead'], $page['content']['intro'], $page['content']['how_to']['heading'], ...$page['content']['how_to']['items'], $page['content']['find']['heading'], $page['content']['directory']['heading'], $page['content']['faq_services']['heading'], $page['content']['faq_services']['text'], $page['content']['consultation']['heading'], $page['content']['consultation']['text'], $page['content']['consultation']['notice'], $page['content']['scope']['heading'], ...$page['content']['scope']['paragraphs']] as $string) {
                $this->assertStringContainsString($this->squash($string), $main, "approved P03 copy is shown ({$locale}): ".mb_substr($string, 0, 50));
            }
            $this->assertStringNotContainsString(' style="', $this->main($locale), "no inline style attributes (CSP) ({$locale})");
        }
    }

    public function test_every_guide_is_a_complete_anchored_section_in_the_approved_order(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->html($locale);
            $positions = [];
            foreach (config('summit.guides') as $code => $anchor) {
                $guide = content()->guide($code, $locale);
                $this->assertSame($anchor, $guide['anchor']);
                $this->assertSame(1, preg_match_all('/<h2[^>]* id="'.preg_quote($anchor, '/').'"/', $html), "#{$anchor} is the H2 of {$code} ({$locale})");
                $article = $this->text($this->article($locale, $code));
                foreach ($this->strings($guide['body']) as $string) {
                    $this->assertStringContainsString($this->squash($string), $article, "{$code} ({$locale}) shows: ".mb_substr($string, 0, 60));
                }
                $positions[] = strpos($html, 'id="'.$anchor.'"');
            }
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, "G01 … G09 in the approved order ({$locale})");
        }
    }

    public function test_guide_parts_follow_the_heading_order(): void
    {
        foreach (self::LOCALES as $locale) {
            preg_match_all('/<h([1-6])\b/', $this->main($locale), $m);
            $previous = 1;
            foreach (array_map('intval', $m[1]) as $i => $level) {
                if ($i === 0) {
                    $this->assertSame(1, $level, "the main content starts with the H1 ({$locale})");

                    continue;
                }
                $this->assertLessThanOrEqual($previous + 1, $level, "no heading level is skipped ({$locale}, heading {$i})");
                $previous = $level;
            }
            $article = $this->article($locale, 'G01');
            $this->assertMatchesRegularExpression('/<h4[^>]*>/', $article, "the guide's FAQ questions are H4 ({$locale})");
        }
    }

    public function test_every_in_page_link_has_a_target_and_the_cards_and_contents_link_to_the_guides(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->html($locale);
            preg_match_all('/\sid="([^"]+)"/', $html, $ids);
            $ids = array_flip($ids[1]);
            preg_match_all('/\shref="#([^"]+)"/', $html, $links);
            $this->assertNotEmpty($links[1]);
            foreach (array_unique($links[1]) as $fragment) {
                $this->assertArrayHasKey($fragment, $ids, "#{$fragment} exists on the page ({$locale})");
            }
            foreach (config('summit.guides') as $code => $anchor) {
                $this->assertMatchesRegularExpression('/data-kc-card="'.$code.'"[\s\S]*?href="#'.preg_quote($anchor, '/').'"/', $html, "the {$code} card links to #{$anchor} ({$locale})");
                $this->assertMatchesRegularExpression('/class="toc__link" href="#'.preg_quote($anchor, '/').'"/', $html, "the contents list #{$anchor} ({$locale})");
            }
            $this->assertStringContainsString('href="#knowledge-top"', $html, "back to the guide directory ({$locale})");
        }
    }

    public function test_search_and_topic_controls_use_the_approved_labels_and_render_every_count(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->html($locale);
            foreach (['resources.search.label', 'resources.search.placeholder', 'resources.topic.label', 'resources.topic.all', 'resources.clear'] as $key) {
                $this->assertStringContainsString(e(ui($key, $locale)), $html, "{$key} ({$locale})");
            }
            foreach (content()->guideCategories($locale) as $category) {
                $this->assertStringContainsString('data-kc-topic-chip="'.$category['code'].'"', $html, "topic chip {$category['code']} ({$locale})");
            }
            $this->assertMatchesRegularExpression('#<script type="application/json" data-kc-strings>(.+?)</script>#s', $html);
            preg_match('#<script type="application/json" data-kc-strings>(.+?)</script>#s', $html, $m);
            $strings = json_decode($m[1], true);
            $this->assertSame(ui('resources.no_results', $locale), $strings['none']);
            $this->assertCount(count(config('summit.guides')) + 1, $strings['results'], "a message for 0 … 9 results ({$locale})");
            foreach ($strings['results'] as $n => $message) {
                $this->assertSame(trans_choice('proposed.d2.resources_page.results', (int) $n, ['count' => '__N__'], $locale), $message);
                // The number is a placeholder the script isolates in a <bdi>; Arabic singular and dual forms
                // carry no numeral at all.
                $this->assertDoesNotMatchRegularExpression('/\d/', $message, "no raw number in the message ({$locale}, {$n})");
                if ($locale !== 'ar' && (int) $n > 0) {
                    $this->assertStringContainsString('__N__', $message, "the count is a placeholder ({$locale}, {$n})");
                }
            }
        }
    }

    public function test_related_service_consultation_and_sources_links(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            foreach (config('summit.guides') as $code => $anchor) {
                $guide = content()->guide($code, $locale);
                $article = $this->article($locale, $code);
                $service = null;
                foreach ($guide['body']['related']['items'] as $item) {
                    if (preg_match('/^S\d{2}$/', $item['target'])) {
                        $service = $item['target'];
                        $this->assertStringContainsString('href="'.SiteUrl::service($service, $locale).'"', $article, "{$code} links to its service ({$locale})");
                        $this->assertStringContainsString('aria-label="'.e($item['label']).'"', $article, "the service card is named by the approved label ({$locale})");
                    }
                }
                $this->assertNotNull($service);
                $this->assertStringContainsString('href="'.e(Prefill::url(['service' => $service], $locale)).'"', $article, "{$code}: the consultation CTA pre-fills {$service} ({$locale})");
                $this->assertStringContainsString('#consultation-form', Prefill::url(['service' => $service], $locale));
                foreach ($guide['body']['sources']['items'] as $source) {
                    $this->assertMatchesRegularExpression('#<a [^>]*href="'.preg_quote(e($source['url']), '#').'"[^>]*target="_blank" rel="noopener noreferrer"#', $article, "{$code}: official source opens in a new tab ({$locale})");
                }
            }
        }
    }

    public function test_contact_section_follows_the_contact_rules(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->html($locale);
            $this->assertStringContainsString('href="'.e(SiteUrl::consultation($locale)).'"', $html, "consultation goes to the home form until P07 is released ({$locale})");
            $this->assertStringContainsString('href="'.e(setting('whatsapp_url', $locale)).'"', $html, "approved WhatsApp draft ({$locale})");
            // the kit's contact channels (Design 2.1: one channel pattern with every CTA panel and the footer)
            $this->assertMatchesRegularExpression('#<a class="channel channel--phone" href="tel:\+971524666191">[\s\S]*?<bdi class="channel__text" dir="ltr">\+971 52 466 6191</bdi>#', $html, "telephone isolated left to right ({$locale})");
            $this->assertMatchesRegularExpression('#<a class="channel channel--email" href="mailto:info@summitlegaluae.com">[\s\S]*?<bdi class="channel__text" dir="ltr">info@summitlegaluae.com</bdi>#', $html, "e-mail isolated left to right ({$locale})");
            // the request and WhatsApp buttons share one height on phones (resources.css .kc-contact__buttons)
            $this->assertMatchesRegularExpression('#<div class="kc-contact__buttons">\s*<a class="btn [^"]*btn--ink#', $html);
            $main = mb_strtolower($this->text($this->main($locale)));
            foreach (['law firm', 'free consultation', '24/7', 'years of experience', 'success rate'] as $banned) {
                $this->assertStringNotContainsString($banned, $main, "no '{$banned}' ({$locale})");
            }
        }
    }

    public function test_proposed_copy_exists_in_every_language_and_is_marked(): void
    {
        $keys = array_keys(require lang_path('en/proposed-d2/resources_page.php'));
        $this->assertNotEmpty($keys);
        foreach (['ar', 'ru'] as $locale) {
            $translated = require lang_path("{$locale}/proposed-d2/resources_page.php");
            $this->assertSame($keys, array_keys($translated), "same keys in {$locale}");
            foreach ($translated as $key => $value) {
                $this->assertNotSame((require lang_path('en/proposed-d2/resources_page.php'))[$key], $value, "{$key} is translated in {$locale}");
            }
        }
        // The plurals (trans_choice): Arabic zero, one, two, few, many, other; Russian one, few, many. The
        // disclosure labels are plain strings.
        foreach (['category_count', 'results'] as $key) {
            $this->assertCount(2, explode('|', (require lang_path('en/proposed-d2/resources_page.php'))[$key]), "{$key}: two English forms");
            $this->assertCount(6, explode('|', (require lang_path('ar/proposed-d2/resources_page.php'))[$key]), "{$key}: six Arabic forms");
            $this->assertCount(3, explode('|', (require lang_path('ru/proposed-d2/resources_page.php'))[$key]), "{$key}: three Russian forms");
        }
        foreach (['guide_open', 'guide_close'] as $key) {
            foreach (self::LOCALES as $locale) {
                $this->assertStringNotContainsString('|', (require lang_path("{$locale}/proposed-d2/resources_page.php"))[$key], "{$key} is one string ({$locale})");
            }
        }
        $html = $this->html('en');
        $this->assertMatchesRegularExpression('/class="kc-dir__count" data-copy-status="proposed"/', $html, 'the category count is marked proposed');
        // Design 2.1 (brief §3.6 rules 5, 9 and 10): the guides carry no generated cover banner, no repeated
        // "N min read" line with its clock and no object glyphs or arrows on their related links.
        $this->assertStringNotContainsString('kc-guide__meta', $html, 'no reading time on the guides');
        $this->assertStringNotContainsString('kc-guide__cover', $html, 'no cover banner on the guides');
        $guides = substr($html, (int) strpos($html, 'id="kc-guides"'));
        $this->assertStringNotContainsString('data-icon="clock"', $guides);
        $this->assertStringNotContainsString('data-icon="arrow"', $guides);
        $this->assertStringNotContainsString('kc-link__icon', $guides);
        $this->assertSame(9, substr_count($html, '<span class="kc-guide__toggle-open" data-copy-status="proposed">'), 'the disclosure labels are marked proposed');
        $this->assertSame(9, substr_count($html, '<span class="kc-guide__toggle-close" data-copy-status="proposed">'));
    }

    public function test_every_guide_has_its_compact_phone_card_and_a_disclosure(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->html($locale);
            foreach (config('summit.guides') as $code => $anchor) {
                $guide = content()->guide($code, $locale);
                $article = $this->article($locale, $code);
                $card = strpos($article, '<div class="kc-guide__card">');
                $title = strpos($article, 'id="'.$anchor.'"');
                $body = strpos($article, '<div class="kc-guide__body" id="'.$anchor.'-body" data-kc-body>');
                $this->assertNotFalse($card, "{$code} card part ({$locale})");
                $this->assertNotFalse($body, "{$code} body part ({$locale})");
                $this->assertTrue($card < $title && $title < $body, "{$code}: the H2 is in the card, before the body ({$locale})");

                // The approved card summary shows once on the page: on the directory card, never again in the guide's
                // phone card (QA round 1: the phone page printed every summary twice).
                $this->assertStringNotContainsString('kc-guide__summary', $article, "{$code}: no second summary ({$locale})");
                $summary = $this->squash((string) ($guide['card_summary'] ?: $guide['home_summary']));
                $this->assertSame(1, substr_count($this->text($html), $summary), "{$code}: the approved summary once, on its directory card ({$locale})");

                // The disclosure: a button (folded state in the HTML: the CSS opens nothing without JavaScript),
                // controlling the body and described by the guide's title; both labels proposed.
                $this->assertSame(1, preg_match('#<button class="[^"]*\bkc-guide__toggle\b[^"]*"[^>]*>#', $article, $toggle), "{$code} disclosure ({$locale})");
                foreach (['type="button"', 'aria-expanded="false"', 'aria-controls="'.$anchor.'-body"', 'aria-describedby="'.$anchor.'"', 'data-kc-toggle'] as $attribute) {
                    $this->assertStringContainsString($attribute, $toggle[0], "{$code} disclosure {$attribute} ({$locale})");
                }
                $this->assertStringContainsString('>'.e(d2p('resources_page.guide_open', [], $locale)).'</span>', $article);
                $this->assertStringContainsString('>'.e(d2p('resources_page.guide_close', [], $locale)).'</span>', $article);

                // Every section of the guide is inside its body.
                foreach (['-audience', '-steps', '-related'] as $part) {
                    $at = strpos($article, 'id="'.$anchor.$part.'"');
                    if ($at !== false) {
                        $this->assertGreaterThan($body, $at, "{$anchor}{$part} is in the body ({$locale})");
                    }
                }
            }

            // The arrival script (an address naming a guide or an anchor inside one opens that guide before the
            // first paint): after the nine guides, nonce'd for the CSP.
            $last = strrpos($html, 'data-kc-guide="');
            $script = strpos($html, "closest('[data-kc-guide]')", $last);
            $this->assertNotFalse($script, "the arrival script follows the guides ({$locale})");
            $this->assertMatchesRegularExpression('#<script nonce="[^"]+">\s*\(function \(\) \{#', substr($html, $script - 400, 400), "the arrival script carries the nonce ({$locale})");
        }
    }

    public function test_the_search_line_shows_in_full_and_the_topic_chips_never_wrap(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->html($locale);
            // D09, the FAQ search's solution: the approved placeholder stays the input's placeholder (the accessible
            // one) and a wrapping copy (aria-hidden) shows it in full inside the box while the field is empty, so a
            // phone shows the whole line on two lines; it is never cut and never moved out of the field.
            $line = e(ui('resources.search.placeholder', $locale));
            $this->assertMatchesRegularExpression('#<div class="kc-find__box" data-kc-box>\s*<span class="control[^"]*"\s*>[\s\S]*?\bid="kc-q"[\s\S]*?<span class="kc-find__hint" aria-hidden="true">'.preg_quote($line, '#').'</span>\s*</div>#', $html, $locale);
            $this->assertSame(1, preg_match('#<input [^>]*\bid="kc-q"[^>]*>#', $html, $input), $locale);
            $this->assertStringContainsString('placeholder="'.$line.'"', $input[0], $locale);
            $this->assertStringNotContainsString('kc-q-hint', $html, 'no help text under the label: the line stays in the field');
        }
        $css = file_get_contents(resource_path('css/d2/pages/resources.css'));
        $this->assertMatchesRegularExpression('/\.kc-find__box:has\(\.input:placeholder-shown\) \.kc-find__hint \{\s*display: block;/', $css);
        $this->assertMatchesRegularExpression('/\.kc-find__hint \{[^}]*text-wrap: balance;/', $css);
        $this->assertMatchesRegularExpression('/\.kc-find__form \{\s*grid-template-columns: minmax\(0, 7fr\) minmax\(0, 5fr\);/', $css, 'one line from 1280 px in Russian');
        $js = file_get_contents(resource_path('js/d2/components/resources.js'));
        $this->assertStringContainsString("finder.querySelector('[data-kc-box]')", $js, 'a press in the box focuses the field');
        // Chips: one line each (brief §3.5, 32 px pills), wrapping as a row from 768 px.
        $this->assertMatchesRegularExpression('/\.kc-find__chips \{[^}]*& \.chip \{\s*flex: none;\s*white-space: nowrap;/s', $css);
        $this->assertStringNotContainsString('white-space: normal', $css);
    }

    public function test_phones_choose_the_topic_in_a_picker_that_shows_every_name_whole(): void
    {
        // D30 / D41 rule (no half-cut label): no phone row holds every approved category name on one line (RU
        // «Цифровые доказательства и организация бизнеса» is wider than a 390 px phone's panel), so a sideways chip
        // row always cut one under its fade. Below 768 px the topic is a native picker labelled "Topic"; its box
        // shows the chosen approved name whole (an aria-hidden copy that wraps), the transparent <select> over it.
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->html($locale);
            $this->assertMatchesRegularExpression('#<div class="kc-find__chips" role="group" aria-labelledby="kc-topic-label">#', $html, $locale);
            $this->assertSame(1, preg_match('#<select class="kc-find__select" id="kc-topic" aria-labelledby="kc-topic-label" data-kc-topic-select>(.*?)</select>#s', $html, $select), $locale);
            preg_match_all('#<option value="([^"]*)"[^>]*>([^<]*)</option>#', $select[1], $options, PREG_SET_ORDER);
            $categories = content()->guideCategories($locale);
            $this->assertCount(count($categories) + 1, $options, "all topics + one option per category ({$locale})");
            $this->assertSame(['', e(ui('resources.topic.all', $locale))], [$options[0][1], trim($options[0][2])], $locale);
            foreach (array_values($categories) as $i => $category) {
                $this->assertSame((string) $category['code'], $options[$i + 1][1], $locale);
                $this->assertSame(e((string) $category['name']), trim($options[$i + 1][2]), "the approved name, whole ({$locale})");
            }
            $this->assertStringContainsString('<span class="kc-find__pick-value" aria-hidden="true" data-kc-topic-value>'.e(ui('resources.topic.all', $locale)).'</span>', $html, $locale);
        }
        $css = file_get_contents(resource_path('css/d2/pages/resources.css'));
        $this->assertMatchesRegularExpression('/@media \(width < 768px\) \{\s*\.kc-find__chips \{\s*display: none;\s*\}\s*\.kc-find__pick \{[^}]*display: grid;/s', $css, 'one control per width');
        $this->assertMatchesRegularExpression('/\.kc-find__pick-value \{[^}]*line-height: calc\(var\(--field-h\) - 2 \* var\(--line-w\) - 2 \* var\(--pick-pad\)\);[^}]*text-wrap: balance;/s', $css, 'one line fills 48 px, a long name wraps');
        $this->assertMatchesRegularExpression('/\.kc-find__select \{[^}]*position: absolute;\s*inset: 0;[^}]*opacity: 0;/s', $css, 'the native control covers the box');
        $this->assertStringNotContainsString('mask-fade', $css, 'no sideways row with a fade any more');
        $js = file_get_contents(resource_path('js/d2/components/resources.js'));
        $this->assertStringContainsString("ui.select?.addEventListener('change', onPick)", $js);
        $this->assertStringContainsString('topicLabel(ui.select.options, ui.topic)', $js, 'the box follows the chosen topic');
    }

    public function test_mistakes_to_avoid_take_the_service_pages_treatment(): void
    {
        // Finding (D07, §3.6 rule 5): one treatment for the same list: the cool band panel with a cross per item, as
        // on S01–S11; never the warm warning callout with plain bullets.
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            foreach (config('summit.guides') as $code => $anchor) {
                $article = $this->article($locale, $code);
                $items = content()->guide($code, $locale)['body']['mistakes']['items'] ?? [];
                $this->assertMatchesRegularExpression('#<section class="prose__section prose__section--panel" id="'.$anchor.'-mistakes">\s*<h3 class="prose__heading">#', $article, "{$code} ({$locale})");
                preg_match('#id="'.$anchor.'-mistakes">(.*?)</section>#s', $article, $panel);
                $this->assertSame(count($items), substr_count($panel[1] ?? '', 'data-icon="close"'), "{$code}: a cross per mistake ({$locale})");
                $this->assertStringNotContainsString('callout--warning', $article, "{$code} ({$locale})");
            }
        }
    }

    public function test_guide_titles_land_with_the_whole_card_clear_of_the_bars_and_the_closing_blocks_share_one_width(): void
    {
        $css = file_get_contents(resource_path('css/d2/pages/resources.css'));
        // the title's scroll margin includes the contents bar and the guide's top above the title (--kc-lead)
        $this->assertMatchesRegularExpression('/\.kc-guide \.kc-guide__title \{\s*scroll-margin-block-start: calc\(var\(--toc-bar-h\) \+ var\(--s-4\) \+ var\(--kc-lead\)\);/', $css);
        $this->assertMatchesRegularExpression('/\.js \.kc-guide \{\s*--kc-lead: calc\(var\(--line-w\) \+ var\(--pad-card\) \+ var\(--lh-ui\) \+ var\(--s-2\)\);/', $css);
        $js = file_get_contents(resource_path('js/d2/components/resources.js'));
        $this->assertStringContainsString("article.style.setProperty('--kc-lead'", $js, 'measured per guide through CSSOM');
        // "Scope of Information and Services" is the kit's one scope note (P02, P03, P04, S01–S11), container-wide
        // under the consultation panel: from 1024 px the heading on the page grid's first columns and the text from
        // the next column line, as P02's approved sections (pages/inner.css .inner-scope--wide).
        $this->assertStringContainsString('class="prose prose--md kc-scope__prose inner-scope inner-scope--wide"', $this->html('en'));
        $this->assertStringNotContainsString('.kc-scope__prose', $css, 'no page-specific scope layout');
    }
}
