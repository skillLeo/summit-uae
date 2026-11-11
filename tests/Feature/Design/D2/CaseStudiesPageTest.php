<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Prefill;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 Case Studies page (P04, d2::inner.case-studies; upgrade spec §7.4 "Case study page", §12 Phase D):
 * the approved page copy, the six directory text cards (Design 2.1 D46: no numeral, chip, icon or corner cue),
 * the six complete stories as anchored sections case-01 … case-06 (no numeral tab, no repeated stage timeline and,
 * Phase 2 brief §5.3, no qualification callout repeating part of the introduction in every file: the head states
 * the complete paragraph once), the related service and consultation links, every in-page link resolving, in all
 * three languages.
 */
final class CaseStudiesPageTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    private const CASES = ['case-01', 'case-02', 'case-03', 'case-04', 'case-05', 'case-06'];

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
            ->get("/{$locale}/case-studies/")->assertOk()->getContent();
    }

    private function text(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#si', ' ', $html);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    private function squash(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function main(string $locale): string
    {
        $html = $this->html($locale);
        $start = strpos($html, '<main');

        return substr($html, $start, strpos($html, '</main>') - $start);
    }

    /** The HTML of a case file (up to the next file or the end of the list). */
    private function file(string $locale, string $code): string
    {
        $html = $this->html($locale);
        $at = strpos($html, 'data-case-file="'.$code.'"');
        $this->assertNotFalse($at, "{$code} file is rendered ({$locale})");
        $open = strrpos(substr($html, 0, $at), '<article');
        $next = strpos($html, 'data-case-file="', $at + 20);
        $end = $next !== false ? strrpos(substr($html, 0, $next), '<article') : strpos($html, 'class="section cs-faq"', $at);

        return substr($html, $open, $end - $open);
    }

    /** The approved qualification wording: the last P04 introduction paragraph without its first sentence. */
    private function qualification(string $locale): string
    {
        $paragraphs = content()->page('P04', $locale)['content']['intro']['paragraphs'];
        $last = end($paragraphs);
        preg_match('/^.+?[.!?؟](?=\s)/u', $last, $first);

        return trim(mb_substr($last, mb_strlen($first[0])));
    }

    public function test_page_renders_the_approved_head_directory_faq_and_contact_copy(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->html($locale);
            $page = content()->page('P04', $locale);
            $c = $page['content'];
            $this->assertStringContainsString('data-design="2"', $html, $locale);
            $this->assertSame(1, substr_count($html, '<h1'), "one H1 ({$locale})");
            $this->assertMatchesRegularExpression('/<h1[^>]* id="cases-top"/', $html, "the H1 is the #cases-top target ({$locale})");
            $main = $this->text($this->main($locale));
            $strings = [$page['h1'], $c['intro']['lead'], ...$c['intro']['paragraphs'], $c['directory']['heading'], $c['faq']['heading'], $c['faq']['link']['label'], $c['cta']['heading'], $c['cta']['body'], $c['cta']['consultation']['label'], $c['cta']['whatsapp']['label'], $c['cta']['notice'], $c['scope']['heading'], $c['scope']['body'], $c['scope']['link']['label']];
            foreach ($c['faq']['items'] as $item) {
                $strings[] = $item['question'];
                $strings[] = $item['answer'];
            }
            foreach ($strings as $string) {
                $this->assertStringContainsString($this->squash($string), $main, "approved P04 copy is shown ({$locale}): ".mb_substr($string, 0, 50));
            }
            foreach (array_keys($c['faq']['items']) as $i) {
                $this->assertMatchesRegularExpression('/<details\b[^>]*\sid="case-faq-'.($i + 1).'"[\s\S]*?<h3 /', $html, "FAQ question ".($i + 1)." is an H3 ({$locale})");
            }
            $this->assertStringNotContainsString(' style="', $this->main($locale), "no inline style attributes (CSP) ({$locale})");
            // Finding (one head pattern, §3.5): the approved subtitle is the ink lead under the H1, as on P05, P06, P07.
            $this->assertMatchesRegularExpression('#<header class="inner-head inner-head--display inner-head--aside pp-head cs-head">[\s\S]*?<div class="inner-head__meta"><p class="pp-lead">'.preg_quote(e($c['intro']['lead']), '#').'</p></div>#', $html, $locale);
            $this->assertStringNotContainsString('<p class="inner-head__intro">'.e($c['intro']['lead']).'</p>', $html, $locale);
        }
    }

    public function test_six_complete_stories_are_anchored_sections_in_the_approved_order(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->html($locale);
            $positions = [];
            foreach (self::CASES as $code) {
                $case = content()->caseStudy($code, $locale);
                $this->assertSame(1, preg_match_all('/<h2[^>]* id="'.$code.'"/', $html), "#{$code} is the H2 of its story ({$locale})");
                $file = $this->text($this->file($locale, $code));
                $b = $case['body'];
                $strings = [$case['title'], $case['matter'], ...$b['narrative']];
                foreach ([...$b['sections'], $b['outcome'], $b['takeaway']] as $section) {
                    $strings[] = $section['heading'];
                    array_push($strings, ...$section['paragraphs']);
                }
                $strings[] = $b['related']['heading'];
                foreach ($b['related']['links'] as $link) {
                    $strings[] = $link['label'];
                }
                $strings[] = $b['back_link']['label'];
                foreach ($strings as $string) {
                    $this->assertStringContainsString($this->squash($string), $file, "{$code} ({$locale}) shows: ".mb_substr($string, 0, 60));
                }
                $positions[] = strpos($html, 'id="'.$code.'"');
            }
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, "case-01 … case-06 in the approved order ({$locale})");
        }
    }

    public function test_no_file_repeats_the_qualification_or_carries_a_numeral_or_a_stage_timeline(): void
    {
        foreach (self::LOCALES as $locale) {
            $qualification = $this->qualification($locale);
            $this->assertNotSame('', $qualification);
            // Phase 2 (brief §5.3): the qualification sentences are part of the last introduction paragraph, which the
            // head's note states once, complete; the six files no longer repeat a shortened copy of it in a callout.
            $paragraphs = content()->page('P04', $locale)['content']['intro']['paragraphs'];
            $main = $this->text($this->main($locale));
            $this->assertSame(1, substr_count($main, $this->squash(end($paragraphs))), "the complete paragraph, once ({$locale})");
            $this->assertSame(1, substr_count($main, $this->squash($qualification)), "the qualification wording, once ({$locale})");
            $this->assertMatchesRegularExpression('#<div class="cs-note">\s*<p class="cs-note__text">#', $this->html($locale), "the head's note ({$locale})");
            $stages = array_map(fn ($s) => $s['title'], content()->page('P01', $locale)['content']['review']['steps']);
            $this->assertCount(4, $stages);
            foreach (self::CASES as $n => $code) {
                $html = $this->file($locale, $code);
                $text = $this->text($html);
                $this->assertStringNotContainsString('casefile__qualification', $html, "{$code}: no qualification callout ({$locale})");
                $this->assertStringNotContainsString('callout--info', substr($html, 0, (int) strpos($html, 'casefile__body')), "{$code}: no callout in the file's head ({$locale})");
                $this->assertStringNotContainsString($this->squash($qualification), $text, "{$code}: the qualification is not repeated ({$locale})");
                // D46 (brief §3.6 rules 4 and 10): no folder tab with a case numeral, no chip, no stage timeline.
                foreach (['casefile__tab', 'casefile__no', 'timeline', 'chip'] as $gone) {
                    $this->assertStringNotContainsString($gone, $html, "{$code}: no {$gone} ({$locale})");
                }
                $this->assertStringNotContainsString('>0'.($n + 1).'<', $html, "{$code}: no case numeral ({$locale})");
                $this->assertStringNotContainsString($this->squash(content()->page('P01', $locale)['content']['review']['method_heading']), $text, "{$code}: the How We Work heading is not repeated ({$locale})");
                // The service line: the shorter approved name of the story's service, above the H2 (title first in the source).
                $this->assertMatchesRegularExpression('#<h2 class="casefile__title" id="'.$code.'" tabindex="-1">[\s\S]*?</h2>\s*<p class="casefile__service">#', $html, "{$code}: the service line ({$locale})");
            }
        }
    }

    /** D46: six text cards (category, title, summary): no numeral, chip, icon, matter line or corner chevron. */
    public function test_directory_cards_are_text_cards(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = $this->html($locale);
            preg_match('#<section class="section cs-dir"[\s\S]*?</section>#', $html, $dir);
            $this->assertNotEmpty($dir, $locale);
            preg_match_all('#<article class="[^"]*\bcase-card\b[^"]*"[\s\S]*?</article>#', $dir[0], $cards);
            $this->assertCount(6, $cards[0], "six directory cards ({$locale})");
            foreach (self::CASES as $i => $code) {
                $card = $cards[0][$i];
                $case = content()->caseStudy($code, $locale);
                $text = $this->text($card);
                $this->assertMatchesRegularExpression('/<h3 class="card__title">\s*<a class="card__link" href="#'.$code.'">/', $card, "{$code}: the title is the card's one link ({$locale})");
                $this->assertSame(1, substr_count($card, '<a '), "{$code}: one link ({$locale})");
                $this->assertStringContainsString($this->squash($case['card_title']), $text);
                $this->assertStringContainsString($this->squash($case['card_summary']), $text);
                $this->assertMatchesRegularExpression('#<p class="case-card__category">[^<]+</p>#u', $card, "{$code}: the category line ({$locale})");
                foreach (['case-card__no', 'chip', 'data-icon', 'timeline', 'badge', 'case-card__cue'] as $gone) {
                    $this->assertStringNotContainsString($gone, $card, "{$code}: no {$gone} ({$locale})");
                }
                $this->assertStringNotContainsString($this->squash($case['matter']), $text, "{$code}: the matter line stays in the story ({$locale})");
                $this->assertDoesNotMatchRegularExpression('/>\s*0\d\s*</', $card, "{$code}: no decorative number ({$locale})");
            }
        }
    }

    public function test_directory_cards_link_to_the_stories_and_every_in_page_link_resolves(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->html($locale);
            foreach (self::CASES as $code) {
                $case = content()->caseStudy($code, $locale);
                $this->assertMatchesRegularExpression('/id="card-'.$code.'"[\s\S]*?<a class="card__link" href="#'.$code.'"/', $html, "the {$code} card links to its story ({$locale})");
                $this->assertStringContainsString(e($case['card_summary']), $html, "{$code}: approved card summary ({$locale})");
            }
            preg_match_all('/\sid="([^"]+)"/', $html, $ids);
            $ids = array_flip($ids[1]);
            preg_match_all('/\shref="#([^"]+)"/', $html, $links);
            foreach (array_unique($links[1]) as $fragment) {
                $this->assertArrayHasKey($fragment, $ids, "#{$fragment} exists on the page ({$locale})");
            }
            $this->assertSame(count(self::CASES), substr_count($html, 'href="#cases-top"'), "every story links back to the directory ({$locale})");
        }
    }

    public function test_related_service_guide_and_consultation_links(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            foreach (self::CASES as $code) {
                $case = content()->caseStudy($code, $locale);
                $html = $this->file($locale, $code);
                $service = null;
                foreach ($case['body']['related']['links'] as $link) {
                    $target = $link['target'];
                    if (preg_match('/^S\d{2}$/', $target)) {
                        $service ??= $target;
                        $this->assertStringContainsString('href="'.SiteUrl::service($target, $locale).'"', $html, "{$code}: related service ({$locale})");
                        $this->assertStringContainsString('aria-label="'.e($link['label']).'"', $html);
                    } elseif (preg_match('/^G\d{2}$/', $target)) {
                        $this->assertStringContainsString('href="'.SiteUrl::guide($target, $locale).'"', $html, "{$code}: related guide on P03 ({$locale})");
                    }
                }
                $this->assertNotNull($service, "{$code} has a related service");
                $this->assertSame(1, substr_count($html, 'href="'.e(Prefill::url(['service' => $service], $locale)).'"'), "{$code}: one consultation CTA with {$service} ({$locale})");
            }
            $page = $this->html($locale);
            $this->assertStringContainsString('href="'.e(SiteUrl::consultation($locale)).'"', $page, "final consultation follows the release rule ({$locale})");
            $this->assertStringContainsString('href="'.e(setting('whatsapp_url', $locale)).'"', $page, "approved WhatsApp draft ({$locale})");
            // telephone and e-mail: the kit's contact channels (one pattern with every CTA panel and the footer)
            $this->assertMatchesRegularExpression('#<ul class="channels kc-contact__channels" role="list">[\s\S]*?<a class="channel channel--phone" href="tel:\+971524666191">[\s\S]*?<bdi class="channel__text" dir="ltr">\+971 52 466 6191</bdi>#', $page);
            $main = mb_strtolower($this->text($this->main($locale)));
            foreach (['law firm', 'free consultation', '24/7', 'years of experience', 'success rate', 'success stor'] as $banned) {
                $this->assertStringNotContainsString($banned, $main, "no '{$banned}' ({$locale})");
            }
        }
    }

    /**
     * Design 2.1 round 1 (home-b, brief 3.5): every story's aside is built alike: the related heading, the approved
     * related links in their DOCX order (story 02 opens with its approved "Consultation on a sale contract or
     * claim" to P07, a related link like the others), then the one Ink CTA (prefilled with the story's service) in
     * the actions slot, then the way back. No CTA inside the related list.
     */
    public function test_every_story_keeps_its_consultation_cta_in_the_same_slot(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            foreach (self::CASES as $code) {
                $file = $this->file($locale, $code);
                $case = content()->caseStudy($code, $locale);
                $this->assertSame(1, substr_count($file, 'casefile__cta'), "{$code}: one Ink CTA ({$locale})");
                $this->assertMatchesRegularExpression('#<div class="casefile__actions">\s*<a class="btn btn--ink btn--48 btn--full casefile__cta" href="[^"]*\?service=S\d\d\#consultation-form">\s*<span class="btn__spinner" aria-hidden="true"></span>\s*<span class="btn__label">'.preg_quote(e(ui('cta.request_consultation', $locale)), '#').'</span>\s*</a>\s*<a [^>]*href="\#cases-top"#', $file, "{$code}: the CTA opens the actions slot, before the way back ({$locale})");
                preg_match('#<ul class="casefile__related" role="list">(.*?)</ul>#s', $file, $list);
                $this->assertStringNotContainsString('btn--ink', $list[1] ?? '', "{$code}: no CTA among the related links ({$locale})");
                // The related items keep the DOCX order of their labels.
                $labels = array_map(fn ($l) => e($l['label']), $case['body']['related']['links']);
                $positions = array_map(fn ($label) => strpos($list[1] ?? '', $label), $labels);
                $this->assertNotContains(false, $positions, "{$code}: every related link is listed ({$locale})");
                $this->assertSame($positions, array_values(array_unique($positions)));
                $sorted = $positions;
                sort($sorted);
                $this->assertSame($sorted, $positions, "{$code}: related links in the approved order ({$locale})");
            }
            // Story 02's approved first related link goes to P07 (the release rule), as a related link.
            $this->assertMatchesRegularExpression('#<ul class="casefile__related" role="list">\s*<li class="casefile__related-item">\s*<a class="kc-link" href="'.preg_quote(e(SiteUrl::target('P07', $locale)), '#').'">#', $this->file($locale, 'case-02'));
        }
    }

    public function test_proposed_copy_files_exist_with_the_same_keys(): void
    {
        $keys = array_keys(require lang_path('en/proposed-d2/cases_page.php'));
        foreach (['ar', 'ru'] as $locale) {
            $this->assertSame($keys, array_keys(require lang_path("{$locale}/proposed-d2/cases_page.php")), "same keys in {$locale}");
        }
    }
}
