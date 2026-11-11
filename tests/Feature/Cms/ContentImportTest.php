<?php

namespace Tests\Feature\Cms;

use App\Content\Cms\ContentImporter;
use App\Content\Cms\CopySource;
use App\Content\Cms\HomeBlocks;
use App\Content\Cms\Sources;
use App\Content\Cms\Tree;
use App\Models\Content\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * php artisan summit:content-import (brief §8.8 step 2; App\Content\Cms\ContentImporter): idempotent, --fresh rebuilds,
 * admin edits are kept, copy_source is set on every record, block and interface string, navigation keeps the site-map
 * link keys, settings carry the contacts, hours and WA01 drafts, and users and enquiries are never touched.
 */
final class ContentImportTest extends TestCase
{
    use RefreshDatabase;

    /** Every CMS table the import writes. */
    private const TABLES = ['pages', 'services', 'guides', 'guide_categories', 'case_studies', 'faq_categories', 'faqs', 'team_members',
        'specialist_sites', 'situations', 'document_samples', 'ui_strings', 'navigation_menus', 'navigation_items', 'calculator_rule_sets', 'settings'];

    public function test_the_import_is_idempotent(): void
    {
        $this->assertSame(0, Artisan::call('summit:content-import'));
        $first = $this->tables();
        $revisions = DB::table('revisions')->count();
        $this->assertGreaterThan(0, $revisions, 'the first import writes a revision for every record it creates');

        $result = app(ContentImporter::class)->run();
        foreach ($result['counts'] as $table => $count) {
            $this->assertSame(0, $count['created'] + $count['updated'] + $count['removed'], "{$table}: nothing changes the second time");
        }
        $this->assertSame($first, $this->tables(), 'every row is the same');
        $this->assertSame($revisions, DB::table('revisions')->count(), 'no new revision');
    }

    public function test_the_import_fills_every_table_from_the_sources(): void
    {
        app(ContentImporter::class)->run();
        $counts = [];
        foreach (self::TABLES as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $this->assertSame([
            'pages' => 11, 'services' => 11, 'guides' => 9, 'guide_categories' => 3, 'case_studies' => 6, 'faq_categories' => 12,
            'faqs' => 74, 'team_members' => 3, 'specialist_sites' => 6, 'situations' => 44, 'document_samples' => 5,
            'ui_strings' => count((new Sources)->build()['ui_strings']), 'navigation_menus' => 9, 'navigation_items' => 51,
            'calculator_rule_sets' => 2, 'settings' => 16,
        ], $counts);
        $this->assertSame(144, DB::table('ui_strings')->where('group', 'ui')->count(), 'the approved DOCX interface strings');
    }

    public function test_edits_are_kept_and_fresh_rebuilds(): void
    {
        app(ContentImporter::class)->run();
        $user = User::factory()->create();
        $page = Page::query()->findOrFail('P06');
        $approved = $page->getTranslation('h1', 'en');
        $page->setTranslation('h1', 'en', 'Edited in the admin');
        $page->updated_by = $user->id;
        $page->save();

        $result = app(ContentImporter::class)->run();
        $this->assertSame(1, $result['counts']['pages']['kept'], 'an edited record is kept');
        $this->assertSame('Edited in the admin', content()->page('P06', 'en')['h1']);

        $result = app(ContentImporter::class)->run(fresh: true);
        $this->assertSame($approved, content()->page('P06', 'en')['h1'], '--fresh writes the approved text again');
        $this->assertSame(0, $result['counts']['pages']['created'], 'nothing new, the records come back');
    }

    public function test_users_and_enquiries_survive_a_fresh_import(): void
    {
        $user = User::factory()->create();
        DB::table('enquiries')->insert([
            'reference' => 'SLC-261111-ABCDEF', 'idempotency_key' => '5f0a3c1e-5a43-4c8e-9d0e-6b1f1b8a2c11', 'locale' => 'en', 'status' => 'new',
            'name' => 'Test', 'summary' => 'x', 'consent_at' => now(), 'consent_text' => 'x', 'ip_hash' => str_repeat('0', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(ContentImporter::class)->run(fresh: true);
        app(ContentImporter::class)->run(fresh: true);
        $this->assertSame(1, DB::table('users')->where('id', $user->id)->count());
        $this->assertSame(1, DB::table('enquiries')->count());
    }

    public function test_copy_source_is_set_on_every_record_block_and_interface_string(): void
    {
        app(ContentImporter::class)->run();
        foreach (array_diff(self::TABLES, ['settings', 'navigation_items']) as $table) {
            $this->assertSame(0, DB::table($table)->whereNotIn('copy_source', CopySource::ALL)->orWhereNull('copy_source')->count(), "{$table}.copy_source");
        }
        $this->assertSame(0, DB::table('navigation_items')->whereNotIn('copy_source', CopySource::ALL)->count());

        // Approved DOCX copy vs the team's pending copy.
        $this->assertSame([CopySource::DOCX], DB::table('ui_strings')->where('group', 'ui')->distinct()->pluck('copy_source')->all());
        $this->assertSame([CopySource::PENDING], DB::table('ui_strings')->whereIn('group', ['proposed', 'switcher'])->distinct()->pluck('copy_source')->all());
        $this->assertSame([CopySource::PENDING], DB::table('document_samples')->distinct()->pluck('copy_source')->all());
        $this->assertSame([CopySource::PENDING], DB::table('calculator_rule_sets')->distinct()->pluck('copy_source')->all());
        foreach (['pages', 'services', 'guides', 'case_studies', 'faqs', 'team_members', 'situations'] as $table) {
            $this->assertSame([CopySource::DOCX], DB::table($table)->distinct()->pluck('copy_source')->all(), $table);
        }

        // Blocks: the three tool blocks of the home are pending, every other block is approved copy.
        foreach (Page::query()->get() as $page) {
            foreach ($page->blocks as $block) {
                $pending = $page->code === HomeBlocks::PAGE && in_array($block['type'], ['start', 'anatomy', 'navigator'], true);
                $this->assertSame($pending ? CopySource::PENDING : CopySource::DOCX, $block['copy_source'], "{$page->code} {$block['id']}");
            }
        }
    }

    public function test_the_home_blocks_keep_the_docx_order_switches_and_anchors(): void
    {
        app(ContentImporter::class)->run();
        $blocks = Page::query()->findOrFail('P01')->blocks;
        $this->assertSame(array_column(HomeBlocks::BLOCKS, 'type'), array_column($blocks, 'type'));
        $this->assertSame(['hero', 'start', 'services', 'anatomy', 'who', 'review', 'navigator', 'about', 'team', 'cases', 'guides', 'faq', 'contact'], array_column($blocks, 'type'));
        $this->assertSame([null, 'start', 'services', 'anatomy', 'who-we-help', 'review', 'navigator', 'about', 'our-team', 'case-studies', 'guides', 'questions', 'consultation-form'], array_column($blocks, 'anchor'));
        $this->assertSame([true, true, true, true, true, true, true, true, false, true, true, true, true], array_column($blocks, 'enabled'), 'the team block is off (D24)');
        foreach ($blocks as $block) {
            $this->assertContains($block['background'], ['white', 'band', 'dark']);
        }
    }

    public function test_block_texts_share_one_structure_with_limits_above_the_longest_text(): void
    {
        app(ContentImporter::class)->run();
        $hero = Page::query()->findOrFail('P01')->blocks[0];
        $this->assertSame(['en', 'ar', 'ru'], array_keys($hero['data']['hero']['lead']), 'one structure, the texts inside');
        $checked = 0;
        foreach (Page::query()->get() as $page) {
            foreach ($page->blocks as $block) {
                $this->walk($block['data'], '', function (string $path, array $leaf) use ($block, $page, &$checked) {
                    $longest = max(array_map(fn ($t) => is_string($t) ? mb_strlen($t) : 0, $leaf));
                    if ($longest === 0) {
                        return;
                    }
                    $limit = $block['limits'][$path] ?? null;
                    $this->assertNotNull($limit, "{$page->code} {$block['id']} {$path}: a limit");
                    $this->assertGreaterThanOrEqual((int) ceil($longest * 1.15), $limit, "{$page->code} {$path}: 115 % of the longest text in any language");
                    $checked++;
                });
            }
        }
        $this->assertGreaterThan(400, $checked, "every text field of every page block is checked");
    }

    public function test_navigation_keeps_the_site_map_link_keys(): void
    {
        app(ContentImporter::class)->run();
        $keys = fn (string $menu) => DB::table('navigation_items')
            ->join('navigation_menus', 'navigation_menus.id', '=', 'navigation_items.menu_id')
            ->where('navigation_menus.key', $menu)->orderBy('position')->pluck('link_key')->all();
        $this->assertSame(['H-L001', 'H-L002', 'H-L003', 'H-L004', 'H-L005', 'H-L006', 'H-L007'], $keys('primary'));
        $this->assertSame(['H-L011'], $keys('actions'));
        $this->assertSame(['H-L040', 'H-L041', 'H-L042'], $keys('footer_contact'));
        $this->assertSame(['H-L043', 'H-L044', 'H-L045'], $keys('footer_resources'));
        $this->assertSame(['H-L046', 'H-L047', 'H-L048', 'H-L049', 'H-L050'], $keys('footer_useful'));
        $this->assertSame(['H-L051', 'H-L052', 'H-L053', 'H-L054', 'H-L055', 'H-L056'], $keys('footer_specialist'));
        $this->assertSame(['H-L001', 'H-L002', 'H-L003', 'H-L007'], $keys('tabbar'));

        $channels = array_column(content()->menu('footer_contact', 'ar'), 'href', 'link_key');
        $this->assertSame(setting('phone_href'), $channels['H-L040']);
        $this->assertSame(setting('whatsapp_url', 'ar'), $channels['H-L041'], 'TEL, WA01, EMAIL');
        $this->assertSame(setting('email_href'), $channels['H-L042']);
        foreach (content()->menu('primary', 'ru') as $item) {
            $this->assertNotNull($item['label']);
            $this->assertStringStartsWith(url('/ru/'), $item['href']);
        }
    }

    public function test_settings_hold_the_contacts_hours_and_wa01_drafts(): void
    {
        app(ContentImporter::class)->run();
        $this->assertSame('+971 52 466 6191', content()->settings('contact')['phone_display']);
        $this->assertSame('tel:+971524666191', setting('phone_href'));
        $this->assertSame('info@summitlegaluae.com', setting('email'));
        $this->assertSame('971524666191', content()->settings('contact')['whatsapp_number']);
        $this->assertSame(['timezone' => 'Asia/Dubai', 'days' => [1, 2, 3, 4, 5], 'open' => '09:00', 'close' => '18:00', 'closures' => [], 'live_status' => true, 'review_target_hours' => null], content()->settings('hours'));
        foreach (Tree::LOCALES as $locale) {
            $draft = content()->settings('whatsapp')['draft'][$locale];
            $this->assertSame('https://wa.me/971524666191?text='.rawurlencode($draft), setting('whatsapp_url', $locale), "{$locale}: the WA01 link is the number with the draft");
        }
        $stored = DB::table('settings')->where('group', 'whatsapp')->where('name', 'draft')->value('payload');
        $this->assertStringStartsWith('{"en":', (string) $stored, 'JSON text, the approved key order kept (not the MySQL JSON type)');
    }

    public function test_the_calculator_rule_set_is_version_one_of_the_estimators(): void
    {
        app(ContentImporter::class)->run();
        $gratuity = content()->ruleSet('gratuity');
        $this->assertSame(1, (int) $gratuity['version']);
        $this->assertSame(['year_days' => 365, 'month_days' => 30, 'first_years' => 5, 'days_first_years' => 21, 'days_later_years' => 30, 'cap_months' => 24,
            'notice_min' => 30, 'notice_max' => 90, 'scopes' => ['mainland', 'freezone', 'difc', 'adgm', 'government', 'domestic'], 'in_scope' => ['mainland', 'freezone'],
            'contracts' => ['full', 'part', 'temporary']], $gratuity['rules']);
        $rent = content()->ruleSet('rent');
        $this->assertSame(['notice_days' => 90, 'bands' => [['upTo' => 10, 'increase' => 0], ['upTo' => 20, 'increase' => 5], ['upTo' => 30, 'increase' => 10],
            ['upTo' => 40, 'increase' => 15], ['upTo' => null, 'increase' => 20]]], $rent['rules']);
        $this->assertSame(d2p('tools.rent.result.references', [], 'ru'), $rent['legal_basis']['ru']);
    }

    /** @return array<string, list<array<string, mixed>>> every row of every table, without the timestamps */
    private function tables(): array
    {
        $out = [];
        foreach (self::TABLES as $table) {
            $out[$table] = DB::table($table)->orderBy(in_array($table, ['ui_strings', 'navigation_menus', 'navigation_items', 'calculator_rule_sets', 'settings'], true) ? 'id' : 'code')->get()
                ->map(fn ($row) => array_diff_key((array) $row, array_flip(['created_at', 'updated_at', 'published_at'])))->all();
        }

        return $out;
    }

    /** Calls $leaf(path, {en, ar, ru}) for every text leaf of a block's data (list positions as "*"). */
    private function walk(mixed $node, string $path, callable $leaf): void
    {
        if (! is_array($node)) {
            return;
        }
        if (Tree::isLeaf($node)) {
            $leaf($path, $node);

            return;
        }
        $list = array_is_list($node);
        foreach ($node as $k => $child) {
            $this->walk($child, $path === '' ? ($list ? '*' : (string) $k) : $path.'.'.($list ? '*' : $k), $leaf);
        }
    }
}
