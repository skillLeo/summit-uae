<?php

namespace Tests\Feature\Design\D2;

use App\Content\NavigatorData;
use App\Models\Content\Situation;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Design 2.1 Phase 2, area start-review-who:
 *   D19  "Start your matter" (#start, <x-d2.hero.tool>): before a choice the result half shows the quick picks
 *        (the featured situations of the CMS, imported from config design.d2.home.quick_picks; the one chip, approved
 *        situations), after a choice the documents; never
 *        an empty box. The picks follow the audience, work without JavaScript and never cut their text.
 *   D49  "What a Review of Your Matter Can Clarify" (#review): paragraphs on the start side, the five questions under
 *        their H3 on the end side, then the four workflow steps in one row of four (2 x 2 on tablets, stacked on
 *        phones), in the DOCX order, without cards or the still-life.
 * Who We Help (brief §5.1 item 4) is pinned in WhoWeHelpTest.
 */
final class StartReviewWhoTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function home(string $locale = 'en', string $query = ''): string
    {
        app()->setLocale($locale);

        return $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])
            ->get("/{$locale}/".($query !== '' ? '?'.$query : ''))->assertOk()->getContent();
    }

    private function start(string $html): string
    {
        $this->assertSame(1, preg_match('#<section class="section start" id="start".*?</section>#s', $html, $m), 'the start block renders');

        return $m[0];
    }

    private function review(string $html): string
    {
        $this->assertSame(1, preg_match('#<section class="section method" id="review".*?</section>#s', $html, $m), '#review renders');

        return $m[0];
    }

    /** @return array<string, string> situation code => approved text, in the given locale */
    private function situations(string $locale): array
    {
        app()->setLocale($locale);
        $out = [];
        foreach ((new NavigatorData(content()))->services() as $service) {
            foreach (array_values($service['situations']) as $i => $text) {
                $out[$service['code'].'-'.($i + 1)] = $text;
            }
        }

        return $out;
    }

    /** @return list<string> the quick-pick codes rendered, in order */
    private function picks(string $start): array
    {
        preg_match_all('/data-quick-pick="([^"]+)"/', $start, $m);

        return $m[1];
    }

    // ------------------------------------------------------------------------------------------------ D19

    public function test_the_default_quick_picks_are_three_known_situations(): void
    {
        $codes = content()->featuredSituations();
        $this->assertSame(config('design.d2.home.quick_picks'), $codes, 'the import copies the default picks into situations.featured');
        $this->assertIsArray($codes);
        $this->assertCount(3, $codes);
        $this->assertSame($codes, array_values(array_unique($codes)));
        $known = $this->situations('en');
        foreach ($codes as $code) {
            $this->assertNotNull(NavigatorData::parse($code), "{$code} is a situation code");
            $this->assertArrayHasKey($code, $known, "{$code} exists in NavigatorData");
        }
    }

    public function test_before_a_choice_the_result_half_shows_the_quick_picks_as_chips(): void
    {
        $codes = content()->featuredSituations();
        foreach (self::LOCALES as $locale) {
            $start = $this->start($this->home($locale));
            $situations = $this->situations($locale);
            $this->assertSame($codes, $this->picks($start), "{$locale}: the configured picks, in order");

            // The live line names the picks, and the list is labelled by it.
            $label = __('proposed.d2.hero.tool.picks_label', [], $locale);
            $this->assertMatchesRegularExpression('/<p class="hero-tool__count" id="hero-tool-count" aria-live="polite" data-tool-count\s+data-picks-label="'.preg_quote(e($label), '/').'"[^>]*>'.preg_quote(e($label), '/').'<\/p>/', $start);
            $this->assertStringContainsString('<ul class="hero-tool__pick-list" role="list" aria-labelledby="hero-tool-count">', $start);
            $this->assertMatchesRegularExpression('/<div class="hero-tool__picks" data-docs-panel="">/', $start, "{$locale}: shown before a choice");
            $this->assertMatchesRegularExpression('/<p class="hero-tool__hint" data-picks-none\s+hidden/', $start, "{$locale}: the fallback line stays hidden");

            foreach ($codes as $code) {
                [$service] = explode('-', $code);
                // The one chip (D04: no icon, no dot), a submit button carrying the code (the no-JS path), with the
                // complete approved situation as its label.
                $this->assertMatchesRegularExpression('/<li class="hero-tool__pick" data-pick-service="'.$service.'"\s*>\s*<button class="chip chip--filter hero-tool__pick-chip"\s+name="situation" value="'.$code.'" data-quick-pick="'.$code.'"\s+type="submit"\s*>\s*<span class="chip__label">([^<]|<bdi[^>]*>|<\/bdi>)+<\/span>\s*<\/button>/', $start, "{$locale}: pick {$code}");
                $this->assertStringContainsString($situations[$code], html_entity_decode(strip_tags($start), ENT_QUOTES | ENT_HTML5), "{$locale}: {$code} is complete");
            }
            $picks = substr($start, (int) strpos($start, 'hero-tool__pick-list'));
            $picks = substr($picks, 0, (int) strpos($picks, '</ul>'));
            $this->assertStringNotContainsString('data-icon', $picks);
            $this->assertStringNotContainsString('chip__icon', $picks);
            // Continue comes first in the form, so it stays the default (Enter) button.
            $this->assertLessThan(strpos($start, 'data-quick-pick'), strpos($start, 'data-tool-continue'));
        }
    }

    public function test_the_picks_follow_the_audience_and_a_chosen_situation_shows_the_documents(): void
    {
        // Investor lists S05 but not S01 or S06 (config audience_map.hero): those picks are hidden, like the select's groups.
        $start = $this->start($this->home('en', 'audience=investor'));
        $map = config('audience_map.hero.investor');
        foreach (content()->featuredSituations() as $code) {
            [$service] = explode('-', $code);
            $hidden = ! in_array($service, $map, true);
            $this->assertSame($hidden ? 1 : 0, preg_match('/<li class="hero-tool__pick" data-pick-service="'.$service.'"\s+hidden/', $start), "{$code} hidden for investors: ".($hidden ? 'yes' : 'no'));
        }

        // A situation sent back (no-JS round trip): the documents show, the picks give way.
        $start = $this->start($this->home('en', 'situation=S05-3'));
        $this->assertMatchesRegularExpression('/<div class="hero-tool__picks is-off" data-docs-panel="">/', $start);
        $this->assertMatchesRegularExpression('/<ul class="hero-tool__chips" role="list" data-docs-panel="S05"/', $start);
        $this->assertStringContainsString('>'.e(trans_choice('proposed.d2.hero.tool.documents', 4, ['count' => 4])).'</p>', $start);
    }

    public function test_the_quick_picks_are_the_featured_situations_of_the_cms(): void
    {
        foreach (Situation::query()->where('featured', true)->get() as $situation) {
            $situation->update(['featured' => false, 'featured_position' => null]);
        }
        Situation::query()->findOrFail('S08-1')->update(['featured' => true, 'featured_position' => 2]);
        Situation::query()->findOrFail('S06-1')->update(['featured' => true, 'featured_position' => 1]);

        $this->assertSame(['S06-1', 'S08-1'], content()->featuredSituations(), 'in featured_position order');
        $this->assertSame(['S06-1', 'S08-1'], $this->picks($this->start($this->home('en'))), 'the home shows the saved picks at once');
    }

    public function test_unknown_duplicate_or_extra_codes_are_ignored_and_no_pick_falls_back_to_one_quiet_line(): void
    {
        app()->setLocale('en');
        $tool = fn (array $picks, string $audience = 'individual') => Blade::render(
            '<x-d2.hero.tool :picks="$picks" :audience="$audience" />',
            ['picks' => $picks, 'audience' => $audience],
        );
        $this->assertSame(['S06-1', 'S01-1', 'S02-1'], $this->picks($tool(['S99-1', 'S06-1', 'S06-1', 'S01-1', 'S02-1', 'S03-1', 'S06-9', ['S04-1']])), 'known codes, each once, at most three');

        // The investor audience lists none of these picks: the plain documents label and the hint, never a box.
        $start = $tool(['S06-1'], 'investor');
        $this->assertStringContainsString('>'.e(d2p('hero.tool.documents_label')).'</p>', $start);
        $this->assertMatchesRegularExpression('/<p class="hero-tool__hint" data-picks-none\s+data-copy-status="proposed">'.preg_quote(e(d2p('hero.tool.documents_empty')), '/').'<\/p>/', $start);

        $start = $tool([]);
        $this->assertStringNotContainsString('hero-tool__pick-list', $start);
        $this->assertStringContainsString('>'.e(d2p('hero.tool.documents_label')).'</p>', $start);

        // No featured situation in the CMS: the home falls back to the same quiet line.
        foreach (Situation::query()->where('featured', true)->get() as $situation) {
            $situation->update(['featured' => false, 'featured_position' => null]);
        }
        $start = $this->start($this->home('en'));
        $this->assertStringNotContainsString('hero-tool__pick-list', $start);
    }

    public function test_the_picks_css_never_cuts_and_draws_no_box(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/sections/start.css'));
        foreach (['dashed', 'line-clamp', 'text-overflow', 'nowrap', 'box-shadow', 'gradient'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, "start.css: no {$gone}");
        }
        $this->assertStringNotContainsString('hero-tool__empty', $css);
        // A wrapped pick keeps the one chip's look with a radius of half its one-line height.
        $this->assertStringContainsString('--radius-pick: calc(var(--chip-h) / 2);', $css);
        $this->assertStringContainsString('white-space: normal;', $css);
    }

    // ------------------------------------------------------------------------------------------------ D49

    public function test_the_review_follows_the_docx_order_in_two_columns_then_the_steps(): void
    {
        foreach (self::LOCALES as $locale) {
            $section = $this->review($this->home($locale));
            $review = content()->page('P01', $locale)['content']['review'];

            $order = ['<h2 class="method__title" id="review-title">'];
            foreach ($review['paragraphs'] as $paragraph) {
                $order[] = '<p>'.e($paragraph).'</p>';
            }
            $order[] = 'id="review-questions-title"';
            foreach ($review['questions'] as $question) {
                $order[] = '<li class="method__question">'.e($question).'</li>';
            }
            $order[] = 'id="review-method-title"';
            foreach ($review['steps'] as $step) {
                $order[] = e($step['title']);
            }
            $positions = array_map(fn (string $needle) => strpos($section, $needle), $order);
            $this->assertNotContains(false, $positions, "{$locale}: every approved line is present");
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, "{$locale}: H2, paragraphs, questions, steps (the DOCX order)");

            // Paragraphs and questions share one grid row; the steps follow under their own H3.
            $this->assertMatchesRegularExpression('/<div class="grid method__intro">\s*<div class="method__text">/', $section);
            $this->assertMatchesRegularExpression('/<\/div>\s*<div class="method__questions">\s*<h3 class="method__heading" id="review-questions-title">/', $section);
            $this->assertSame(4, substr_count($section, '<li class="method-step">'));
            $this->assertSame(5, substr_count($section, '<li class="method__question">'));
        }
    }

    public function test_the_review_layout_is_two_columns_then_a_row_of_four(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/sections/method.css'));
        $this->assertMatchesRegularExpression('/@media \(width >= 1024px\) \{\s*\.method__text \{\s*grid-column: 1 \/ span 6;\s*\}\s*\.method__questions \{\s*grid-column: 8 \/ span 5;/', $css, 'start side and end side from 1024 px');
        $this->assertMatchesRegularExpression('/@media \(width >= 768px\) \{\s*\.method__steps:not\(\.method__steps--stacked\) \{\s*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);/', $css, '2 x 2 on tablets');
        $this->assertMatchesRegularExpression('/@media \(width >= 1024px\) \{\s*\.method__steps:not\(\.method__steps--stacked\) \{\s*grid-template-columns: repeat\(4, minmax\(0, 1fr\)\);/', $css, 'one row of four');
        $this->assertStringContainsString('grid-template-rows: subgrid;', $css, 'titles and texts of a row share their lines');
        foreach (['method__still', '.card', 'box-shadow', 'gradient', '--bronze'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, "method.css: no {$gone}");
        }
        foreach (self::LOCALES as $locale) {
            $this->assertSame([], require lang_path("{$locale}/proposed-d2/method.php"), "{$locale}: the still-life's name is deleted");
        }
    }
}
