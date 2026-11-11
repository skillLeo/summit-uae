<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Cover;
use App\Design\D2\Prefill;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2 home: About (#about), the "Our Team" block (#our-team, off by default, C8), case studies
 * (#case-studies, C9: the two approved text previews, D25) and legal guides (#guides, C10), built from
 * <x-d2.team.profile> / <x-d2.team.portrait> (D42-D44), <x-d2.case.preview> and <x-d2.guide.card> (owner: cards). Rendered through the real home page with the Design 2 cookie, in
 * all three languages, against the approved imported content.
 */
final class CardsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function home(string $locale = 'en'): string
    {
        app()->setLocale($locale);

        return $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/")->assertOk()->getContent();
    }

    /** The home with the "Our Team" block switched on (it is off by default: D24). */
    private function homeWithTeam(string $locale = 'en'): string
    {
        config(['design.d2.home.blocks.team.enabled' => true]);

        return $this->home($locale);
    }

    private function section(string $html, string $id): string
    {
        $this->assertSame(1, preg_match('/<section class="section[^"]*" id="'.preg_quote($id, '/').'".*?<\/section>/s', $html, $m), "section #{$id} renders");

        return $m[0];
    }

    /** Visible text: tags removed, entities decoded, whitespace collapsed. */
    private function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    /** @return list<string> the <article> cards of a section */
    private function cards(string $section, string $class): array
    {
        preg_match_all('/<article class="[^"]*\b'.$class.'\b[^"]*".*?<\/article>/s', $section, $m);

        return $m[0];
    }

    public function test_the_three_sections_keep_their_anchors_order_and_headings(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->home($locale);
            $c = content()->page('P01')['content'];
            $about = strpos($html, 'id="about"');
            $cases = strpos($html, 'id="case-studies"');
            $guides = strpos($html, 'id="guides"');
            $this->assertTrue($about !== false && $about < $cases && $cases < $guides, "{$locale}: team, cases, guides in spec order");

            foreach (['about' => $c['about']['heading'], 'case-studies' => $c['cases']['heading'], 'guides' => $c['guides']['heading']] as $id => $heading) {
                $section = $this->section($html, $id);
                $this->assertSame(1, substr_count($section, '<h2'), "{$locale} #{$id}: one H2");
                $this->assertMatchesRegularExpression('/<section class="section[^"]*" id="'.$id.'" aria-labelledby="([^"]+)">/', $section);
                preg_match('/aria-labelledby="([^"]+)"/', $section, $label);
                $this->assertMatchesRegularExpression('/<h2 id="'.preg_quote($label[1], '/').'">/', $section, "{$locale} #{$id}: the H2 names the section");
                $this->assertStringContainsString($heading, $this->text($section), "{$locale} #{$id}: approved heading");
                $this->assertStringNotContainsString('<h1', $section, 'the hero owns the only H1');
            }
        }
    }

    public function test_about_shows_the_approved_heading_paragraphs_and_link_without_the_team(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->home($locale);
            $section = $this->section($html, 'about');
            $text = $this->text($section);
            foreach (content()->page('P01')['content']['about']['paragraphs'] as $paragraph) {
                $this->assertStringContainsString($paragraph, $text, "{$locale}: approved About paragraph");
            }
            $this->assertStringContainsString('href="'.SiteUrl::page('P06').'"', $section, 'the approved "Meet Summit and the Team" link');
            $this->assertStringContainsString(ui('cta.meet_team'), $text);
            // D24: the team grid is a separate block, off by default (the team stays on About).
            $this->assertStringNotContainsString('id="our-team"', $html, "{$locale}: no team grid on the home by default");
            $this->assertStringNotContainsString('class="profile', $section, "{$locale}: no profile cards in About");
        }
    }

    public function test_the_team_block_follows_about_with_the_approved_heading_when_switched_on(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->homeWithTeam($locale);
            $section = $this->section($html, 'our-team');
            $this->assertTrue(strpos($html, 'id="about"') < strpos($html, 'id="our-team"') && strpos($html, 'id="our-team"') < strpos($html, 'id="case-studies"'), "{$locale}: About, then the team block, then the case studies");
            $this->assertStringContainsString(content()->page('P06')['content']['team']['heading'], $this->text($section), "{$locale}: approved Our Team heading");
            $this->assertSame(1, substr_count($section, '<h2'), "{$locale}: one H2");
        }
    }

    public function test_profile_cards_show_approved_names_titles_portraits_and_the_pending_panel(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->homeWithTeam($locale);
            $section = $this->section($html, 'our-team');
            // D42: one nonce'd <style> per page places every photo on the shared eye line (no style attributes).
            $this->assertSame(1, preg_match_all('/<style nonce="[^"]*" data-portraits>[^<]*--portrait-scale:[^<]*<\/style>/', $html), "{$locale}: the portrait rules once");
            $cards = $this->cards($section, 'profile');
            $members = content()->teamMembers();
            $this->assertCount(count($members), $cards, "{$locale}: one profile card per team member");

            foreach ($members as $i => $member) {
                $card = $cards[$i];
                $text = $this->text($card);
                $this->assertStringContainsString($member['name'], $text, "{$locale}: approved name");
                $this->assertStringContainsString($member['role'], $text, "{$locale}: approved title");
                $this->assertMatchesRegularExpression('/<h3 class="card__title">\s*<a class="card__link" href="'.preg_quote(SiteUrl::target($member['code']), '/').'">/', $card, 'the name opens the About profile');
                // No credential line: the approved focus line opens with a degree for one member.
                $this->assertStringNotContainsString((string) $member['focus'], $text);
                foreach (['lawyer', 'advocate', 'licensed', 'law firm', 'years of experience', 'محامي', 'адвокат', 'юрист'] as $word) {
                    $this->assertStringNotContainsStringIgnoringCase($word, $text, "{$locale}: no {$word} on a profile card");
                }

                if ($member['portrait']) {
                    $this->assertMatchesRegularExpression('/<div class="portrait portrait--photo" data-member="'.$member['code'].'"\s*>/', $card, "{$locale}: the frame names its member (D42)");
                    $this->assertMatchesRegularExpression('/<source type="image\/avif" srcset="\/images\/d2\/team\/'.$member['portrait'].'-480\.avif 480w, \/images\/d2\/team\/'.$member['portrait'].'-800\.avif 800w"/', $card);
                    $this->assertMatchesRegularExpression('/<img class="portrait__img" src="\/images\/d2\/team\/'.$member['portrait'].'-480\.webp"[^>]*width="480" height="600" alt="'.preg_quote(e($member['alt']), '/').'"[^>]*loading="lazy"/', $card, "{$locale}: explicit size, approved alt, lazy");
                    $this->assertFileExists(public_path('images/d2/team/'.$member['portrait'].'-800.avif'));
                } else {
                    // D43: the photo is pending: a neutral panel of the same frame, never a placeholder photo.
                    $this->assertMatchesRegularExpression('/<div class="portrait portrait--pending" data-member="'.$member['code'].'"\s+data-photo-pending="true"\s*>/', $card);
                    $this->assertStringNotContainsString('<img class="portrait__img"', $card);
                    $this->assertMatchesRegularExpression('/<div class="portrait__panel" aria-hidden="true">/', $card);
                }
            }
        }
    }

    public function test_the_pending_panel_shows_the_initials_of_the_approved_name_in_the_sans(): void
    {
        foreach (['en' => ['S', 'Z'], 'ar' => ['س', 'ز'], 'ru' => ['С', 'З']] as $locale => $letters) {
            $section = $this->section($this->homeWithTeam($locale), 'our-team');
            preg_match('/<span class="portrait__initials">(.*?)<\/span>\s*<\/div>/s', $section, $m);
            preg_match_all('/<span class="portrait__initial">(.+?)<\/span>/u', $m[1] ?? '', $initials);
            $this->assertSame($letters, $initials[1], "{$locale}: initials of Ms Sarah Zaki");
        }
        // D43: the interface sans in a neutral panel: no serif, no ring, no bronze.
        $css = (string) file_get_contents(resource_path('css/d2/components/profile.css'));
        $this->assertSame(1, preg_match('/\.portrait__initials \{(.*?)\}/s', $css, $rule));
        $this->assertStringContainsString('font-family: var(--font-sans);', $rule[1]);
        $this->assertStringNotContainsString('border', $rule[1]);
        $this->assertStringNotContainsString('bronze', $rule[1]);
        $this->assertStringNotContainsString('monogram', $css);
    }

    public function test_profile_chips_and_the_request_button(): void
    {
        foreach (self::LOCALES as $locale) {
            $section = $this->section($this->homeWithTeam($locale), 'our-team');
            foreach (content()->teamMembers() as $i => $member) {
                $card = $this->cards($section, 'profile')[$i];
                $focus = trans('proposed.d2.team.focus.'.$member['code']);
                $this->assertIsArray($focus, "{$locale}: proposed focus chips for {$member['code']}");
                $this->assertMatchesRegularExpression('/<ul class="profile__list" role="list"\s+aria-label="'.preg_quote(e($member['areas']['heading']), '/').'"\s+data-copy-status="proposed">/', $card, 'focus chips: proposed, named by the approved areas heading');
                // D44: at most three chips, all in the one chip style.
                $this->assertLessThanOrEqual(3, substr_count($card, 'class="chip '), "{$locale}: at most three chips");
                foreach (array_slice($focus, 0, 3) as $chip) {
                    $this->assertStringContainsString('<span class="chip__label">'.e($chip).'</span>', $card);
                }
                $this->assertStringNotContainsString('chip--choice', $card, "{$locale}: one chip style");

                // D44: the approved "Working Languages: Arabic · English · Russian" as plain text, exactly as written.
                $this->assertSame(1, preg_match('/<p class="profile__languages">(.*?)<\/p>/s', $card, $languages));
                $this->assertSame($member['languages'], $this->text($languages[1]), "{$locale}: the approved languages line verbatim");
                [$label, $items] = array_map('trim', explode(':', $member['languages'], 2));
                $this->assertStringContainsString('<span class="profile__languages-items">'.e($items).'</span>', $languages[1]);
                foreach (preg_split('/\s*·\s*/u', $items) as $language) {
                    $this->assertStringNotContainsString('<span class="chip__label">'.e($language).'</span>', $card, "{$locale}: no language chip {$language}");
                }

                // The request button: a real link to the wizard (works without JavaScript) + d2TeamCta.
                $url = Prefill::url(['consultant' => $member['code']]);
                $this->assertStringContainsString('href="'.e($url).'"', $card);
                $this->assertMatchesRegularExpression('/x-data="d2TeamCta"\s+x-on:click="request"\s+data-consultant="'.$member['code'].'"\s+data-copy-status="proposed"/', $card);
                $this->assertStringContainsString(e(d2p('team.cta.'.$member['code'])), $card);
                // Round 1 (brief 3.5): a Text button on the card's text column, never a full-width box (a boxed
                // button wrapped "…with Dr Mohamed" into a taller box than its neighbours').
                $this->assertMatchesRegularExpression('/<a class="btn btn--text btn--48" href="'.preg_quote(e($url), '/').'"/', $card, "{$locale}: {$member['code']} request link is a Text button");
                $this->assertStringNotContainsString('btn--full', $card);
            }
        }
        $css = (string) file_get_contents(resource_path('css/d2/components/profile.css'));
        $this->assertMatchesRegularExpression('/\.profile > \.card__footer \.btn \{\s*justify-content: flex-start;\s*min-block-size: 0;\s*max-inline-size: 100%;\s*text-align: start;\s*\}/', $css, 'the request link starts on the text column and hugs its label');
        $this->assertMatchesRegularExpression('/\.team__item \.profile > \.card__footer \{\s*align-items: flex-start;/', (string) file_get_contents(resource_path('css/d2/sections/team.css')), 'the links of a row start on one line');

        // The prefill link is accepted by the wizard's validation.
        $this->withUnencryptedCookies(['summit_design' => '2'])->get('/en/?consultant=team-sarah')->assertOk();
        $this->assertSame(['consultant' => 'team-sarah'], Prefill::fromRequest());
    }

    /**
     * D25: the homepage DOCX's two text previews, in its order (the cheque story, then the cryptocurrency story), each
     * with its approved title, paragraph and link label, linking to its case anchor on the Case Studies page; then
     * "View All Case Studies". No numbers, icons, chips, timelines or badges.
     */
    public function test_two_text_previews_link_to_their_case_anchors(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->home($locale);
            $section = $this->section($html, 'case-studies');
            $c = content()->page('P01')['content'];
            $this->assertStringContainsString($c['cases']['intro'], $this->text($section), "{$locale}: approved intro");
            $cards = $this->cards($section, 'case-preview');
            $this->assertCount(2, $cards, "{$locale}: two text previews");

            foreach (['case-01', 'case-04'] as $i => $code) {
                $case = content()->caseStudy($code);
                $card = $cards[$i];
                $text = $this->text($card);
                $this->assertStringContainsString('id="preview-'.$code.'"', $card, 'Design 1 home anchor');
                $this->assertMatchesRegularExpression('/<h3 class="card__title">\s*<a class="card__link" href="'.preg_quote(SiteUrl::caseStudy($code), '/').'">/', $card, "{$locale}: the title opens the story's anchor");
                $this->assertStringEndsWith('#'.$code, SiteUrl::caseStudy($code));
                $this->assertSame(1, substr_count($card, '<a '), "{$locale}: one link per card");
                foreach (['home_title', 'home_summary', 'home_link_label'] as $field) {
                    $this->assertNotSame('', trim((string) $case[$field]));
                    $this->assertStringContainsString($case[$field], $text, "{$locale}: approved {$field} of {$code}");
                }
                $this->assertMatchesRegularExpression('/<span class="case-preview__cta" aria-hidden="true" data-card-link>/', $card);
                foreach (['case-card__no', 'timeline', 'badge', 'chip', 'data-icon', $case['matter']] as $gone) {
                    $this->assertStringNotContainsString($gone, $card, "{$locale}: no {$gone} on a preview");
                }
                $this->assertDoesNotMatchRegularExpression('/>\s*0\d\s*</', $card, "{$locale}: no decorative number");
            }
            $this->assertStringNotContainsString('case-card', $section, "{$locale}: no directory cards on the home");
            $this->assertSame(1, substr_count($this->text($section), $this->qualification($c['cases']['intro'])), "{$locale}: the qualification is in the intro only, not repeated per card");
            $this->assertStringContainsString('href="'.SiteUrl::page('P04').'"', $section, 'View All Case Studies');
            $this->assertStringContainsString(ui('cta.view_all_case_studies'), $this->text($section));
            $this->assertLessThan(strpos($section, 'href="'.SiteUrl::page('P04').'"'), strpos($section, 'id="preview-case-04"'), "{$locale}: the previews, then View All Case Studies");
        }
    }

    public function test_case_cards_state_no_outcome_or_banned_claim(): void
    {
        foreach (self::LOCALES as $locale) {
            $text = $this->text($this->section($this->home($locale), 'case-studies'));
            foreach (['success', 'won', 'win ', 'acquitt', 'guaranteed result', '%', '24/7', 'free consultation', 'law firm'] as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $text, "{$locale}: no '{$word}' in the case cards");
            }
        }
    }

    public function test_arabic_latin_runs_are_isolated_on_the_cards(): void
    {
        $section = $this->section($this->home('ar'), 'case-studies');
        $this->assertStringContainsString('<bdi dir="ltr">USDT</bdi>', $section, 'case-04 title keeps USDT left-to-right');
    }

    public function test_nine_guide_cards_link_to_the_knowledge_centre_with_design_one_ids(): void
    {
        foreach (self::LOCALES as $locale) {
            $section = $this->section($this->home($locale), 'guides');
            $c = content()->page('P01')['content'];
            $this->assertStringContainsString($c['guides']['intro'], $this->text($section));
            $cards = $this->cards($section, 'guide-card');
            $this->assertCount(9, $cards, "{$locale}: the nine approved guide cards");

            $order = collect(content()->guideCategories())->flatMap(fn ($g) => $g['guides'])->values()->all();
            $names = collect(content()->guideCategories())->mapWithKeys(fn ($g) => [$g['code'] => $g['name']]);
            foreach ($order as $i => $code) {
                $guide = content()->guide($code);
                $card = $cards[$i];
                $text = $this->text($card);
                $id = 'guide-'.strtolower($code);
                $this->assertMatchesRegularExpression('/<a class="card__link" href="'.preg_quote(SiteUrl::guide($code), '/').'">\s*<span id="'.$id.'-title">/', $card, "{$locale}: {$code} opens its guide; Design 1 title id kept");
                $this->assertStringContainsString('id="'.$id.'-cta"', $card, 'Design 1 cta id kept');
                $this->assertStringContainsString($guide['home_title'], $text);
                $this->assertStringContainsString($guide['home_summary'], $text);
                $this->assertStringContainsString($names[$guide['category']], $text, 'approved category');
                $this->assertStringContainsString(ui('cta.read_guide'), $text);
                // D27: a text card. Category, title, summary and "Read the Guide" (the visible label of the card's one
                // link, not a second link); no generated cover, no image area without a real cover, no reading time.
                $this->assertSame(1, substr_count($card, '<a '), "{$locale}: {$code} is one link");
                $this->assertStringContainsString('<span class="guide-card__read" id="'.$id.'-cta" aria-hidden="true" data-card-link>'.e(ui('cta.read_guide')).'</span>', $card);
                $this->assertStringNotContainsString('card__media', $card, "{$locale}: {$code} has no image area");
                $this->assertStringNotContainsString('<svg', $card, "{$locale}: {$code} has no generated cover");
                $this->assertStringNotContainsString('data-cover', $card);
                $this->assertStringNotContainsString('guide-card__time', $card, "{$locale}: {$code} has no reading time");
                $this->assertStringNotContainsString('<bdi>', $card);
                // Order on the card: the title (the heading first in the source), then the category, the summary, the label.
                $this->assertLessThan(strpos($card, 'guide-card__category'), strpos($card, 'card__title'));
                $this->assertLessThan(strpos($card, 'guide-card__summary'), strpos($card, 'guide-card__category'));
                $this->assertLessThan(strpos($card, 'guide-card__read'), strpos($card, 'guide-card__summary'));
            }
            // The former proposed reading time: "N min read", "N минуты чтения", «مدة القراءة: N دقائق».
            $this->assertDoesNotMatchRegularExpression('/min read|мину\w* чтения|مدة القراءة/u', $this->text($section), "{$locale}: no reading time");
            $this->assertStringContainsString('href="'.SiteUrl::page('P03').'"', $section);
            foreach (content()->guideCategories() as $group) {
                // Design 1's category anchor sits on the first card of the category.
                $first = $group['guides'][0];
                $this->assertMatchesRegularExpression('/<li class="guides__item"\s+id="gc-'.$group['code'].'"\s*>\s*<div class="guide-frame">\s*<article[^>]*data-guide="'.$first.'"/', $section, "{$locale}: #gc-{$group['code']}");
            }
        }
    }

    public function test_guide_cards_show_an_image_area_only_for_a_real_cover(): void
    {
        // D27: the hook for a cover the admin uploads (Phase 4). None exists, so no card has an image area.
        app()->setLocale('en');
        $guide = content()->guide('G01');
        $this->assertNull(Cover::image($guide), 'no guide has a cover today');
        foreach (content()->guides() as $g) {
            $this->assertNull(Cover::image($g), "{$g['code']}: no cover");
        }
        $this->assertNull(Cover::image(['cover' => 'javascript:alert(1)'] + $guide), 'only paths and http(s) URLs');
        $this->assertNull(Cover::image(['cover' => '//evil.test/x.webp'] + $guide), 'no protocol-relative URL');
        $this->assertNull(Cover::image(['cover' => ['alt' => 'x']] + $guide));
        $this->assertSame(['src' => '/storage/covers/g01.webp', 'alt' => '', 'width' => null, 'height' => null], Cover::image(['cover' => '/storage/covers/g01.webp'] + $guide));
        $this->assertSame(
            ['src' => 'https://cdn.example.test/g01.webp', 'alt' => 'A payslip', 'width' => 800, 'height' => 400],
            Cover::image(['cover' => ['src' => 'https://cdn.example.test/g01.webp', 'alt' => 'A payslip', 'width' => '800', 'height' => 400]] + $guide),
        );

        $plain = (string) $this->blade('<x-d2.guide.card :guide="$guide" />', ['guide' => $guide]);
        $this->assertStringNotContainsString('card__media', $plain);
        $this->assertStringNotContainsString('<img', $plain);
        $this->assertStringNotContainsString('guide-card--cover', $plain);

        $covered = (string) $this->blade('<x-d2.guide.card :guide="$guide" />', ['guide' => ['cover' => ['src' => '/storage/covers/g01.webp', 'width' => 800, 'height' => 400]] + $guide]);
        $this->assertMatchesRegularExpression('/<article class="[^"]*\bguide-card guide-card--cover\b/', $covered);
        $this->assertMatchesRegularExpression('/<div class="card__media guide-card__media">\s*<img class="guide-cover" src="\/storage\/covers\/g01.webp" alt=""\s+width="800"\s+height="400"\s+loading="lazy" decoding="async">/', $covered);

        // The generated covers and the reading time are gone (brief §3.6 rules 9 and 10).
        $this->assertFalse(method_exists(Cover::class, 'svg'));
        $this->assertFalse(method_exists(Cover::class, 'readingMinutes'));
        $this->assertSame('S06', Cover::serviceFor($guide), 'the Knowledge Centre still prefills the guide\'s practice area');
        foreach (self::LOCALES as $locale) {
            $this->assertFileDoesNotExist(lang_path("{$locale}/proposed-d2/guides.php"));
            $this->assertSame('proposed.d2.guides.reading_time', __('proposed.d2.guides.reading_time', [], $locale));
        }
        $css = (string) file_get_contents(resource_path('css/d2/sections/guides.css'));
        foreach (['.cover--', '.cover__', 'guide-card__time', 'container: guide'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, "guides.css: no {$gone}");
        }
        // Rule 12: the cards of a row share their four row tracks (category, title, summary, label).
        $this->assertMatchesRegularExpression('/\.guides__item,\s*\.guides__item > \.guide-frame,\s*\.guides__item \.guide-card \{\s*display: grid;\s*grid-row: span 4;\s*grid-template-rows: subgrid;/', $css);
    }

    /**
     * Design 2.1, Phase 1 (brief §3.6; D03, D04, D05): the cards carry no decorative icon (no service icon in a
     * chip, no shield on the qualification badge, no globe before the languages, no clock before the reading
     * time), no arrow on a button, link or card corner, and the legacy hover hooks that moved a card are gone.
     * The only icons left in the three sections are the phone rails' previous and next buttons.
     */
    public function test_design_2_1_cards_have_no_decorative_icons_or_arrows(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->home($locale);
            foreach (['about', 'case-studies', 'guides'] as $id) {
                $section = $this->section($html, $id);
                preg_match_all('/data-icon="([^"]+)"/', $section, $m);
                $this->assertSame([], array_values(array_diff(array_unique($m[1]), ['carousel-prev', 'carousel-next', 'chevron', 'chevron-start'])), "{$locale} #{$id}: only the rail's previous and next");
                $this->assertDoesNotMatchRegularExpression('/<(a|button)\b[^>]*class="btn\b[^"]*"[^>]*>(?:(?!<\/\1>).)*btn__icon--end/s', $section, "{$locale} #{$id}: no button ends with an icon");
                $this->assertStringNotContainsString('chip__icon', $section, "{$locale} #{$id}: chips are text only");
                $this->assertStringNotContainsString('badge__icon', $section, "{$locale} #{$id}: no icon on the badge");
            }
            $team = $this->section($this->homeWithTeam($locale), 'our-team');
            $this->assertStringNotContainsString('profile__languages-icon', $team);
            $guides = $this->section($html, 'guides');
            $this->assertStringNotContainsString('guide-card__time', $guides, "{$locale}: no reading time (D27)");
            $this->assertMatchesRegularExpression('/<span class="guide-card__read" id="guide-g01-cta" aria-hidden="true" data-card-link>[^<]+<\/span>/', $guides, "{$locale}: Read the Guide has no arrow");
        }
    }

    public function test_sections_have_no_style_attributes_or_duplicate_ids(): void
    {
        foreach (self::LOCALES as $locale) {
            $html = $this->home($locale);
            foreach (['about', 'case-studies', 'guides'] as $id) {
                $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $this->section($html, $id), "{$locale} #{$id}: no style attributes (CSP)");
            }
            preg_match_all('/\sid="([^"]+)"/', $html, $ids);
            $this->assertSame([], array_keys(array_filter(array_count_values($ids[1]), fn ($n) => $n > 1)), "{$locale}: no duplicate ids on the home page");
        }
    }

    public function test_proposed_keys_exist_in_all_three_languages(): void
    {
        $keys = ['team.cta.team-ahmed', 'team.cta.team-mohamed', 'team.cta.team-sarah', 'team.cta_generic'];
        foreach (self::LOCALES as $locale) {
            foreach ($keys as $key) {
                $value = __('proposed.d2.'.$key, [], $locale);
                $this->assertIsString($value);
                $this->assertNotSame('proposed.d2.'.$key, $value, "{$locale}: {$key}");
                if ($locale !== 'en') {
                    $this->assertNotSame(__('proposed.d2.'.$key, [], 'en'), $value, "{$locale}: {$key} is translated");
                }
            }
            foreach (['team-ahmed', 'team-mohamed', 'team-sarah'] as $code) {
                $chips = trans('proposed.d2.team.focus.'.$code, [], $locale);
                $this->assertIsArray($chips);
                $this->assertCount(3, $chips, "{$locale}: three focus chips for {$code} (D44: at most three)");
                if ($locale !== 'en') {
                    $this->assertNotSame(trans('proposed.d2.team.focus.'.$code, [], 'en'), $chips);
                }
            }
        }
        $this->assertSame(1, preg_match('/[\x{0600}-\x{06FF}]/u', __('proposed.d2.team.cta.team-ahmed', [], 'ar')), 'Arabic draft');
        $this->assertSame(1, preg_match('/[\x{0400}-\x{04FF}]/u', __('proposed.d2.team.cta.team-ahmed', [], 'ru')), 'Russian draft');
    }

    public function test_the_styleguide_partial_shows_every_card_state_without_repeating_ids(): void
    {
        // The partial alone (the page also renders the other areas' partials, owned elsewhere).
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $html = view('d2::styleguide.cards')->render();
            $this->assertStringContainsString('<section class="sg-section sg-cards" id="sg-cards">', $html);
            $this->assertSame($locale, app()->getLocale(), "{$locale}: the other-direction specimens restore the locale");
            foreach (['is-hover', 'is-focus', 'portrait--pending', 'case-preview', 'case-card__category', 'data-sg="guide-states"', 'data-sg="guide-row"'] as $state) {
                $this->assertStringContainsString($state, $html, "{$locale}: styleguide shows {$state}");
            }
            $other = $locale === 'ar' ? 'en' : 'ar';
            $this->assertStringContainsString('dir="'.($other === 'ar' ? 'rtl' : 'ltr').'" lang="'.$other.'"', $html, 'a specimen in the other direction');
            $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $html);
            preg_match_all('/\sid="([^"]+)"/', $html, $ids);
            $this->assertSame([], array_keys(array_filter(array_count_values($ids[1]), fn ($n) => $n > 1)), "{$locale}: specimens repeat no id");
            foreach (['preview-case-01', 'guide-g01-title', 'about'] as $homeId) {
                $this->assertNotContains($homeId, $ids[1], 'no id of the home page');
            }
        }
    }

    private function qualification(string $intro): string
    {
        preg_match('/^.+?[.!?؟](?=\s)/u', $intro, $first);

        return trim(mb_substr($intro, mb_strlen($first[0] ?? '')));
    }
}
