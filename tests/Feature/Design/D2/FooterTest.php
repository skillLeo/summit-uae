<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Hours;
use App\Support\SiteUrl;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Design 2.1 footer (owner: footer-shell). Brief §3.3 (text on dark), §3.6 (banned patterns), §3.8 (the lockup and
 * the WhatsApp mark), §5.1 item 11 and §5.2, fix D47 (with D01, D02, D10, D12), and the owner decisions of Phase 1:
 * the footer's hours sentence is the homepage file's own line (the homepage DOCX defines the shared footer), and
 * the specialist resources group is the last group inside the footer, directly above the copyright line.
 * The order and wording come from 01_Homepage (FOOTER): the brand H2 and description, Practice Areas (S01–S11),
 * Legal Resources (Legal Resources Centre, FAQs, Case Studies), Useful Links, the disclaimer, the copyright line.
 */
final class FooterTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'ar', 'ru'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function d2(string $path, int $status = 200): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get($path)->assertStatus($status)->getContent();
    }

    private function footer(string $html): string
    {
        $this->assertSame(1, preg_match_all('/<footer class="d2-footer on-dark"[^>]*>.*?<\/footer>/s', $html, $m), 'one site footer');

        return $m[0][0];
    }

    private function text(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    /** @return list<string> the href of every footer link of one group, in order */
    private function hrefs(string $footer, string $group): array
    {
        $this->assertSame(1, preg_match('/<nav class="d2-footer__col d2-footer__group d2-footer__group--'.$group.'"[^>]*>(.*?)<\/nav>/s', $footer, $m), "the {$group} group");
        preg_match_all('/<a class="d2-footer__link" href="([^"]+)">(.*?)<\/a>/s', $m[1], $links);

        return array_map(fn ($href, $label) => $href.' '.$this->text($label), $links[1], $links[2]);
    }

    public function test_the_footer_is_one_dark_band_on_the_shared_container(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $footer = $this->footer($this->d2("/{$locale}/"));

            // .on-dark (dark band, light-gold focus ring) and the page container, so its edges are the header's.
            $this->assertStringStartsWith('<footer class="d2-footer on-dark" aria-labelledby="d2-footer-title">', $footer);
            $this->assertMatchesRegularExpression('/^<footer[^>]*>\s*<div class="container">/', $footer);
            $this->assertStringNotContainsString('on-night', $footer);
            // The approved H2 (visually the official lockup, which carries the brand name as its link name).
            $this->assertStringContainsString('<h2 class="sr-only" id="d2-footer-title">'.e(ui('footer.brand')).'</h2>', $footer);
            $this->assertMatchesRegularExpression('/<a class="d2-footer__logo" href="'.preg_quote(SiteUrl::home(), '/').'" aria-label="'.preg_quote(e(ui('brand.logo_alt')), '/').'"><svg class="d2-footer__lockup" aria-hidden="true" focusable="false"/', $footer);
            $this->assertStringContainsString('<p class="d2-footer__desc">'.e(ui('footer.description')).'</p>', $footer);
            // Every id other components link to stays (the tab bar's and the header's no-JS targets among them).
            foreach (['d2-footer-nav', 'd2-footer-practice', 'd2-footer-resources', 'd2-footer-useful', 'd2-footer-specialist', 'd2-footer-languages', 'd2-footer-languages-label'] as $id) {
                $this->assertSame(1, substr_count($footer, 'id="'.$id.'"'), "{$locale}: #{$id}");
            }
            $this->assertStringNotContainsString('style="', $footer);
        }
    }

    public function test_group_headings_are_the_approved_words_as_minor_h3s_never_mono_capitals(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $footer = $this->footer($this->d2("/{$locale}/"));

            preg_match_all('/<h3 class="d2-footer__title t-minor" id="d2-footer-[a-z]+">(.*?)<\/h3>/s', $footer, $m);
            $this->assertSame(
                [ui('footer.practice_areas'), ui('footer.resources'), ui('footer.useful_links'), ui('footer.specialist_heading')],
                array_map(fn ($h) => $this->text($h), $m[1]),
                "{$locale}: the four approved headings in DOCX wording and order (D01, D47)",
            );
            $this->assertSame(4, substr_count($footer, '<h3'), "{$locale}: no other heading");
            $this->assertDoesNotMatchRegularExpression('/t-eyebrow|t-mono|eyebrow/', $footer);
        }

        // The rule behind it: no monospace, capitals, tracking, shadows or glass in the footer's styles.
        $css = (string) file_get_contents(resource_path('css/d2/components/footer.css'));
        foreach (['--font-mono', 'text-transform', 'letter-spacing', 'box-shadow', 'backdrop-filter', '--night', '--glass', '--metal', 'gradient'] as $banned) {
            $this->assertStringNotContainsString($banned, $css, "footer.css: {$banned}");
        }
    }

    public function test_link_groups_follow_the_docx_order(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $footer = $this->footer($this->d2("/{$locale}/"));

            $this->assertSame(
                array_values(array_map(fn ($s) => SiteUrl::service($s['code']).' '.$s['title'], content()->services())),
                $this->hrefs($footer, 'practice'),
                "{$locale}: the eleven approved service titles, S01 to S11",
            );
            // 01_Homepage H-L043 to H-L045: Legal Resources Centre, FAQs, Case Studies.
            $this->assertSame([
                SiteUrl::page('P03').' '.ui('footer.resources_centre'),
                SiteUrl::page('P05').' '.ui('footer.faq'),
                SiteUrl::page('P04').' '.ui('footer.case_studies'),
            ], $this->hrefs($footer, 'resources'), "{$locale}: Legal Resources in the DOCX order");
            // H-L046 to H-L050.
            $this->assertSame([
                SiteUrl::page('P06').' '.ui('footer.about'),
                SiteUrl::contact().' '.ui('footer.contact'),
                SiteUrl::page('P08').' '.ui('footer.legal_notice'),
                SiteUrl::page('L01').' '.ui('footer.privacy'),
                SiteUrl::page('L02').' '.ui('footer.terms'),
            ], $this->hrefs($footer, 'useful'), "{$locale}: Useful Links in the DOCX order");
        }
    }

    public function test_the_specialist_group_is_the_last_group_directly_above_the_copyright_line(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $footer = $this->footer($this->d2("/{$locale}/"));

            // DOCX order, with the owner's placement of the specialist group: groups, disclaimer, specialist, ©.
            $positions = array_map(fn ($needle) => strpos($footer, $needle), [
                'class="d2-footer__col d2-footer__group d2-footer__group--useful"',
                '<p class="d2-footer__disclaimer">'.e(ui('footer.disclaimer')).'</p>',
                '<section class="d2-footer__specialist" aria-labelledby="d2-footer-specialist">',
                '<p class="d2-footer__copyright">',
            ]);
            $this->assertNotContains(false, $positions, $locale);
            $sorted = $positions;
            sort($sorted);
            $this->assertSame($sorted, $positions, "{$locale}: link groups, disclaimer, specialist group, copyright");
            $this->assertMatchesRegularExpression('/<\/section>\s*<div class="d2-footer__bottom">\s*<p class="d2-footer__copyright">/', $footer, "{$locale}: nothing between the specialist group and the copyright line");

            // A compact list: name, then the domain (left to right) and the external-link mark as an SVG; new tab.
            $section = substr($footer, $positions[2], $positions[3] - $positions[2]);
            preg_match_all('/<a class="d2-footer__site" href="([^"]+)" target="_blank" rel="noopener noreferrer">(.*?)<\/a>/s', $section, $sites);
            $expected = array_values(content()->specialistSites());
            $this->assertSame(array_column($expected, 'url'), $sites[1], "{$locale}: X01 to X06 in the DOCX order");
            foreach ($expected as $i => $site) {
                $this->assertMatchesRegularExpression(
                    '/^\s*<span class="d2-footer__site-label">'.preg_quote(e($site['label']), '/').'<\/span>\s*<span class="d2-footer__site-meta">\s*<bdi class="d2-footer__site-domain" dir="ltr">'.preg_quote($site['domain'], '/').'<\/bdi>\s*<svg class="icon mirror-rtl d2-footer__site-icon"[^>]*data-icon="external"/',
                    $sites[2][$i],
                    "{$locale}: {$site['domain']}",
                );
                // The hidden "(opens in a new tab)" is proposed copy: marked outside production (DOCX conformance, §8.7).
                $this->assertStringContainsString('<span class="sr-only" data-copy-status="proposed">'.e(proposed('external.new_tab')).'</span>', $sites[2][$i]);
            }
            $this->assertStringNotContainsString('↗', $section, 'the external mark is an icon, never a character');
        }
    }

    public function test_contact_controls_and_the_homepage_hours_sentence_without_a_live_status(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $footer = $this->footer($this->d2("/{$locale}/"));

            $this->assertMatchesRegularExpression('/<a class="d2-footer__channel" href="tel:\+971524666191">.*?<bdi class="d2-footer__channel-text" dir="ltr">\+971 52 466 6191<\/bdi>/s', $footer);
            $this->assertMatchesRegularExpression('/<a class="d2-footer__channel" href="mailto:info@summitlegaluae\.com">.*?<bdi class="d2-footer__channel-text d2-footer__email" dir="ltr">info@summitlegaluae\.com<\/bdi>/s', $footer);
            // WhatsApp: the approved WA01 draft of this language, a new tab, never sent; the official mark
            // (<x-d2.ui.brand-mark>: Meta's dark-background file on this dark band, or its marked placeholder).
            $this->assertSame(1, preg_match('/<a class="d2-footer__channel" href="([^"]+)" target="_blank" rel="noopener noreferrer">(.*?)<\/a>/s', $footer, $wa));
            $this->assertSame(setting('whatsapp_url'), html_entity_decode($wa[1]), $locale);
            $this->assertStringContainsString('data-brand="whatsapp"', $wa[2]);
            $this->assertStringContainsString('<span class="d2-footer__channel-text">'.e(ui('footer.whatsapp')).'</span>', $wa[2]);
            $this->assertStringContainsString(e(proposed('external.new_tab')), $wa[2]);

            // The footer's Word file is the homepage DOCX: its own hours line, plain text, no live status (brief
            // §3.7: the live status appears only in the contact block) and no clock icon.
            // (each time bound to its am/pm by a no-break space: Hours::keepTimes(), D11)
            $sentence = Hours::sentence('footer', $locale);
            $this->assertSame(Hours::keepTimes(content()->page('P01', $locale)['content']['contact']['hours']), $sentence);
            $this->assertStringContainsString('<p class="d2-footer__hours">'.e($sentence).'</p>', $footer, $locale);
            $contactPage = Hours::keepTimes(content()->page('P07', $locale)['content']['details']['hours'] ?? '');
            if ($contactPage !== '' && $contactPage !== $sentence) {
                $this->assertStringNotContainsString(e($contactPage), $footer, "{$locale}: not the Contact page's sentence");
            }
            $this->assertStringNotContainsString('data-hours-status', $footer);
            $this->assertStringNotContainsString('data-icon="clock"', $footer);
            // No address and no map anywhere in the footer.
            $this->assertDoesNotMatchRegularExpression('/<iframe|maps\.google|google\.[a-z.]+\/maps|<address/i', $footer);
        }
    }

    public function test_the_copyright_year_is_dynamic_in_uae_time(): void
    {
        // 31 December 21:00 UTC is already 1 January in Dubai (UTC+4).
        Carbon::setTestNow(Carbon::parse('2030-12-31 21:00:00', 'UTC'));
        try {
            foreach (self::LOCALES as $locale) {
                app()->setLocale($locale);
                $footer = $this->footer($this->d2("/{$locale}/"));
                $expected = preg_replace('/\b(19|20)\d{2}\b/', '2031', ui('footer.copyright'), 1);

                $this->assertStringContainsString('<p class="d2-footer__copyright">'.e($expected).'</p>', $footer, $locale);
                $this->assertStringNotContainsString('2026', $this->text($footer), "{$locale}: no fixed year");
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_language_links_show_the_approved_labels_of_the_page_language(): void
    {
        $approved = ['en' => ['Arabic', 'English', 'Russian'], 'ar' => ['العربية', 'الإنجليزية', 'الروسية'], 'ru' => ['Арабский', 'Английский', 'Русский']];
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $footer = $this->footer($this->d2("/{$locale}/about/"));

            $this->assertSame(1, preg_match('/<div class="d2-footer__langs" id="d2-footer-languages" role="group" aria-labelledby="d2-footer-languages-label">(.*?)<\/div>/s', $footer, $group));
            $this->assertStringContainsString('<span class="d2-footer__langs-label" id="d2-footer-languages-label">'.e(ui('a11y.choose_language')).'</span>', $group[1]);
            preg_match_all('/<a class="d2-footer__lang()" href="([^"]+)" hreflang="([a-z]+)"\s+data-lang-switch="([a-z]+)"\s*(aria-current="true")?\s*>(.*?)<\/a>/s', $group[1], $links, PREG_SET_ORDER);
            $this->assertCount(3, $links, $locale);
            $this->assertSame(['ar', 'en', 'ru'], array_column($links, 3), "{$locale}: Arabic, English, Russian (DOCX order)");
            // D12: the approved labels in the page language; the text is in the page language, so no lang attribute.
            $this->assertSame($approved[$locale], array_map(fn ($l) => $this->text($l[6]), $links), $locale);
            $this->assertStringNotContainsString(' lang="', $group[1], $locale);
            foreach ($links as $link) {
                $this->assertSame($link[3], $link[4]);
                $this->assertSame($link[3] === $locale, $link[5] !== '', "{$locale}: only the page language is current (aria-current)");
                $this->assertStringEndsWith('/'.$link[3].'/about/', $link[2], "{$locale}: the same page in that language");
            }
        }

        // An unknown address: every language link goes to that language's home page (never echoes the path).
        app()->setLocale('en');
        $footer = $this->footer($this->d2('/en/no-such-page/', 404));
        foreach (['ar', 'en', 'ru'] as $code) {
            $this->assertStringContainsString('href="'.SiteUrl::home($code).'" hreflang="'.$code.'"', $footer);
        }
        $this->assertStringNotContainsString('no-such-page', $footer);
    }

    public function test_no_banned_pattern_reaches_the_footer_text(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            $text = $this->text($this->footer($this->d2("/{$locale}/")));

            $this->assertStringNotContainsString(' · ', $text, "{$locale}: no middle-dot strings (D02)");
            foreach (['→', '←', '›', '»', '…', '...', '↗'] as $char) {
                $this->assertStringNotContainsString($char, $text, "{$locale}: {$char}");
            }
        }
    }

    /**
     * Brief §3.4 and the shell's touch rule (WCAG 2.5.8): 48 px rows on phones and on every touch screen, a touch
     * tablet at 768–1023 px included (pointer: coarse, as the header), 44 px from 768 px with a fine pointer only.
     */
    public function test_footer_targets_are_48_px_on_touch_screens_at_every_width(): void
    {
        $css = (string) file_get_contents(resource_path('css/d2/components/footer.css'));
        $targets = '\.d2-footer__channel,\s*\.d2-footer__link,\s*\.d2-footer__site,\s*\.d2-footer__lang \{\s*min-block-size: ';

        foreach (['channel', 'link', 'site', 'lang'] as $part) {
            $this->assertMatchesRegularExpression('/\.d2-footer__'.$part.' \{[^}]*min-block-size: var\(--target-touch\);/', $css, "{$part}: 48 px on phones");
        }
        $this->assertSame(1, preg_match('/@media \(width >= 768px\) \{.*?'.$targets.'var\(--target\);/s', $css, $fine, PREG_OFFSET_CAPTURE), '44 px from 768 px');
        $this->assertSame(1, preg_match('/@media \(pointer: coarse\) \{\s*'.$targets.'var\(--target-touch\);/', $css, $touch, PREG_OFFSET_CAPTURE), '48 px on touch screens');
        $this->assertGreaterThan($fine[0][1], $touch[0][1], 'the touch rule follows the 768 px rule, so it wins on a touch tablet');

        // One rhythm (§3.4): where the intro and the contact controls share a row (768–1279 px), neither column
        // stretches to the other's height, so the description stays 24 px under the lockup and the contact rows
        // keep their 44 / 48 px steps.
        $this->assertMatchesRegularExpression('/\.d2-footer__brand \{[^}]*align-items: start;/', $css, 'the brand row columns keep their own height');
    }

    public function test_the_styleguide_specimen_is_a_second_footer_with_its_own_ids(): void
    {
        app()->setLocale('en');
        $html = $this->d2('/en/styleguide/');

        $this->assertSame(1, substr_count($html, 'id="d2-footer-nav"'), 'the site footer once');
        $this->assertSame(1, substr_count($html, 'id="sg-footer-nav"'), 'the specimen with its own ids');
        $this->assertStringContainsString('<footer class="d2-footer on-dark" aria-labelledby="sg-footer-title">', $html);
        preg_match_all('/\sid="([^"]+)"/', $html, $ids);
        $this->assertSame([], array_keys(array_filter(array_count_values($ids[1]), fn ($n) => $n > 1)), 'no duplicate ids');
    }
}
