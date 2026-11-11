<?php

namespace Tests\Feature\Design\D2;

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Design 2 404 (owner: system-pages; upgrade spec §7.4 and §12 Phase D: "404 (misfiled sample document +
 * search)"). A real 404 in the language of the URL, noindex, the approved U01 copy, the palette trigger with
 * its no-JS list of main pages, the misfiled sample document, language links to each home page, and the
 * missing address never echoed. Design 1's 404 stays as it was.
 */
final class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function missing(string $path): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])
            ->get($path)
            ->assertNotFound()
            ->getContent();
    }

    public function test_unmatched_urls_render_the_design_two_404_in_the_language_of_the_url(): void
    {
        foreach (['en' => 'ltr', 'ar' => 'rtl', 'ru' => 'ltr'] as $locale => $dir) {
            $html = $this->missing("/{$locale}/no-such-page/");
            app()->setLocale($locale);
            $u01 = content()->page('U01');

            $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.$dir.'"', $html);
            $this->assertStringContainsString('data-design="2"', $html);
            $this->assertStringContainsString('data-page="error"', $html);
            $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
            $this->assertStringNotContainsString('rel="canonical"', $html);
            $this->assertStringNotContainsString('rel="alternate"', $html);
            $this->assertStringContainsString('<title>'.e($u01['seo_title']).'</title>', $html);
            $this->assertSame(1, substr_count($html, '<h1'), 'one H1');
            $this->assertStringContainsString('>'.e($u01['h1']).'</h1>', $html);
            $this->assertStringContainsString(e($u01['text']), $html);
            $this->assertStringContainsString('<bdi dir="ltr">'.e($u01['code_label']).'</bdi>', $html);
            $this->assertStringContainsString(e($u01['actions']['home_label']), $html);
            $this->assertStringContainsString(e($u01['actions']['services_label']), $html);
            $this->assertStringContainsString(e($u01['more_heading']), $html);
            foreach ($u01['links'] as $link) {
                $this->assertStringContainsString(e($link['label']), $html);
            }
        }
    }

    public function test_the_actions_and_links_point_at_real_pages(): void
    {
        $html = $this->missing('/ar/no-such-page/');
        $base = url('/');

        $this->assertMatchesRegularExpression('#<a class="btn btn--ink btn--\d+" href="'.preg_quote($base.'/ar/', '#').'">#', $html, 'Back to Home');
        $this->assertMatchesRegularExpression('#<a class="btn btn--secondary btn--\d+" href="'.preg_quote($base.'/ar/practice-areas/', '#').'">#', $html, 'Explore Our Legal Services');
        // Design 2.1 (brief §3.6 rules 3 and 5): no home or arrow glyph on the buttons, no object tiles or arrows
        // on the link cards; the search submit is the search glyph.
        $main = preg_match('#<main\b.*?</main>#s', $html, $m) ? $m[0] : '';
        $this->assertStringNotContainsString('data-icon="home"', $main);
        $this->assertStringNotContainsString('data-icon="arrow"', $main);
        $this->assertStringNotContainsString('nf-link__icon', $main);
        $this->assertSame(1, substr_count($main, 'data-icon='), 'one glyph: the search button');
        $this->assertStringContainsString('data-icon="search"', $main);
        // Approved U01 links: Knowledge Centre (P03), FAQ (P05), Contact (P07 → the home form until released), as
        // the kit's link rows (brief §3.5: the one look of a link to a related page), never cards.
        foreach (['/ar/resources/', '/ar/faq/', '/ar/#consultation-form'] as $path) {
            $this->assertMatchesRegularExpression('#<a class="link-row nf-link" href="'.preg_quote($base.$path, '#').'">\s*<span class="link-row__label">#', $html, $path);
        }
        $this->assertStringNotContainsString('nf-link__label', $main);
        $css = (string) file_get_contents(resource_path('css/d2/pages/error.css'));
        $this->assertStringNotContainsString('.nf-link__label', $css, 'no 17/600 card label');
        $this->assertMatchesRegularExpression('/\.nf-links \{[^}]*display: grid;[^}]*& > li \{\s*display: flex;/s', $css);
    }

    public function test_search_opens_the_palette_and_falls_back_to_the_main_pages_without_javascript(): void
    {
        $html = $this->missing('/en/no-such-page/');

        // A real search field (d2NotFoundSearch hands the typed words to the palette through d2:palette-open with
        // detail.query); the form's action is the Knowledge Centre for an unclaimed submit.
        $this->assertMatchesRegularExpression('#<form class="nf-search" role="search" action="'.preg_quote(url('/'), '#').'/en/resources/" x-data="d2NotFoundSearch" data-nf-search>#', $html);
        // Its placeholder is the page's own short proposed hint (Design 2.1, brief §3.6 rule 8: the header's longer
        // hint was cut beside the button on a 390 px phone in Russian and Arabic), drafted in all three languages.
        $this->assertMatchesRegularExpression('#<input class="nf-search__input" id="nf-q" type="search" placeholder="'.preg_quote(e(d2p('errors.search_placeholder')), '#').'"\s+aria-label="'.preg_quote(e(d2p('palette.input_label')), '#').'"#', $html);
        foreach (['en', 'ar', 'ru'] as $locale) {
            $hint = trans('proposed.d2.errors.search_placeholder', [], $locale);
            $this->assertNotSame('proposed.d2.errors.search_placeholder', $hint, $locale);
            $this->assertLessThan(mb_strlen(trans('proposed.d2.shell.search_placeholder', [], $locale)), mb_strlen($hint), $locale.': shorter than the header hint');
        }
        // The words never reach an address: the field has no name (the form carries nothing even without the script).
        preg_match('#<input class="nf-search__input"[^>]*>#', $html, $input);
        $this->assertStringNotContainsString(' name=', $input[0] ?? '');
        preg_match('#<button class="[^"]*\bnf-search__go\b[^"]*"[^>]*>#', $html, $go);
        $this->assertStringContainsString('aria-label="'.e(d2p('errors.search_go')).'"', $go[0] ?? '', 'the search button is named');
        $this->assertStringContainsString('type="submit"', $go[0] ?? '');
        // The site's one search field (brief §3.5): 48 px, the magnifier at its inline start as on P03 and P05. The
        // magnifier is the submit: a 44 px icon button (48 px hit area on touch), first in the form as on screen;
        // the input fills the field's height inside its 1 px edge.
        $this->assertStringContainsString('btn--44', $go[0] ?? '', 'the 44 px button keeps the field 48 px');
        $this->assertMatchesRegularExpression('#<form class="nf-search"[^>]*>\s*<button class="[^"]*\bnf-search__go\b[^"]*"[\s\S]*?</button>\s*<input class="nf-search__input" id="nf-q"#', $html, 'the magnifier comes first');
        $css = (string) file_get_contents(resource_path('css/d2/pages/error.css'));
        $this->assertMatchesRegularExpression('/\.nf-search \{[^}]*min-block-size: var\(--field-h\);\s*padding-inline: var\(--s-1\) var\(--s-4\);/s', $css);
        $this->assertMatchesRegularExpression('/\.nf-search__input \{[^}]*block-size: calc\(var\(--field-h\) - 2 \* var\(--line-w\)\);/s', $css);
        $this->assertStringNotContainsString('data-d2-search aria-keyshortcuts', $html, 'no longer a palette trigger link');
        // No JavaScript: the main pages, with the approved navigation labels.
        $this->assertStringContainsString('<nav class="nf-pages" aria-labelledby="nf-pages-title">', $html);
        $this->assertStringContainsString(e(d2p('errors.pages_title')), $html);
        foreach (['nav.practice_areas', 'nav.resources', 'nav.case_studies', 'nav.faq', 'nav.about', 'nav.contact'] as $key) {
            $this->assertMatchesRegularExpression('#class="chip chip--filter"\s+href="[^"]+"\s*>\s*<span class="chip__label">'.preg_quote(e(ui($key)), '#').'</span>#', $html, $key);
        }
        // The layout's command palette is on the page for the trigger to open.
        $this->assertStringContainsString('data-modal="palette"', $html);
    }

    public function test_the_misfiled_sample_document_is_a_labelled_illustration_with_its_watermark(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->missing("/{$locale}/no-such-page/");
            app()->setLocale($locale);

            $this->assertStringContainsString('<div class="nf__art nf-art" role="img" aria-label="'.e(d2p('errors.art_alt')).'"', $html);
            $this->assertStringContainsString('<div class="nf-art__stage" aria-hidden="true">', $html);
            if (is_file(resource_path('views/components/d2/doc/contract.blade.php'))) {
                $this->assertStringContainsString('data-doc="contract"', $html);
                $this->assertStringContainsString(e(d2p('docs.watermark')), $html);
            }
            $this->assertStringContainsString('class="nf-art__emblem" aria-hidden="true"', $html, 'the official emblem, inlined');
        }
    }

    public function test_language_links_go_to_each_home_page_and_the_missing_address_is_never_echoed(): void
    {
        $html = $this->missing('/ru/private-matter-reference/?name=Ivan&case=12345');
        $base = url('/');

        foreach (['en', 'ar', 'ru'] as $code) {
            $this->assertMatchesRegularExpression('#href="'.preg_quote($base, '#').'/'.$code.'/"[^>]*\bhreflang="'.$code.'"[^>]*data-lang-switch="'.$code.'"#', $html, "header/sheet language link {$code}");
        }
        $this->assertStringNotContainsString('private-matter-reference', $html);
        $this->assertStringNotContainsString('Ivan', $html);
        $this->assertStringNotContainsString('12345', $html);
    }

    public function test_abort_404_on_a_matched_route_also_sends_the_language_links_home(): void
    {
        // A matched route that aborts (as PagePreviewController does for a page without content).
        Route::middleware('web')->get('/en/d2-test-abort/', fn () => abort(404));

        $html = $this->missing('/en/d2-test-abort/');
        $base = url('/');

        $this->assertStringContainsString('data-page="error"', $html);
        $this->assertStringNotContainsString('/ar/d2-test-abort/', $html, 'no language link to the same missing path');
        $this->assertStringNotContainsString('/ru/d2-test-abort/', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote($base, '#').'/ar/"[^>]*\bhreflang="ar"#', $html);
    }

    public function test_unknown_language_prefixes_fall_back_to_english(): void
    {
        $html = $this->missing('/de/seite/');

        $this->assertStringContainsString('<html lang="en" dir="ltr"', $html);
        $this->assertStringContainsString('data-design="2"', $html);
    }

    public function test_design_one_keeps_its_own_404(): void
    {
        $html = $this->get('/en/no-such-page/')->assertNotFound()->getContent();

        $this->assertStringNotContainsString('data-design="2"', $html);
        $this->assertStringNotContainsString('nf-art', $html);
        $this->assertStringContainsString('class="has-bottombar', $html, 'Design 1 layout');
    }

    public function test_proposed_copy_exists_in_every_language_and_is_marked(): void
    {
        $keys = array_keys(require lang_path('en/proposed-d2/errors.php'));
        foreach (['ar', 'ru'] as $locale) {
            $this->assertSame($keys, array_keys(require lang_path("{$locale}/proposed-d2/errors.php")), $locale);
        }
        foreach (['en', 'ar', 'ru'] as $locale) {
            foreach ($keys as $key) {
                $value = d2p('errors.'.$key, [], $locale);
                $this->assertNotSame('proposed.d2.errors.'.$key, $value, "{$locale} {$key}");
                if ($locale !== 'en') {
                    $this->assertNotSame(d2p('errors.'.$key, [], 'en'), $value, "{$locale} {$key} is translated");
                }
            }
        }
        $this->assertStringContainsString('data-copy-status="proposed"', $this->missing('/en/no-such-page/'));
    }
}
