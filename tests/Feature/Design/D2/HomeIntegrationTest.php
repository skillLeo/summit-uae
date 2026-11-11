<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 home as a whole (integration review of Phases B and C): the shared contracts between the
 * sections, which no single area test owns. Section order and anchors (contract A), one H1, unique ids,
 * a live office-hours slot beside every approved hours sentence of the page (contract E; since Design 2.1 the
 * footer, the menu sheet, the hero and the trust row show the homepage sentence without one: brief §3.7 puts
 * the live status in the contact block only, never in the hero), the
 * layout of lazy sections keyed on html.js rather than their mount class, and no style attributes.
 */
final class HomeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    /**
     * Contract A, since Design 2.1 Phase 2 the order of brief §5.1 (the homepage DOCX, D26): the approved sections
     * 01-09 with the switchable tool blocks between them (the hero is identified by its H1). The trust row is gone
     * (D20), the calculators have their own page and the team block is off by default (D24).
     */
    private const ORDER = ['hero-title', 'start', 'services', 'anatomy', 'who-we-help', 'review', 'navigator', 'about', 'case-studies', 'guides', 'questions', 'consultation-form'];

    /** Design 1 home anchors that must keep working. */
    // (faq-42 and the other FAQ-page anchors live on P05: the home's section 08 holds its eight own questions, D41 / §5.1.)
    private const ANCHORS = ['main-content', 'who-individuals', 'who-businesses', 'who-investors', 'who-residents', 'who-entrepreneurs',
        'home-faq-1', 'home-faq-3', 'home-faq-8', 'preview-case-01', 'preview-case-04', 'guide-g01-title'];

    /** @var array<string, string> */
    private array $pages = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function home(string $locale): string
    {
        return $this->pages[$locale] ??= $this->withUnencryptedCookies(['summit_design' => '2'])
            ->get("/{$locale}/")->assertOk()->getContent();
    }

    public function test_sections_follow_contract_a_with_one_h1_and_unique_ids(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->home($locale);
            $positions = array_map(fn (string $id) => strpos($html, ' id="'.$id.'"'), self::ORDER);
            foreach ($positions as $i => $at) {
                $this->assertNotFalse($at, self::ORDER[$i]." is rendered ({$locale})");
            }
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, "home sections are in the spec order ({$locale})");
            $this->assertSame(1, preg_match_all('/<h1\b/', $html), "one H1 ({$locale})");
            foreach (self::ANCHORS as $id) {
                $this->assertStringContainsString(' id="'.$id.'"', $html, "anchor #{$id} ({$locale})");
            }
            preg_match_all('/\sid="([^"]+)"/', $html, $m);
            $dupes = array_keys(array_filter(array_count_values($m[1]), fn ($n) => $n > 1));
            $this->assertSame([], $dupes, "no duplicate ids ({$locale})");
            $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $html, "no style attributes ({$locale})");
        }
    }

    public function test_every_approved_hours_sentence_has_a_live_status_slot(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->home($locale);
            // Design 2.1 (brief §3.7; owners: footer-shell, home-a): the shell (the footer and the menu sheet) prints the
            // homepage file's hours sentence without a live status (the status belongs to the contact block, never the
            // hero, which has no hours line since D18); the contact block keeps its slot. Every place prints the sentence
            // with each time bound to its am/pm (App\Design\D2\Hours::keepTimes(), D11).
            $hero = substr($html, (int) strpos($html, '<section class="section hero"'));
            $this->assertStringNotContainsString(e($home = \App\Design\D2\Hours::keepTimes(content()->page('P01', $locale)['content']['contact']['hours'] ?? '')), substr($hero, 0, (int) strpos($hero, '</section>')), "no hours line in the hero ({$locale})");
            $home = \App\Design\D2\Hours::keepTimes(content()->page('P01', $locale)['content']['contact']['hours'] ?? '');
            $shell = [];
            foreach ([['<footer class="d2-footer', '</footer>'], ['<div class="sheet sheet--menu"', '<div class="sheet sheet--services"']] as [$from, $to]) {
                $start = strpos($html, $from);
                $this->assertNotFalse($start, "{$from} ({$locale})");
                $part = substr($html, $start, (int) strpos($html, $to, $start) - $start);
                $this->assertStringContainsString(e($home), $part, "{$from} shows the homepage sentence ({$locale})");
                $this->assertStringNotContainsString('data-hours-status', $part, "no live status in {$from} ({$locale})");
                $shell[] = $part;
            }
            $page = str_replace($shell, '', $html);

            $sentences = array_filter([
                \App\Design\D2\Hours::keepTimes(content()->page('P07', $locale)['content']['details']['hours'] ?? ''),
                $home,
            ]);
            $this->assertNotEmpty($sentences);
            $found = 0;
            foreach (array_unique($sentences) as $sentence) {
                $needle = e($sentence);
                $offset = 0;
                while (($at = strpos($page, $needle, $offset)) !== false) {
                    $offset = $at + strlen($needle);
                    // the slot sits next to the sentence, inside the same block (no other tag soup in between)
                    $after = substr($page, $offset, 400);
                    $this->assertMatchesRegularExpression('/data-hours-status/', $after, "a status slot follows the hours sentence at {$at} ({$locale})");
                    $found++;
                }
            }
            // the contact card (the footer and the menu sheet show the sentence without a status)
            $this->assertGreaterThanOrEqual(1, $found, "hours sentences on the home ({$locale})");
        }
    }

    /** Acceptance C: every approved home section is present (P01 copy, compared without whitespace and tags). */
    public function test_every_approved_home_section_is_rendered(): void
    {
        foreach (self::LOCALES as $locale) {
            $text = preg_replace('/\s+/u', '', html_entity_decode(strip_tags($this->home($locale)), ENT_QUOTES | ENT_HTML5));
            $page = content()->page('P01', $locale);
            $c = $page['content'];
            $strings = [
                'h1' => $page['h1'],
                'hero.lead' => $c['hero']['lead'], 'hero.body' => $c['hero']['body'],
                'services.heading' => $c['services']['heading'], 'services.intro' => $c['services']['intro'], 'services.closing' => $c['services']['closing'],
                'who.heading' => $c['who_we_help']['heading'], 'who.intro' => $c['who_we_help']['intro'],
                'review.heading' => $c['review']['heading'], 'review.questions_heading' => $c['review']['questions_heading'], 'review.method_heading' => $c['review']['method_heading'],
                'about.heading' => $c['about']['heading'],
                'cases.heading' => $c['cases']['heading'], 'cases.intro' => $c['cases']['intro'],
                'guides.heading' => $c['guides']['heading'], 'guides.intro' => $c['guides']['intro'],
                'faq.heading' => $c['faq']['heading'],
                'contact.heading' => $c['contact']['heading'], 'contact.intro' => $c['contact']['intro'],
                'contact.languages' => $c['contact']['languages'], 'contact.hours' => $c['contact']['hours'],
            ];
            foreach ($c['who_we_help']['items'] as $i => $item) {
                $strings["who.items.{$i}.title"] = $item['title'];
                $strings["who.items.{$i}.text"] = $item['text'];
            }
            foreach ($c['review']['paragraphs'] as $i => $p) {
                $strings["review.paragraphs.{$i}"] = $p;
            }
            foreach ($c['review']['questions'] as $i => $q) {
                $strings["review.questions.{$i}"] = $q;
            }
            foreach ($c['review']['steps'] as $i => $step) {
                $strings["review.steps.{$i}.title"] = $step['title'];
                $strings["review.steps.{$i}.text"] = $step['text'];
            }
            foreach ($c['about']['paragraphs'] as $i => $p) {
                $strings["about.paragraphs.{$i}"] = $p;
            }
            foreach ($c['faq']['items'] as $i => $item) {
                $strings["faq.items.{$i}.question"] = $item['question'];
                $strings["faq.items.{$i}.answer"] = $item['answer'];
            }
            foreach ($strings as $key => $value) {
                $this->assertNotSame('', trim((string) $value), "P01 {$key} has approved copy ({$locale})");
                $this->assertStringContainsString(preg_replace('/\s+/u', '', (string) $value), $text, "P01 {$key} is on the home ({$locale})");
            }
        }
    }

    public function test_lazy_sections_key_their_layout_on_html_js_not_on_the_mount_class(): void
    {
        $tools = (string) file_get_contents(resource_path('css/d2/sections/tools.css'));
        $this->assertStringContainsString('.js .tool__nojs', $tools);
        $this->assertStringNotContainsString('.tool:not(.is-ready) .tool-js', $tools);

        $navigator = (string) file_get_contents(resource_path('css/d2/sections/navigator.css'));
        $this->assertStringContainsString('.js .navigator .nav-slot', $navigator);
        $this->assertStringContainsString('.js .navigator .nav-submit', $navigator);
        $this->assertStringNotContainsString('.navigator.is-ready .nav-slot {', $navigator);
    }
}
