<?php

namespace Tests\Feature\Cms;

use App\Content\Cms\HomeBlocks;
use App\Models\Content\Page;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Design 2 home renders the blocks of page P01 (brief §8.4; components/d2/blocks/): every block in EN, AR and RU, in
 * the blocks' order, with their switches and anchors.
 */
final class HomeBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        config(['design.d2.show_pending' => true, 'design.d2.home.block_overrides' => []]);
    }

    private function home(string $locale = 'en'): string
    {
        return (string) $this->withUnencryptedCookies(['summit_design' => '2', 'summit_qa' => '1'])->get("/{$locale}/")->assertOk()->getContent();
    }

    /** @param callable(list<array<string, mixed>>): list<array<string, mixed>> $change */
    private function blocks(callable $change): void
    {
        $page = Page::query()->findOrFail(HomeBlocks::PAGE);
        $page->blocks = array_values($change($page->blocks));
        $page->save();
    }

    /** The position of each block on the page: the hero's data-hero, every other block's section id. */
    private function positions(string $html, array $types): array
    {
        $out = [];
        foreach ($types as $type) {
            $anchor = collect(HomeBlocks::BLOCKS)->firstWhere('type', $type)['anchor'];
            $needle = $anchor === null ? 'data-hero>' : 'id="'.$anchor.'"';
            $position = strpos($html, $needle);
            $this->assertNotFalse($position, "block {$type} renders ({$needle})");
            $out[$type] = $position;
        }

        return $out;
    }

    public function test_every_block_renders_in_every_language(): void
    {
        $this->blocks(fn (array $blocks) => array_map(fn (array $b) => ['enabled' => true] + $b, $blocks));
        $types = HomeBlocks::types();
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->home($locale);
            $positions = $this->positions($html, $types);
            $sorted = $positions;
            asort($sorted);
            $this->assertSame($types, array_keys($sorted), "{$locale}: the blocks' order");
            $this->assertStringNotContainsString('⟦', $html, "{$locale}: no missing copy");
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), "{$locale}: one H1");
        }
    }

    public function test_the_home_follows_the_order_switches_and_anchors_of_its_blocks(): void
    {
        $this->blocks(function (array $blocks) {
            $byType = array_column($blocks, null, 'type');
            $faq = $byType['faq'];
            $rest = array_values(array_filter($blocks, fn ($b) => $b['type'] !== 'faq'));
            array_splice($rest, 1, 0, [$faq]); // the questions right after the hero

            return array_map(fn (array $b) => match ($b['type']) {
                'about' => ['enabled' => false] + $b,
                'guides' => ['anchor' => 'legal-guides'] + $b,
                default => $b,
            }, $rest);
        });
        $html = $this->home('en');
        $this->assertLessThan(strpos($html, 'id="start"'), strpos($html, 'id="questions"'), 'the questions now come first');
        $this->assertStringNotContainsString('<section class="section about"', $html, 'about is off');
        $this->assertStringContainsString('<section class="section guides" id="legal-guides"', $html, 'the anchor is the section id');
    }

    public function test_the_team_block_is_off_by_default_and_an_unknown_type_renders_nothing(): void
    {
        $this->assertStringNotContainsString('id="our-team"', $this->home('en'), 'D24: off by default');
        $this->blocks(fn (array $blocks) => [...$blocks, ['id' => 'P01.qa', 'type' => 'testimonials', 'enabled' => true, 'anchor' => 'qa', 'copy_source' => 'docx', 'data' => []]]);
        $this->assertStringNotContainsString('id="qa"', $this->home('en'), 'no component, no output (no testimonials block: compliance)');
    }
}
