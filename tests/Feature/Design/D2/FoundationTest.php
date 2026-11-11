<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\FontFaces;
use App\Design\D2\Fonts;
use App\Filament\Support\AdminPalette;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design 2.1 foundation (docs/briefs/design-2.1-and-admin-cms.md §3.2 to §3.4; contract docs/d2.1/phase-1/tokens.md;
 * owner: foundation): the fonts (unmodified Fontsource files, faces, fallbacks, groups, preloads), the tokens
 * (colour, space, radius, elevation, type), the base type roles and the layout primitives.
 */
final class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
    }

    private function home(string $locale = 'en'): string
    {
        return $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/")->assertOk()->getContent();
    }

    private function css(string $file): string
    {
        return (string) file_get_contents(resource_path('css/d2/'.$file));
    }

    /** @return array<string, string> the declarations of the main :root block of tokens.css */
    private function tokens(): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->css('tokens.css'));
        $this->assertSame(1, preg_match('/^:root\s*\{(.*?)^\}/ms', $css, $block));
        preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/', $block[1], $m, PREG_SET_ORDER);
        $tokens = [];
        foreach ($m as [, $name, $value]) {
            $tokens[$name] ??= trim($value);
        }

        return $tokens;
    }

    /** @return array<string, string> the declarations of `@media (width >= {$min}px) { :root { … } }` */
    private function at(int $min): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->css('tokens.css'));
        $this->assertSame(1, preg_match('/@media \(width >= '.$min.'px\) \{\s*:root \{(.*?)\}\s*\}/s', $css, $block), "a {$min}px block");
        preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/', $block[1], $m, PREG_SET_ORDER);

        return array_column($m, 2, 1);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, "found {$from}");
        $end = strpos($html, $to, $start + strlen($from));
        $this->assertNotFalse($end, "found {$to} after {$from}");

        return substr($html, $start, $end - $start + strlen($to));
    }

    private static function contrast(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            $c = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
            $c = array_map(fn ($v) => $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$lum($a), $lum($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    // ------------------------------------------------------------------------------------------------ fonts

    /** Pair B from the unmodified Fontsource files (owner decision, Phase 1): pinned, copied byte for byte. */
    public function test_the_interface_fonts_are_the_unmodified_fontsource_files(): void
    {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true);
        $this->assertSame('5.3.1', $package['devDependencies']['@fontsource-variable/onest'] ?? null, 'exact pin');
        $this->assertSame('5.3.0', $package['devDependencies']['@fontsource/ibm-plex-sans-arabic'] ?? null, 'exact pin');

        $manifest = json_decode((string) file_get_contents(public_path('fonts/d2/manifest.json')), true);
        $this->assertSame('scripts/fonts/fontsource-d2.mjs', $manifest['generator']);
        $this->assertSame('5.3.1', $manifest['packages']['@fontsource-variable/onest']['version']);
        $this->assertSame('5.3.0', $manifest['packages']['@fontsource/ibm-plex-sans-arabic']['version']);

        $families = [];
        foreach ($manifest['files'] as $key => $file) {
            $path = public_path(ltrim($file['url'], '/'));
            $this->assertFileExists($path, $key);
            $this->assertSame($file['sha256'], hash_file('sha256', $path), "{$key}: the served file is the manifest's");
            $this->assertStringStartsWith('/fonts/d2/'.$key.'.'.substr($file['sha256'], 0, 8).'.woff2', $file['url'], "{$key}: content-hashed URL");
            if (str_starts_with($file['source'], '@fontsource')) {
                $families[$file['family']][] = $key;
                $source = base_path('node_modules/'.preg_replace('/@(\d+\.\d+\.\d+)\//', '/', $file['source']));
                if (is_file($source)) {
                    $this->assertSame(hash_file('sha256', $source), $file['sha256'], "{$key}: byte for byte the package's file");
                }
            }
        }
        $this->assertSame(['onest-cyrillic-wght-normal', 'onest-latin-wght-normal'], $families['D2 Sans'], 'Onest: Latin and Cyrillic');
        sort($families['D2 Sans Arabic']);
        $this->assertSame([
            'ibm-plex-sans-arabic-arabic-400-normal', 'ibm-plex-sans-arabic-arabic-500-normal', 'ibm-plex-sans-arabic-arabic-600-normal',
            'ibm-plex-sans-arabic-latin-400-normal', 'ibm-plex-sans-arabic-latin-500-normal', 'ibm-plex-sans-arabic-latin-600-normal',
        ], $families['D2 Sans Arabic'], 'IBM Plex Sans Arabic: Arabic and its own Latin, 400 / 500 / 600 only');
        foreach (['LICENSE-onest.txt', 'LICENSE-ibm-plex-sans-arabic.txt'] as $licence) {
            $this->assertStringContainsString('SIL OPEN FONT LICENSE', strtoupper((string) file_get_contents(public_path('fonts/d2/'.$licence))), $licence);
        }
        // the retired IBM Plex Sans interface files are gone
        $this->assertSame([], glob(public_path('fonts/d2/sans-*.woff2')));
    }

    /** brief §3.2: font-display swap, one face per subset with its unicode-range, metric-matched fallbacks. */
    public function test_font_faces_and_metric_matched_fallbacks(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->css('base/fonts.css'));
        preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $rules);
        $byFamily = [];
        foreach ($rules[1] as $rule) {
            $this->assertSame(1, preg_match('/font-family:\s*"([^"]+)"/', $rule, $f));
            $byFamily[$f[1]][] = $rule;
            if (str_contains($rule, 'url(')) {
                $this->assertStringContainsString('font-display: swap', $rule, $f[1]);
                $this->assertMatchesRegularExpression('/unicode-range: U\+/', $rule, $f[1]);
            } else {
                foreach (['size-adjust', 'ascent-override', 'descent-override', 'line-gap-override'] as $descriptor) {
                    $this->assertMatchesRegularExpression('/'.$descriptor.': \d+(\.\d+)?%/', $rule, "{$f[1]}: {$descriptor}");
                }
                $this->assertStringContainsString('src: local(', $rule);
            }
        }
        foreach ($byFamily['D2 Sans'] as $rule) {
            $this->assertStringContainsString('font-weight: 400 600;', $rule, 'Onest: variable, declared 400 to 600 (nothing heavier)');
        }
        foreach ($byFamily['D2 Sans Arabic'] as $rule) {
            $this->assertMatchesRegularExpression('/font-weight: (400|500|600);/', $rule, 'IBM Plex Sans Arabic: static 400 / 500 / 600');
        }
        // the sample-document faces stay (illustrations only), with their fallbacks
        foreach (['D2 Mono', 'D2 Serif', 'D2 Naskh', 'D2 Sans Fallback', 'D2 Sans Arabic Fallback', 'D2 Mono Fallback', 'D2 Serif Fallback', 'D2 Naskh Fallback'] as $family) {
            $this->assertArrayHasKey($family, $byFamily, $family);
        }
        // Arabic pages: the space (drawn by the Latin file) has the web font's width before that file arrives
        $space = array_filter($byFamily['D2 Sans Arabic Fallback'], fn ($rule) => str_contains($rule, 'unicode-range: U+0020, U+00A0;'));
        $this->assertCount(3, $space, 'one space-only face per weight');
        foreach ($space as $rule) {
            $this->assertStringContainsString('size-adjust: 84.94%;', $rule, 'IBM Plex Sans Arabic space 0.236 em / Arial 0.278 em');
        }
        // no file in the CSS that the manifest does not list, and the other way round
        preg_match_all('#url\("(/fonts/d2/[^"]+)"\)#', $css, $urls);
        $this->assertEqualsCanonicalizing(array_values(Fonts::manifest()), array_values(array_unique($urls[1])));
    }

    /** brief §3.2 and §6: preload only the current locale's 400 and 600 files, two files at most. */
    public function test_at_most_two_font_preloads_per_locale(): void
    {
        $this->assertSame('b', Fonts::pair(), 'pair B until Graphik is licensed');
        $expected = [
            'en' => ['onest-latin-wght-normal'],
            'ru' => ['onest-cyrillic-wght-normal', 'onest-latin-wght-normal'],
            'ar' => ['ibm-plex-sans-arabic-arabic-400-normal', 'ibm-plex-sans-arabic-arabic-600-normal'],
        ];
        foreach ($expected as $locale => $keys) {
            $html = $this->home($locale);
            preg_match_all('#<link rel="preload" href="/fonts/d2/([a-z0-9-]+)\.[0-9a-f]{8}\.woff2" as="font" type="font/woff2" crossorigin>#', $html, $m);
            $this->assertSame($keys, $m[1], "{$locale}: preloads");
            $this->assertLessThanOrEqual(2, substr_count($html, 'as="font"'), "{$locale}: two at most");
        }
    }

    /** The @font-face rules in groups; what the first screen does not need waits for the first paint. */
    public function test_font_faces_are_grouped_and_held_until_the_first_paint(): void
    {
        $expect = [
            'en' => ['base' => ['onest-latin-wght-normal'], 'late' => ['onest-cyrillic-wght-normal'], 'docs' => ['serif-latin', 'mono-latin', 'mono-latin-400', 'naskh-arabic', 'ibm-plex-sans-arabic-arabic-600-normal']],
            'ru' => ['base' => ['onest-latin-wght-normal', 'onest-cyrillic-wght-normal'], 'docs' => ['serif-latin', 'serif-cyrillic', 'mono-cyrillic', 'ibm-plex-sans-arabic-arabic-600-normal']],
            'ar' => ['base' => ['ibm-plex-sans-arabic-arabic-400-normal', 'ibm-plex-sans-arabic-arabic-600-normal', 'ibm-plex-sans-arabic-arabic-500-normal', 'ibm-plex-sans-arabic-latin-400-normal', 'ibm-plex-sans-arabic-latin-600-normal'], 'docs' => ['naskh-arabic', 'serif-latin', 'mono-latin'], 'late' => ['onest-cyrillic-wght-normal']],
        ];
        foreach ($expect as $locale => $groups) {
            $html = $this->home($locale);
            $head = $this->between($html, '<head>', '</head>');
            preg_match_all('/<style nonce="[^"]*"(?: data-d2-fonts="([a-z]+)")?[^>]*>(@font-face.*?)<\/style>/s', $head, $m, PREG_SET_ORDER);
            $found = [];
            foreach ($m as $style) {
                preg_match_all('#/fonts/d2/([a-z0-9-]+)\.[0-9a-f]+\.woff2#', $style[2], $files);
                $found[$style[1] !== '' ? $style[1] : 'base'] = array_unique($files[1]);
            }
            foreach ($groups as $group => $files) {
                $this->assertArrayHasKey($group, $found, "{$locale}: a {$group} group");
                foreach ($files as $file) {
                    $this->assertContains($file, $found[$group], "{$locale}: {$file} in {$group}");
                }
            }
            // the preloads are first-screen faces: always active
            foreach (Fonts::preload($locale) as $url) {
                $this->assertContains(preg_replace('#^/fonts/d2/([a-z0-9-]+)\.[0-9a-f]+\.woff2$#', '$1', $url), $found['base'], "{$locale}: the preload {$url} is in base");
            }
            // every rule printed once: the full set is there without JavaScript
            $all = array_merge(...array_values($found));
            foreach (array_keys(Fonts::manifest()) as $key) {
                $this->assertContains($key, $all, "{$locale}: {$key} is declared");
            }
            $this->assertCount(count(array_unique($all)), $all, "{$locale}: no file in two groups");
            $boot = $this->between($html, '<script nonce=', '</script>');
            $this->assertStringContainsString("d.querySelectorAll('style[data-d2-fonts]')", $boot);
            $this->assertStringContainsString("getEntriesByName('first-contentful-paint')", $boot, 'held groups are enabled after the first contentful paint');
            $this->assertMatchesRegularExpression('/data-d2-fonts="docs"\s+data-d2-load="\[\[/', $head, "{$locale}: the stage's faces start in the head where the stage is in the first screen");
        }
        // Arabic pages start the Latin files (spaces, digits) and the 500 faces in the head; English pages need none
        $ar = $this->between($this->home('ar'), '<head>', '</head>');
        $this->assertStringContainsString('data-d2-early="[[&quot;400 16px', $ar);
        $this->assertSame([['400 16px "D2 Sans Arabic"', 'A'], ['500 16px "D2 Sans Arabic"', 'اA'], ['600 16px "D2 Sans Arabic"', 'A']], FontFaces::earlyLoads('ar'));
        $this->assertStringNotContainsString('data-d2-early="', $this->between($this->home('en'), '<head>', '</head>'));
        $this->assertContains(['600 16px "D2 Sans Arabic Script"', 'ع'], FontFaces::docLoads('en'), 'the watermark Arabic line');
        $this->assertContains(['600 16px "D2 Sans Arabic"', 'A'], FontFaces::docLoads('ar'), 'its spaces and dots on Arabic pages');
        $this->assertContains(['500 16px "D2 Mono"', 'AЖ'], FontFaces::docLoads('ru'));
    }

    /** One token switches the family; no monospace or serif in the interface (brief §3.2). */
    public function test_one_font_token_and_no_monospace_in_the_interface(): void
    {
        $css = $this->css('tokens.css');
        $this->assertSame(1, preg_match_all('/--font-sans:\s*var\(--font-[ab]\);/', $css), 'one --font-sans declaration: the switch');
        $this->assertMatchesRegularExpression('/:root,\s*\[lang\]\s*\{[^}]*--font-sans: var\(--font-b\);/', $css);
        $this->assertMatchesRegularExpression('/:root\[lang\|="ar"\],\s*:root\[lang\|="ar"\] \[lang\|="ar"\]\s*\{[^}]*--font-b: var\(--font-b-arabic\);/', $css, 'Arabic pages use the Arabic family first');
        $this->assertSame('"D2 Sans", "D2 Sans Fallback", "D2 Sans Arabic Script", "D2 Sans Arabic Script Fallback", system-ui, sans-serif', $this->tokens()['font-b-latin'], 'an Arabic word on a Latin page takes only its letters from the Arabic files');
        $this->assertStringContainsString('"D2 Sans Cyrillic", "D2 Sans Cyrillic Fallback"', $this->tokens()['font-b-arabic'], 'Cyrillic on an Arabic page: Onest\'s Cyrillic file only');
        $tokens = $this->tokens();
        $this->assertStringStartsWith('"D2 Sans", "D2 Sans Fallback"', $tokens['font-b-latin']);
        // script-only aliases: the same files, cut to one script (no family of a stack covers the page's own text twice)
        $fonts = (string) preg_replace('#/\*.*?\*/#s', '', $this->css('base/fonts.css'));
        preg_match_all('/@font-face\s*\{[^}]*font-family: "D2 Sans Arabic Script";[^}]*unicode-range: ([^;]+);/', $fonts, $alias);
        $this->assertCount(3, $alias[1], 'the Arabic files at 400, 500 and 600');
        foreach ($alias[1] as $range) {
            $this->assertStringNotContainsString('U+0000', $range, 'no Latin in the Arabic script alias');
            $this->assertStringNotContainsString('U+2000', $range);
        }
        $this->assertStringStartsWith('"D2 Sans Arabic", "D2 Sans Arabic Fallback"', $tokens['font-b-arabic']);
        foreach (['font-mono', 'font-doc', 'font-doc-arabic'] as $legacy) {
            $this->assertSame('var(--font-sans)', $tokens[$legacy], "--{$legacy}: the sans in the interface");
        }
        $this->assertMatchesRegularExpression('/\.doc,\s*\[data-doc\],\s*\[data-illustration\]\s*\{\s*--font-mono: var\(--font-doc-mono\);/', $css, 'documents keep their typed-field face');
        $this->assertStringContainsString('font-family: var(--font-sans);', $this->css('base/base.css'));
        $this->assertDoesNotMatchRegularExpression('/\.t-eyebrow\s*\{[^}]*(uppercase|var\(--font-mono\)|letter-spacing: 0\.)/', $this->css('base/base.css'), 'the eyebrow is the UI label');
    }

    // ------------------------------------------------------------------------------------------------ tokens

    /** brief §3.3: the colour tokens, their contrast, and no warm canvas. */
    public function test_colour_tokens_match_the_brief(): void
    {
        $t = $this->tokens();
        $expect = [
            'canvas' => '#FFFFFF', 'band' => '#F6F6F7', 'ink' => '#0B0B0C', 'ink-2' => '#3F3F46', 'ink-3' => '#6B6B73',
            'line' => '#E4E4E7', 'line-strong' => '#D4D4D8', 'field-border' => '#8A8A93', 'gold' => '#C9A96B',
            'bronze' => '#8A6A2E', 'bronze-700' => '#7A5C24', 'dark' => '#0B0B0C', 'on-dark' => '#FFFFFF',
            'on-dark-2' => '#E4E4E7', 'on-dark-3' => '#A1A1AA', 'on-dark-gold' => '#C9A96B', 'danger' => '#B42318',
            'success' => '#1E6B47', 'focus' => '#0B0B0C', 'focus-on-dark' => '#E2CF9F',
            'badge-neutral-fg' => '#52525B', 'badge-neutral-bg' => '#F1F1F3', 'badge-info-fg' => '#2F5DA0', 'badge-info-bg' => '#EAF1FB',
            'badge-success-fg' => '#1E6B47', 'badge-success-bg' => '#E8F5EE', 'badge-warning-fg' => '#8A5300', 'badge-warning-bg' => '#FFF4E0',
            'badge-danger-fg' => '#B42318', 'badge-danger-bg' => '#FDECEA', 'badge-brand-fg' => '#7A5C24', 'badge-brand-bg' => '#F7F1E6',
        ];
        foreach ($expect as $name => $hex) {
            $this->assertSame($hex, $t[$name] ?? null, "--{$name}");
        }
        // text contrast (4.5:1) and control contrast (3:1) on the backgrounds they are used on
        foreach ([['ink-3', 'canvas'], ['ink-3', 'band'], ['bronze', 'band'], ['danger', 'canvas'], ['success', 'canvas'], ['on-dark-3', 'dark']] as [$text, $bg]) {
            $this->assertGreaterThanOrEqual(4.5, self::contrast($t[$text], $t[$bg]), "--{$text} on --{$bg}");
        }
        foreach (['neutral', 'info', 'success', 'warning', 'danger', 'brand'] as $badge) {
            $this->assertGreaterThanOrEqual(4.5, self::contrast($t["badge-{$badge}-fg"], $t["badge-{$badge}-bg"]), "{$badge} badge");
        }
        $this->assertGreaterThanOrEqual(3, self::contrast($t['field-border'], $t['band']), 'field border on the band');
        $this->assertGreaterThanOrEqual(3, self::contrast($t['field-border-on-dark'], $t['dark']), 'field border on dark');
        $this->assertGreaterThanOrEqual(4.5, self::contrast($t['ink'], $t['gold-hover']), 'ink on the hovered gold');
        $this->assertLessThan(4.5, self::contrast($t['bronze'], $t['badge-brand-bg']), 'the pair the brief forbids stays forbidden');
        // no warm canvas, no metal, no glass: the legacy names point at the new tokens
        foreach (['surface' => 'var(--canvas)', 'surface-sunken' => 'var(--band)', 'metal' => 'var(--gold)', 'glass' => 'var(--canvas)', 'glass-blur' => 'none', 'hero-light' => 'none', 'shimmer' => 'none', 'duotone-fill' => 'transparent'] as $legacy => $value) {
            $this->assertSame($value, $t[$legacy] ?? null, "legacy --{$legacy}");
        }
        $this->assertDoesNotMatchRegularExpression('/--(canvas|band|surface|surface-sunken|line|line-strong):\s*#(F7F6F3|EFEDE8|E4E1DA|CFCAC0)/', $this->css('tokens.css'), 'the warm page colours are gone (D07)');
    }

    /** brief §3.4: spacing, radius and elevation. */
    public function test_spacing_radius_and_elevation_tokens(): void
    {
        $t = $this->tokens();
        $steps = [];
        foreach ($t as $name => $value) {
            if (preg_match('/^s-\d+$/', $name) && ! in_array($name, ['s-10', 's-20'], true)) {
                $steps[] = (int) $value;
            }
        }
        sort($steps);
        $this->assertSame([4, 8, 12, 16, 20, 24, 32, 48, 64, 96, 128], $steps, 'the spacing scale');
        $this->assertSame(['6px', '10px', '16px', '999px'], [$t['radius-control'], $t['radius-card'], $t['radius-overlay'], $t['radius-pill']]);
        $this->assertSame('0 16px 40px rgb(11 11 12 / 0.12), 0 2px 8px rgb(11 11 12 / 0.06)', $t['shadow-overlay']);
        foreach (['shadow-1', 'shadow-2', 'shadow-3', 'shadow-header', 'shadow-pill', 'shadow-inset-input'] as $legacy) {
            $this->assertSame('none', $t[$legacy], "no shadow on cards, buttons or inputs: --{$legacy}");
        }
        $this->assertSame('var(--shadow-overlay)', $t['shadow-4']);
        $this->assertSame(['0px', '0px', '1'], [$t['lift'], $t['lift-card'], $t['portrait-zoom']], 'no hover lift (D05)');
        $this->assertDoesNotMatchRegularExpression('/^--(?!mask-|paper-|staple)[a-z0-9-]+:\s*(linear|radial|conic)-gradient/m', $this->css('tokens.css'), 'gradients only as masks and inside the documents');
    }

    /** brief §3.2: the type scale (desktop from 768 px / mobile), weights 400 / 500 / 600, the Arabic rules. */
    public function test_type_scale_and_weights(): void
    {
        $t = $this->tokens();
        $desktop = $this->at(768);
        $px = fn (string $rem) => (float) $rem * 16;
        $scale = ['display' => [56, 40], 'h2' => [36, 28], 'h3' => [22, 20], 'lead' => [19, 17], 'body' => [17, 16], 'ui' => [15, 15], 'small' => [15, 14], 'caption' => [13, 13]];
        foreach ($scale as $role => [$big, $small]) {
            $this->assertSame((float) $small, $px($t["fs-{$role}"]), "--fs-{$role} mobile");
            $this->assertSame((float) $big, $px($desktop["fs-{$role}"] ?? $t["fs-{$role}"]), "--fs-{$role} from 768 px");
        }
        foreach (['display' => '1.05', 'h2' => '1.15', 'h3' => '1.3', 'lead' => '1.55', 'body' => '1.6', 'ui' => '1.4', 'small' => '1.5', 'caption' => '1.45'] as $role => $ratio) {
            $this->assertSame("calc(var(--fs-{$role}) * {$ratio})", $t["lh-{$role}"], "--lh-{$role}");
        }
        $this->assertSame(['400', '500', '600'], [$t['fw-regular'], $t['fw-medium'], $t['fw-semibold']]);
        foreach ($t as $name => $value) {
            if (str_starts_with($name, 'fw-')) {
                $this->assertMatchesRegularExpression('/^(400|500|600|var\(--fw-(regular|medium|semibold)\))$/', $value, "--{$name}: nothing above 600");
            }
        }
        $css = $this->css('tokens.css');
        $this->assertMatchesRegularExpression('/\[lang\|="ar"\]\s*\{[^}]*--fs-body: 1\.0625rem;[^}]*--lh-body: calc\(var\(--fs-body\) \* 1\.75\);[^}]*--ls-display: 0;/s', $css, 'Arabic: body 1px larger, line height 1.75, no tracking');
        $base = $this->css('base/base.css');
        $this->assertMatchesRegularExpression('/:lang\(ru\) p \{\s*hyphens: auto;\s*hyphenate-limit-chars: 10 4 4;\s*\}/', $base, 'Russian paragraphs hyphenate long words only (10 letters, 4 on each side)');
        $this->assertMatchesRegularExpression('/p:not\(\.doc p, \[data-illustration\] p\) \{\s*max-inline-size: var\(--measure\);/', $base, '64ch measure');
        $this->assertSame('64ch', $t['measure']);
        $this->assertStringContainsString('font-variant-numeric: tabular-nums', $base, 'a tabular-figures utility');
        $this->assertMatchesRegularExpression('/:lang\(ar\) :is\(em, i, cite, dfn, var, address\) \{\s*font-style: normal;/', $base, 'no italics in Arabic');
    }

    /**
     * A language island (lang="en"/"ru" on an Arabic page, lang="ar" on an English or Russian page) gets its own
     * script's tokens (tokens.css) AND, at its root block, the body size and line height in that script: before,
     * an English note on an Arabic page kept the Arabic body's computed 18 / 31.5 px (round-1 QA, D08).
     */
    public function test_a_block_island_takes_the_body_size_of_its_own_language(): void
    {
        $base = (string) preg_replace('#/\*.*?\*/#s', '', $this->css('base/base.css'));
        $this->assertSame(1, preg_match('/([^{}]*:where\(:root:lang\(ar\)\)[^{}]*)\{\s*font-size: var\(--fs-body\);\s*line-height: var\(--lh-body\);\s*\}/', $base, $rule), 'the island rule');
        $selectors = array_map('trim', $this->topLevel($rule[1], ','));
        $this->assertCount(2, $selectors, 'both directions');
        [$latin, $arabic] = $selectors;
        $this->assertStringStartsWith(':where(:root:lang(ar)) ', $latin);
        $this->assertStringContainsString(':where(:lang(en), :lang(ru))', $latin, 'English or Russian on an Arabic page');
        $this->assertStringContainsString(':not(:lang(en) *, :lang(ru) *', $latin, 'the root of the island only');
        $this->assertStringStartsWith(':where(:root:not(:lang(ar))) ', $arabic);
        $this->assertStringContainsString(':where(:lang(ar))', $arabic, 'Arabic on an English or Russian page');
        $this->assertStringContainsString(':not(:lang(ar) *', $arabic, 'the root of the island only');
        foreach ($selectors as $selector) {
            foreach (['p', 'li', 'ul', 'blockquote', 'div'] as $block) {
                $this->assertMatchesRegularExpression('/:where\([^)]*\b'.$block.'\b/', $selector, "{$block} islands");
            }
            $this->assertStringNotContainsString('span', $selector, 'an inline island keeps the size of its sentence');
            $this->assertStringContainsString('[data-illustration] *', $selector, 'the sample documents keep their sizes');
            // Zero specificity: every compound is a :where(), so any role, heading or component rule still wins.
            $this->assertSame('', trim(str_replace(',', '', $this->outsideWhere($selector))), "zero specificity: {$selector}");
        }
        // The token reset the rule relies on: a Latin island gets the Latin body back.
        $this->assertMatchesRegularExpression('/\[lang\]:not\(\[lang\|="ar"\]\)\s*\{[^}]*--fs-body: 1rem;[^}]*--lh-body: calc\(var\(--fs-body\) \* 1\.6\);/s', $this->css('tokens.css'));
    }

    /**
     * The styleguide frame (owner: platform): the H1 opens the page (no eyebrow, no middle dot: D01, D02, §3.6
     * rules 1 and 2); the elevation specimens carry the lint markers (qa/README.md: the overlay shadow on
     * [data-overlay], paper shadows only on [data-illustration]); English running text on an Arabic page is a
     * left-to-right block.
     */
    public function test_the_styleguide_frame(): void
    {
        $this->assertArrayNotHasKey('eyebrow', require lang_path('en/styleguide_d2/page.php'));
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/styleguide/")->assertOk()->getContent();
            $head = $this->between($html, '<header class="sg-head">', '</header>');
            $this->assertMatchesRegularExpression('/^<header class="sg-head">\s*<h1 class="sg-head__title"/', $head, "{$locale}: the H1 opens the page");
            $this->assertStringNotContainsString('t-eyebrow', $head, $locale);
            $this->assertStringNotContainsString('·', $head, $locale);
            $this->assertSame(1, substr_count($html, '<h1'), "{$locale}: one H1");

            $foundations = $this->between($html, '<section class="sg-section" id="sg-foundations"', '</section>');
            $this->assertSame(1, preg_match_all('/<div class="sg-elev sg-elev--shadow-overlay"[^>]*>/', $foundations, $overlay));
            $this->assertStringContainsString(' data-overlay', $overlay[0][0], "{$locale}: the overlay specimen is an overlay");
            $this->assertSame(3, preg_match_all('/<div class="sg-paper [^"]*"[^>]*>/', $foundations, $papers), 'rest, air, lift');
            foreach ($papers[0] as $paper) {
                $this->assertStringContainsString(' data-illustration', $paper, "{$locale}: a paper shadow only on document art");
            }
            if ($locale !== 'en') {
                $this->assertMatchesRegularExpression('/<p class="sg-note" lang="en">/', $html, "{$locale}: the notes are English islands");
            }
        }
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->css('styleguide.css'));
        // Right to left is read from the page's dir attribute: the production build rewrites :dir(rtl) as
        // :is(:lang(ar), …), which an English (:lang(en)) annotation never matches.
        $this->assertStringNotContainsString(':dir(', $css, 'no :dir() in the styleguide frame');
        $this->assertSame(1, preg_match('/(:root\[dir="rtl"\][^{}]*\.sg-note[^{}]*:lang\(en\)[^{}]*)\{([^}]*)\}/', $css, $ltr), 'the English running-text rule');
        $this->assertStringContainsString('direction: ltr;', $ltr[2]);
        $this->assertStringContainsString('text-align: start;', $ltr[2]);
        $this->assertStringContainsString('max-inline-size: var(--prose);', $ltr[2], 'one reading column on the page\'s start edge');
        // The note's size comes from the island rule (base.css), so a note that also takes a role class (.t-small)
        // keeps it: the styleguide's later layer would otherwise override the role.
        $this->assertSame(1, preg_match('/^\s*\.sg-note \{([^}]*)\}/m', $css, $note));
        $this->assertStringNotContainsString('font-size', $note[1]);
    }

    /** Splits $value at top-level $separator characters (outside parentheses). @return list<string> */
    private function topLevel(string $value, string $separator): array
    {
        $parts = [''];
        $depth = 0;
        foreach (str_split($value) as $char) {
            $depth += $char === '(' ? 1 : ($char === ')' ? -1 : 0);
            if ($char === $separator && $depth === 0) {
                $parts[] = '';

                continue;
            }
            $parts[count($parts) - 1] .= $char;
        }

        return $parts;
    }

    /** The characters of a selector outside its :where(…) groups. */
    private function outsideWhere(string $selector): string
    {
        $out = '';
        $depth = 0;
        for ($i = 0, $n = strlen($selector); $i < $n; $i++) {
            if ($depth === 0 && substr($selector, $i, 7) === ':where(') {
                $depth = 1;
                $i += 6;

                continue;
            }
            if ($depth > 0) {
                $depth += $selector[$i] === '(' ? 1 : ($selector[$i] === ')' ? -1 : 0);

                continue;
            }
            $out .= $selector[$i];
        }

        return $out;
    }

    /** brief §3.4: one container for header, sections and footer; the grid; the section rhythm; the focus ring. */
    public function test_layout_container_grid_sections_and_focus(): void
    {
        $t = $this->tokens();
        $this->assertSame(['1200px', '20px', '16px', '4', '64px', '24px'], [$t['container'], $t['pad-x'], $t['gutter'], $t['cols'], $t['section-y'], $t['pad-card']]);
        $this->assertSame('24px', $this->at(640)['pad-x']);
        $this->assertSame(['24px', '8', '96px', '32px'], [$this->at(768)['gutter'], $this->at(768)['cols'], $this->at(768)['section-y'], $this->at(768)['pad-card']]);
        $this->assertSame(['32px', '12', '128px'], [$this->at(1024)['pad-x'], $this->at(1024)['cols'], $this->at(1024)['section-y']]);
        $layout = $this->css('layout/layout.css');
        $this->assertStringContainsString('max-inline-size: calc(var(--container) + 2 * var(--pad-x));', $layout);
        $this->assertStringContainsString('grid-template-columns: repeat(var(--cols), minmax(0, 1fr));', $layout);
        $this->assertStringContainsString('.section--band', $layout);
        $this->assertStringContainsString('.section--dark', $layout);
        $html = $this->home('en');
        $this->assertMatchesRegularExpression('/class="[^"]*\bcontainer\b/', $this->between($html, '<header class="d2-header"', '</header>'), 'the header uses the container');
        $this->assertMatchesRegularExpression('/class="[^"]*\bcontainer\b/', $this->between($html, '<footer class="d2-footer', '</footer>'), 'the footer uses the container');
        // the focus ring: 2px, 2px offset, ink on light, light gold on dark
        $this->assertSame(['2px', '2px', 'var(--focus)'], [$t['focus-w'], $t['focus-offset'], $t['focus-color']]);
        $this->assertMatchesRegularExpression('/\.on-dark,\s*\.on-night\s*\{\s*--focus-color: var\(--focus-on-dark\);/', $this->css('tokens.css'));
        // no fade-up of sections on scroll (§3.6 rule 7)
        $this->assertDoesNotMatchRegularExpression('/\.reveal[^\n]*\{/', (string) preg_replace('#/\*.*?\*/#s', '', $this->css('base/motion.css')), 'no scroll reveal rule');
    }

    /** The admin panel builds its palette from the same tokens (legacy names resolve through their aliases). */
    public function test_the_admin_palette_reads_the_tokens(): void
    {
        $this->assertSame('#0B0B0C', AdminPalette::token('night'), 'legacy name');
        $this->assertSame('#F6F6F7', AdminPalette::token('band'));
        $colors = AdminPalette::colors();
        $this->assertSame('#8A6A2E', $colors['primary'][500]);
        $this->assertSame('#F6F6F7', $colors['gray'][50]);
        $this->assertSame('#2F5DA0', $colors['info'][500]);
        $this->assertSame('#E8F5EE', $colors['success'][50]);
    }
}
