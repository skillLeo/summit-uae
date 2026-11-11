<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Docs;
use App\Design\D2\HeroStage;
use App\Design\D2\Hours;
use App\Design\D2\Prefill;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 home hero "Case File + Start your matter" (upgrade spec §12 B1–B5; owner: hero; Design 2.1 Phase 1:
 * D01–D09, D11). Server-rendered contract: approved copy (no invented eyebrow, the home page file's own hours line
 * without a live status), the one Gold button, the document stage as an illustration and its pager, the callout
 * captions, the caption without middle dots, the tool card as a working GET form (no arrows, no decorative icons,
 * documents never clamped), proposed copy in all three languages, CSP-safe markup and the boot script's arming
 * class with its safety timeout.
 */
final class HeroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function home(string $locale = 'en', string $query = ''): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])
            ->get("/{$locale}/".($query !== '' ? '?'.$query : ''))
            ->assertOk()
            ->getContent();
    }

    /** The "Start your matter" block under the hero (brief §5.1: its own block since Phase 2). */
    private function start(string $html): string
    {
        $this->assertSame(1, preg_match('/<section class="section start".*?<\/section>/s', $html, $m), 'the start block renders');

        return $m[0];
    }

    private function hero(string $html): string
    {
        $this->assertSame(1, preg_match('/<section class="section hero".*?<\/section>/s', $html, $m), 'the hero section renders');

        return $m[0];
    }

    public function test_hero_shows_the_approved_copy_in_every_language(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            app()->setLocale($locale);
            $html = $this->home($locale);
            $hero = $this->hero($html);
            $p01 = content()->page('P01');

            $this->assertSame(1, substr_count($html, '<h1'), "{$locale}: one H1 on the page");
            $this->assertMatchesRegularExpression('/<h1 class="hero__title" id="hero-title">/', $hero);
            $h1 = preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', $this->between($hero, '<h1', '</h1>'))))));
            $this->assertSame($p01['h1'], trim(preg_replace('/^[^>]*>/', '', $h1)), "{$locale}: approved H1");

            $text = html_entity_decode(strip_tags($hero));
            $this->assertStringContainsString($p01['content']['hero']['lead'], $text, "{$locale}: approved lead");
            $this->assertStringContainsString($p01['content']['hero']['body'], $text, "{$locale}: approved body");
            $this->assertStringContainsString(ui('cta.request_consultation'), $text);
            $this->assertStringContainsString(ui('cta.whatsapp'), $text);
            $this->assertStringContainsString('href="'.e(setting('whatsapp_url')).'"', $hero, "{$locale}: WhatsApp with the approved draft");
            // D18: no office hours in the hero (they stay in the contact block and the footer), and no live status
            // (brief §3.7: the contact block only).
            $this->assertStringNotContainsString('hero__hours', $hero, "{$locale}: no hours line in the hero");
            $this->assertStringNotContainsString(Hours::sentence('home', $locale), $text, "{$locale}: not the home hours sentence");
            $this->assertStringNotContainsString('data-hours-status', $hero, "{$locale}: no live status in the hero (brief §3.7)");
            // D01, D02: the invented eyebrow is deleted, markup and keys.
            $this->assertStringNotContainsString('hero__eyebrow', $hero);
            $this->assertArrayNotHasKey('eyebrow', require lang_path("{$locale}/proposed-d2/hero.php"), "{$locale}: hero.eyebrow is deleted");
            $copy = html_entity_decode(strip_tags($this->between($hero, '<div class="hero__copy">', '<div class="hero-visual')));
            $this->assertStringNotContainsString('·', $copy, "{$locale}: no middle dots in the hero copy");
        }
    }

    /** D15: one document at a time on a file of two blank sheets; every document is in the stage, the cheque shown. */
    public function test_stage_holds_the_six_sample_documents_with_watermarks_and_a_label(): void
    {
        $hero = $this->hero($this->home());

        $this->assertMatchesRegularExpression('/class="hero-stage" role="img" aria-label="'.preg_quote(e(d2p('hero.stage_label')), '/').'"/', $hero);
        $this->assertSame(1, substr_count($hero, 'data-canvas'), 'one canvas for every width (CSS picks the layout)');
        preg_match_all('/class="hero-doc hero-doc--([a-z]+)( is-active)?" data-hero-doc="\1"/', $hero, $d);
        $this->assertSame(['cheque', 'contract', 'tenancy', 'transfer', 'messages', 'notice'], $d[1], 'the six documents, in pager order');
        $this->assertSame([' is-active', '', '', '', '', ''], $d[2], 'one document shown: the cheque');
        foreach (Docs::TYPES as $type) {
            $this->assertStringContainsString('data-doc="'.$type.'" data-variant="stage"', $hero, "<x-d2.doc.{$type}> on the stage");
        }
        // The file under the document: two blank sheets (paper only, no text that could be cut off).
        $this->assertSame(1, preg_match('/<div class="hero-file" aria-hidden="true">(.*?)<\/div>/s', $hero, $file));
        $this->assertSame(2, substr_count($file[1], 'class="hero-sheet hero-sheet--'));
        $this->assertSame('', trim(strip_tags($file[1])), 'the sheets carry no text');
        // Every document carries the trilingual watermark (the cheque also carries its stapled memo's).
        $this->assertGreaterThanOrEqual(6, substr_count($hero, 'class="doc__wm"'));
        $this->assertSame(6, substr_count($hero, 'class="hero-callouts" data-hero-callouts aria-hidden="true"'), 'each document carries its own callouts');
        $this->assertSame(18, substr_count($hero, '<span class="hero-callout hero-callout--'), 'three callouts per document');
    }

    /** D17: six progress segments, the document's name, previous / next; no counter, no dots, no autoplay. */
    public function test_pager_and_callout_captions(): void
    {
        $hero = $this->hero($this->home());
        $pager = $this->between($hero, 'data-hero-pager', 'hero-visual__caption');

        $this->assertStringContainsString('data-types="cheque contract tenancy transfer messages notice"', $pager);
        $this->assertSame(6, substr_count($pager, 'data-pager-seg='), 'six segments');
        $this->assertStringContainsString('role="group" aria-label="'.e(d2p('hero.segments_label')).'"', $pager);
        $this->assertMatchesRegularExpression('/aria-label="'.preg_quote(e(Docs::name('cheque')), '/').'"\s+aria-current="true"/', $pager);
        $this->assertMatchesRegularExpression('/<p class="hero-pager__name" aria-live="polite" data-pager-name[^>]*>'.preg_quote(e(Docs::name('cheque')), '/').'<\/p>/', $pager, 'the document name, a polite live region');
        $this->assertMatchesRegularExpression('/aria-label="'.preg_quote(e(d2p('hero.prev')), '/').'"[^>]*aria-disabled="true"/', $pager, 'no wrap-around: previous is disabled on the first document');
        $this->assertDoesNotMatchRegularExpression('/aria-label="'.preg_quote(e(d2p('hero.next')), '/').'"[^>]*aria-disabled/', $pager);
        $this->assertMatchesRegularExpression('/class="btn btn--secondary btn--40 icon-btn hero-pager__step"/', $pager, 'the 40 px previous / next buttons');
        // No counter ("1/6"), no dots, no autoplay.
        $this->assertDoesNotMatchRegularExpression('/\d\s*\/\s*\d/', strip_tags($pager));
        $this->assertStringNotContainsString('pager__dot', $pager);
        $this->assertStringNotContainsString('autoplay', $pager);
        foreach (['en', 'ar', 'ru'] as $locale) {
            $this->assertArrayNotHasKey('pager_position', require lang_path("{$locale}/proposed-d2/hero.php"), "{$locale}: the counter's key is gone");
        }

        $notes = $this->between($hero, 'data-hero-notes', 'data-hero-pager');
        $this->assertSame(6, substr_count($notes, 'data-notes='));
        $this->assertSame(5, preg_match_all('/data-notes="[a-z]+"[^>]*\shidden[\s>]/', $notes), 'only the document shown is listed');
        $this->assertDoesNotMatchRegularExpression('/data-notes="cheque"[^>]*\shidden[\s>]/', $notes);
        foreach (Docs::callouts('cheque') as $c) {
            $this->assertStringContainsString(e($c['label']), $notes);
        }
        $this->assertStringContainsString(e(d2p('hero.caption')), $hero);
        // D02, D17: the caption is one sentence without middle dots, in every language; the pager's steps are the
        // carousel's own previous / next (the arrows brief §3.6 rule 3 allows), never plain chevrons.
        foreach (['en', 'ar', 'ru'] as $locale) {
            $this->assertStringNotContainsString('·', __('proposed.d2.hero.caption', [], $locale), "{$locale}: caption without middle dots");
        }
        $this->assertSame(2, preg_match_all('/data-icon="carousel-(prev|next)"/', $pager));
        $this->assertDoesNotMatchRegularExpression('/data-icon="(chevron|chevron-start|arrow|arrow-start)"/', $pager);
    }

    public function test_tool_card_is_a_get_form_to_the_consultation_form(): void
    {
        $html = $this->home();
        $this->assertStringNotContainsString('<form', $this->hero($html), 'the hero holds no tool card (brief §5.1)');
        $tool = $this->between($this->start($html), '<form', '</form>');

        $this->assertStringContainsString('action="'.e(Prefill::url()).'" method="get"', $tool);
        $this->assertStringContainsString('x-data="d2HeroTool"', $tool);
        foreach (['individual', 'business', 'investor'] as $audience) {
            $this->assertStringContainsString('name="audience" value="'.$audience.'"', $tool);
            $this->assertStringContainsString(e(d2p('hero.tool.audience.'.$audience)), $tool);
        }
        $this->assertMatchesRegularExpression('/name="audience" value="individual"\s+checked/', $tool);
        $this->assertSame(11, substr_count($tool, '<optgroup'), 'every service, grouped (JavaScript filters by audience)');
        $this->assertSame(44, preg_match_all('/<option value="S(0[1-9]|1[01])-[1-4]"/', $tool), '44 approved situations');
        $this->assertStringContainsString('<option value=""', $tool, 'a placeholder: nothing is chosen for the visitor');

        preg_match('/<script type="application\/json" data-tool-data>(.*?)<\/script>/s', $tool, $json);
        $this->assertSame(['audiences' => config('audience_map.hero')], json_decode($json[1], true));

        $label = trans_choice('proposed.d2.hero.tool.documents', 4, ['count' => 4]);
        $this->assertMatchesRegularExpression('/data-docs-panel="S06"\s+data-count-label="'.preg_quote(e($label), '/').'"/', $tool);
        $s06 = content()->service('S06');
        foreach ($s06['documents'] as $document) {
            $this->assertStringContainsString(e($document), $tool, 'approved documents as chips');
        }
        $this->assertStringContainsString('aria-live="polite" data-tool-count', $tool);
        // Design 2.1: Continue is an Ink button without an arrow (D03, D06); no decorative icons (D04); the approved
        // documents are never clamped or held in a tooltip (D09).
        $this->assertMatchesRegularExpression('/<button class="btn btn--ink btn--48 hero-tool__continue"[^>]*type="submit"/', $tool);
        preg_match_all('/data-icon="([^"]+)"/', $tool, $icons);
        $this->assertSame(['chevron-down'], array_values(array_unique($icons[1])), 'no icon in the tool card but the select\'s own chevron (functional)');
        $this->assertStringNotContainsString('data-tooltip', $tool);
        $css = (string) file_get_contents(resource_path('css/d2/sections/start.css'));
        $this->assertStringNotContainsString('line-clamp', $css, 'no clamped approved copy');
        $this->assertStringNotContainsString('text-overflow', $css);
        // D19 (brief §3.6 rule 11): before a choice the result half shows the quick picks, never an empty box
        // (StartReviewWhoTest pins the picks themselves).
        $this->assertStringContainsString('<div class="hero-tool__picks" data-docs-panel="">', $tool);
        $this->assertStringNotContainsString('hero-tool__empty', $tool);
        $this->assertStringNotContainsString('dashed', $css);
    }

    public function test_no_js_round_trip_keeps_the_choice_and_bad_values_are_ignored(): void
    {
        $tool = $this->between($this->start($this->home('en', 'audience=business&situation=S04-1')), '<form', '</form>');
        $this->assertMatchesRegularExpression('/name="audience" value="business"\s+checked/', $tool);
        $this->assertStringContainsString('<option value="S04-1" selected', $tool);
        $this->assertMatchesRegularExpression('/<ul class="hero-tool__chips" role="list" data-docs-panel="S04"/', $tool, 'the chosen service shows its documents');
        $this->assertMatchesRegularExpression('/<ul class="hero-tool__chips is-off" role="list" data-docs-panel="S06"/', $tool);
        $this->assertMatchesRegularExpression('/<div class="hero-tool__picks is-off" data-docs-panel="">/', $tool, 'a chosen situation hides the quick picks');

        $tool = $this->between($this->start($this->home('en', 'audience=pirate&situation=S12-9')), '<form', '</form>');
        $this->assertMatchesRegularExpression('/name="audience" value="individual"\s+checked/', $tool);
        $this->assertDoesNotMatchRegularExpression('/<option value="S[^"]+" selected/', $tool, 'unknown codes select nothing');
        $this->assertStringContainsString('<option value="" selected>', $tool);

        $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/?situation[]=S06-2')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<option value="S[^"]+" selected/', $this->start($html), 'array values are ignored');
    }

    public function test_proposed_copy_exists_in_all_three_languages(): void
    {
        $flat = fn (array $a) => array_keys(\Illuminate\Support\Arr::dot($a));
        $en = require lang_path('en/proposed-d2/hero.php');
        $ar = require lang_path('ar/proposed-d2/hero.php');
        $ru = require lang_path('ru/proposed-d2/hero.php');
        $this->assertSame($flat($en), $flat($ar));
        $this->assertSame($flat($en), $flat($ru));
        foreach (\Illuminate\Support\Arr::dot($en) as $key => $value) {
            if ($key === 'pager_position') {
                continue;
            }
            $this->assertNotSame($value, \Illuminate\Support\Arr::get($ar, $key), "ar {$key} is translated");
            $this->assertNotSame($value, \Illuminate\Support\Arr::get($ru, $key), "ru {$key} is translated");
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{3,}/', preg_replace('/:[a-z]+/', '', (string) \Illuminate\Support\Arr::get($ar, $key)), "ar {$key} has no English");
        }
        $this->assertCount(3, explode('|', $ru['tool']['documents']), 'Russian: three plural forms');
        $this->assertCount(6, explode('|', $ar['tool']['documents']), 'Arabic: six plural forms');

        app()->setLocale('ru');
        $this->assertSame('Обычно нужно 5 документов', trans_choice('proposed.d2.hero.tool.documents', 5, ['count' => 5]));
        $this->assertSame('Обычно нужны 4 документа', trans_choice('proposed.d2.hero.tool.documents', 4, ['count' => 4]));
        app()->setLocale('ar');
        $this->assertSame('مستندان مطلوبان عادةً', trans_choice('proposed.d2.hero.tool.documents', 2, ['count' => 2]));
        $this->assertSame('4 مستندات مطلوبة عادةً', trans_choice('proposed.d2.hero.tool.documents', 4, ['count' => 4]));
    }

    public function test_markup_is_csp_safe_compliant_and_marks_proposed_copy(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->home($locale);
            $hero = $this->hero($html);
            $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $hero, "{$locale}: no style attributes (CSP)");
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/', $hero, "{$locale}: no inline handlers");
            foreach (['24/7', 'free consultation', 'law firm', 'years of experience', 'award', 'success rate', 'rated'] as $banned) {
                $this->assertStringNotContainsStringIgnoringCase($banned, strip_tags($hero), "{$locale}: {$banned}");
            }
            $this->assertStringContainsString('data-copy-status="proposed"', $hero);
            // The stage's geometry and callout positions: nonce'd style blocks in the head, never inline styles.
            $this->assertMatchesRegularExpression('/<style nonce="[^"]*">@layer components\{\.hero-doc--[a-z]+\{--s-wide:[\d.]+;--s-phone:[\d.]+;--dx-wide:-?[\d.]+px;--dx-phone:-?[\d.]+px\}/', $html);
            $this->assertMatchesRegularExpression('/<style nonce="[^"]*">@layer components\{\.hero-stage\[data-loc="'.$locale.'"\] \.hero-doc--[a-z]+ \.hero-callout\[data-callout="[a-z]+"\]\{--i-wide:/', $html);
        }
    }

    public function test_boot_script_arms_the_hero_with_a_safety_timeout(): void
    {
        $html = $this->home();
        $boot = $this->between($html, '<script nonce=', '</script>');
        $this->assertStringContainsString("h.classList.add('hero-armed')", $boot);
        $this->assertStringContainsString("h.getAttribute('data-page') === 'home'", $boot);
        $this->assertStringContainsString('w.__d2HeroArm = w.setTimeout(function () {', $boot);
        $this->assertMatchesRegularExpression("/h\.classList\.remove\('hero-armed'\);\s+if \(!h\.classList\.contains\('hero-live'\)\) \{ h\.classList\.add\('hero-static'\); \}\s+\}, 2500\)/", $boot, 'the safety timeout shows the static final state when the hero script never ran');
        $this->assertStringContainsString("h.classList.contains('motion-ok')", $boot, 'never armed under reduced motion');
        $this->assertStringContainsString('<html lang="en" dir="ltr" class="no-js" data-design="2" data-page="home"', $html);
    }

    /**
     * D16: every document's callouts are printed by the server in their final place (reduced motion and no JavaScript
     * show them at the first paint): per layout, a dot and a leader in the SVG, and the label (wide) / marker (phone).
     */
    public function test_final_callouts_of_the_document_on_top_are_server_rendered(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            app()->setLocale($locale);
            $hero = $this->hero($this->home($locale));
            $this->assertMatchesRegularExpression('/data-hero-stage\s+data-loc="'.$locale.'" data-active="cheque"/', $hero);
            foreach (Docs::TYPES as $type) {
                $doc = $this->between($hero, 'data-hero-doc="'.$type.'"', '<span class="hero-callout hero-callout--');
                foreach (HeroStage::callouts($type, $locale) as $c) {
                    // One leader per layout: straight, or with one short step outside the paper (a spread marker).
                    $this->assertSame(2, preg_match_all('/<path class="hero-line" d="M[\d.]+ [\d.]+H[\d.]+(V[\d.]+H[\d.]+)?" pathLength="1" data-callout="'.$c['id'].'">/', $doc), "{$locale} {$type} {$c['id']}: two leaders");
                    $this->assertSame(2, preg_match_all('/<circle class="hero-dot" cx="[\d.]+" cy="[\d.]+" r="4" data-callout="'.$c['id'].'">/', $doc), "{$locale} {$type} {$c['id']}: two dots");
                    foreach (HeroStage::LAYOUTS as $layout) {
                        // The leader starts at the dot and runs away from the document, to the label's side.
                        [$x, $y] = $c[$layout]['dot'];
                        $this->assertStringContainsString(sprintf('d="M%.1f %.1fH', $x, $y), $doc, "{$locale} {$type} {$c['id']} {$layout}: the leader starts at the dot");
                        preg_match('/^M[\d.]+ [\d.]+H([\d.]+)/', $c[$layout]['d'], $m);
                        $outwards = ($c['side'] === 'start') === ($locale !== 'ar') ? (float) $m[1] < $x : (float) $m[1] > $x;
                        $this->assertTrue($outwards, "{$locale} {$type} {$c['id']} {$layout}: the leader runs out of the paper");
                    }
                }
            }
            $cheque = $this->between($hero, 'data-hero-doc="cheque"', 'data-hero-doc="contract"');
            foreach (HeroStage::callouts('cheque', $locale) as $c) {
                $this->assertMatchesRegularExpression('/<span class="hero-callout hero-callout--'.$c['side'].' hero-callout--(first|last)" data-callout="'.$c['id'].'"><span class="hero-callout__num t-num">\d<\/span><span class="hero-callout__text" data-copy-status="proposed">/', $cheque, "{$locale}: label {$c['id']} (proposed copy)");
                $this->assertStringContainsString(e($c['label']), strip_tags($cheque, '<bdi>'), "{$locale}: label text {$c['id']}");
            }
        }
        app()->setLocale('en');
    }

    /** The measured fields (resources/js/d2/hero/callout-layout.json) belong to the current document copy. */
    public function test_callout_layout_table_matches_the_current_callouts(): void
    {
        $table = json_decode((string) file_get_contents(resource_path('js/d2/hero/callout-layout.json')), true);
        $this->assertIsArray($table);
        // The generator is committed (gitignored scratch folders would leave the next editor without it).
        $this->assertSame('scripts/d2/callout-layout.mjs', $table['generator'] ?? null);
        $this->assertFileExists(base_path($table['generator']));
        foreach (['en', 'ar', 'ru'] as $locale) {
            $this->assertSame(HeroStage::copyHash($locale), $table['hash'][$locale] ?? null, "{$locale}: the sample documents' copy changed; re-run node scripts/d2/callout-layout.mjs (needs the Design 2 dev server and a PHP server for this tree)");
            $this->assertNotNull(HeroStage::fields($locale));
            foreach (Docs::TYPES as $type) {
                foreach (Docs::callouts($type, $locale) as $c) {
                    $f = $table['fields'][$locale][$type][$c['id']] ?? null;
                    $this->assertIsArray($f, "{$locale} {$type} {$c['id']}: the field was measured");
                    $this->assertCount(4, $f);
                    [$w, $h] = Docs::SIZE[$type];
                    $this->assertGreaterThanOrEqual(0, min($f[0], $f[1]), "{$locale} {$type} {$c['id']}: inside the document");
                    $this->assertLessThanOrEqual($w + 1, $f[0] + $f[2], "{$locale} {$type} {$c['id']}: inside the document");
                    $this->assertLessThanOrEqual($h + 1, $f[1] + $f[3], "{$locale} {$type} {$c['id']}: inside the document");
                }
            }
        }
    }

    /** D16 geometry: dots beside their fields, labels in the margins beside the file, never over the paper, mirrored in RTL. */
    public function test_callouts_sit_in_the_margins_beside_the_file(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach (Docs::TYPES as $type) {
                $fields = HeroStage::fields($locale)[$type];
                foreach (HeroStage::LAYOUTS as $layout) {
                    [$bx, , $bw] = HeroStage::box($type, $layout);
                    [$ps, $pe] = HeroStage::pile($type, $layout);
                    $s = HeroStage::scale($type, $layout);
                    $bySide = ['start' => [], 'end' => []];
                    foreach (HeroStage::callouts($type, $locale) as $c) {
                        $g = $c[$layout];
                        $x = $locale === 'ar' ? HeroStage::W - $g['dot'][0] : $g['dot'][0];
                        [$fx, , $fw] = $fields[$c['id']];
                        // The dot sits outside its field, on the callout's side.
                        $c['side'] === 'start'
                            ? $this->assertLessThan($bx + $fx * $s, $x, "{$locale} {$type} {$c['id']} {$layout}: dot before the field")
                            : $this->assertGreaterThan($bx + ($fx + $fw) * $s, $x, "{$locale} {$type} {$c['id']} {$layout}: dot after the field");
                        // The label's facing edge is outside the pile (document and file), with room to the canvas edge.
                        $edge = $c['side'] === 'start' ? HeroStage::W - $g['inset'] : $g['inset'];
                        $c['side'] === 'start'
                            ? $this->assertLessThanOrEqual($ps - 10 + 0.1, $edge, "{$locale} {$type} {$c['id']} {$layout}: label beside the file")
                            : $this->assertGreaterThanOrEqual($pe + 10 - 0.1, $edge, "{$locale} {$type} {$c['id']} {$layout}: label beside the file");
                        $this->assertGreaterThan($layout === 'wide' ? 90 : 40, $g['room'], "{$locale} {$type} {$c['id']} {$layout}: room for the label");
                        $bySide[$c['side']][] = $g['y'];
                    }
                    if ($layout === 'phone') {
                        foreach ($bySide as $ys) {
                            sort($ys);
                            for ($i = 1; $i < count($ys); $i++) {
                                $this->assertGreaterThanOrEqual(HeroStage::PHONE_SPACING - 0.2, $ys[$i] - $ys[$i - 1], "{$locale} {$type}: markers 12 px apart");
                            }
                        }
                    }
                }
            }
        }
    }

    public function test_a_stale_layout_is_ignored_and_the_callouts_fall_back_to_the_anchors(): void
    {
        app('translator')->addLines(['proposed.d2.docs.cheque.bank' => 'A changed bank'], 'en');
        $this->assertNull(HeroStage::fields('en'), 'measured with other copy: ignored');
        $hero = $this->hero($this->home('en'));
        $this->assertSame(18, substr_count($hero, '<span class="hero-callout hero-callout--'), 'the callouts still render (at the rough anchors)');
    }

    /**
     * D06: the hero's request is the page's one Gold button (flat, the kit's 52 px hero size); the WhatsApp button
     * is the secondary one beside it; every other button of the hero area is Ink or Secondary. The metal anchor of
     * Design 2.0 is gone (the header CTA is always Ink: the shell's contract).
     */
    public function test_the_hero_primary_is_the_one_gold_button(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->home($locale);
            $hero = $this->hero($html);
            $this->assertSame(1, preg_match_all('/class="btn btn--(gold|signature)\b/', $hero), "{$locale}: one Gold button in the hero");
            $this->assertMatchesRegularExpression('/<a class="btn btn--gold btn--52 hero__cta" href="[^"]+#consultation-form"/', $hero);
            $this->assertMatchesRegularExpression('/<a class="btn btn--whatsapp btn--52 hero__cta" href="'.preg_quote(e(setting('whatsapp_url')), '/').'"/', $hero);
            $this->assertStringNotContainsString('data-metal-anchor', $hero);
            // The home sections of this area (Start your matter to the Navigator) and the Navigator's sheet hold no other gold.
            $start = strpos($html, '<section class="section start"');
            $end = strpos($html, '</section>', strpos($html, 'data-navigator-config'));
            $this->assertNotFalse($start);
            $this->assertNotFalse($end);
            $this->assertDoesNotMatchRegularExpression('/btn--(gold|signature)\b/', substr($html, $start, $end - $start), "{$locale}: no other gold in start, services, anatomy, who, review, Navigator");
            $this->assertDoesNotMatchRegularExpression('/btn--(gold|signature)\b/', $this->between($html, 'data-nav-sheet-actions>', '</a>'), "{$locale}: the sheet's Request is Ink");
        }
    }

    /** The stage is one illustration (Design 2.1 brief §7.1): its documents, sheets, dots, leaders and labels are exempt from the interface lint. */
    public function test_the_stage_and_its_documents_are_marked_as_illustrations(): void
    {
        $hero = $this->hero($this->home());
        $this->assertMatchesRegularExpression('/<div class="hero-stage" role="img"[^>]*\sdata-illustration[\s>]/', $hero);
        $this->assertGreaterThanOrEqual(6, substr_count($hero, ' data-doc="'));
        $this->assertSame(substr_count($hero, ' data-doc="'), substr_count($hero, "' data-illustration>"), 'every document root carries data-illustration');
        $css = (string) file_get_contents(resource_path('css/d2/components/stage.css'));
        // Outside the illustration nothing glows: the legend's numbers (.hero-notes) have no halo.
        $notes = substr($css, strpos($css, '.hero-notes__item {'), 900);
        $this->assertStringNotContainsString('box-shadow', $notes);
        // The styleguide's specimens of the stage's own parts and of a document's watermark are illustrations too.
        foreach (['en', 'ar', 'ru'] as $locale) {
            $sg = $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/styleguide/")->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<div class="hero-parts" aria-hidden="true" data-illustration>/', $sg, "{$locale}: hero parts");
            $this->assertSame(2, preg_match_all('/<div class="sg-wm(?: sg-wm--min)? doc" aria-hidden="true" data-illustration>/', $sg), "{$locale}: both watermark specimens");
        }
    }

    /** One Tab stop for the pager: a roving tabindex on the segments; previous / next out of the tab order. */
    public function test_pager_is_one_tab_stop(): void
    {
        $pager = $this->between($this->hero($this->home()), 'data-hero-pager', 'hero-visual__caption');
        preg_match_all('/<button type="button" class="hero-pager__seg"\s+data-pager-seg="([a-z]+)" tabindex="(-?\d)"/', $pager, $segs);
        $this->assertSame(['cheque', 'contract', 'tenancy', 'transfer', 'messages', 'notice'], $segs[1]);
        $this->assertSame(['0', '-1', '-1', '-1', '-1', '-1'], $segs[2], 'only the document shown is in the tab order');
        $this->assertSame(2, preg_match_all('/data-pager-step="-?1" tabindex="-1"/', $pager), 'previous and next are not Tab stops (the arrow keys do the same)');
    }

    /**
     * Design 2.1 round 2 (brief §3.2): the hero's two approved paragraphs (its lead text) and its hours line break
     * between words only. The Russian paragraph rule split the country's name in the first sentence ("Объеди-/нённых
     * Арабских Эмиратах", 768 to 1440 px); Russian running text elsewhere keeps hyphens: auto.
     */
    public function test_the_hero_text_breaks_between_words_only(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/sections/hero.css'));
        $this->assertMatchesRegularExpression('/\.hero__text > p \{\s*hyphens: manual;\s*\}/', $css, 'the two approved paragraphs');
        $base = (string) file_get_contents(resource_path('css/d2/base/base.css'));
        $this->assertMatchesRegularExpression('/:lang\(ru\) p \{\s*hyphens: auto;/', $base, 'Russian running text still hyphenates');
        foreach (['en', 'ar', 'ru'] as $locale) {
            $text = $this->between($this->hero($this->home($locale)), '<div class="hero__text">', '</div>');
            $this->assertSame(2, substr_count($text, '<p>'), "{$locale}: the rule reaches the two approved paragraphs");
            $this->assertStringNotContainsString("\u{00AD}", $text, "{$locale}: no soft hyphen either (hyphens: manual would honour one)");
        }
    }

    // Design 2.1: the footer no longer carries the live office-hours status (brief §3.7: only in the contact block);
    // its hours sentence and the absence of a status slot are tested in FooterTest (owner: footer-shell).

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, "found {$from}");
        $end = strpos($html, $to, $start + strlen($from));
        $this->assertNotFalse($end, "found {$to} after {$from}");

        return substr($html, $start, $end - $start + strlen($to));
    }
}
