<?php

namespace Tests\Feature\Design\D2;

use App\Design\D2\Icons;
use Database\Seeders\ContentSeeder;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Design 2.1 component library (docs/briefs/design-2.1-and-admin-cms.md §3.4 to §3.6, D03 to D06, D08 to D10;
 * contract docs/d2.1/phase-1/components.md; owner: components): buttons and their Design 2.0 aliases, the arrow rule,
 * chips without icons, badges with status icons, icons with their names, links, fields, overlays, cards, and the
 * styleguide areas that show them. The WhatsApp logo has its own test (WhatsAppLogoTest).
 */
final class ComponentsTest extends TestCase
{
    use RefreshDatabase;

    /** The component CSS files this area owns. */
    private const CSS = ['button', 'icon', 'chip', 'segmented', 'field', 'badge', 'kbd', 'progress', 'card', 'accordion',
        'tabs', 'sheet', 'modal', 'toast', 'tooltip', 'skeleton', 'callout', 'popover', 'carousel', 'upload'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->withoutVite();
        Icons::flush();
    }

    protected function tearDown(): void
    {
        Icons::flush();
        parent::tearDown();
    }

    /** Rendered Blade with the whitespace inside tags collapsed ("a  b >" becomes "a b>"), for exact comparisons. */
    private function render(string $blade): string
    {
        $html = Blade::render($blade);
        $html = (string) preg_replace_callback('/<[a-z][^>]*>/i', fn (array $m): string => (string) preg_replace(['/\s+/', '/\s+(\/?)>$/'], [' ', '$1>'], $m[0]), $html);

        return trim($html);
    }

    /** A component CSS file without its comments. */
    private function css(string $name): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path("css/d2/components/{$name}.css")));
    }

    /** @return list<string> the data-icon values of an HTML fragment (sprite icons and brand marks), in order */
    private function iconNames(string $html): array
    {
        preg_match_all('/<(?:svg|span)\b[^>]*\bdata-icon="([^"]+)"/', $html, $m);

        return $m[1];
    }

    public function test_button_variants_keep_their_class_names_and_alias_the_design_2_1_variants(): void
    {
        foreach (['ink', 'primary', 'gold', 'signature', 'secondary', 'text', 'ghost', 'night', 'whatsapp'] as $variant) {
            $html = $this->render('<x-d2.ui.button variant="'.$variant.'">Label</x-d2.ui.button>');
            $this->assertStringStartsWith('<button class="btn btn--'.$variant.' btn--48" type="button">', $html, $variant);
        }
        $this->assertStringStartsWith('<button class="btn btn--primary btn--48"', $this->render('<x-d2.ui.button>Label</x-d2.ui.button>'), 'the default value stays primary (= ink)');
        $this->assertStringStartsWith('<button class="btn btn--primary btn--48"', $this->render('<x-d2.ui.button variant="metal">Label</x-d2.ui.button>'), 'unknown: the ink default');
        foreach ([40, 44, 48, 52, 56] as $size) {
            $this->assertStringContainsString('btn--'.$size.'"', $this->render('<x-d2.ui.button size="'.$size.'">Label</x-d2.ui.button>'));
        }
        $this->assertStringContainsString('btn--48"', $this->render('<x-d2.ui.button size="64">Label</x-d2.ui.button>'));

        // The look: one rule per variant and its alias; ink is the base, gold is flat, the hero size is 52 px.
        $css = $this->css('button');
        $this->assertMatchesRegularExpression('/\.btn \{[^}]*--btn-h: var\(--button-h\);[^}]*--btn-pad: var\(--s-5\);[^}]*--btn-bg: var\(--ink\);[^}]*--btn-fg: var\(--canvas\);/s', $css, 'ink is the default look');
        $this->assertMatchesRegularExpression('/\.btn \{[^}]*border-radius: var\(--radius-control\);[^}]*box-shadow: none;[^}]*font-size: var\(--fs-ui\);[^}]*font-weight: var\(--fw-medium\);/s', $css);
        $this->assertMatchesRegularExpression('/:is\(\.btn--ink, \.btn--primary\)\s*\{/', $css);
        $this->assertMatchesRegularExpression('/:is\(\.btn--gold, \.btn--signature\)\s*\{\s*--btn-bg: var\(--gold\);\s*--btn-fg: var\(--ink\);/', $css);
        $this->assertMatchesRegularExpression('/:is\(\.btn--secondary, \.btn--whatsapp\) \{\s*--btn-bg: var\(--canvas\);\s*--btn-fg: var\(--ink\);\s*--btn-edge: var\(--line-strong\);/', $css);
        $this->assertMatchesRegularExpression('/:is\(\.btn--text, \.btn--ghost\)\s*\{[^}]*--btn-pad: 0;[^}]*text-decoration-line: underline;[^}]*text-decoration-thickness: 1px;\s*text-underline-offset: 3px;/s', $css);
        $this->assertMatchesRegularExpression('/:is\(\.btn--52, \.btn--56\)\s*\{\s*--btn-h: var\(--button-h-hero\);\s*--btn-pad: var\(--s-6\);\s*font-size: var\(--fs-minor\);/', $css, 'hero: 52 px, padding 24, 16/500');
        // Hover: an 8% lighter or darker wash whose opacity fades; press: a 120 ms scale(.98); no lift.
        $this->assertStringContainsString('--btn-wash: var(--dark-hover-tint);', $css, 'ink 8% lighter');
        $this->assertStringContainsString('--btn-wash: var(--hover-tint);', $css, 'white and gold 8% darker');
        $this->assertStringContainsString('transition: opacity var(--dur-hover) var(--ease-out);', $css);
        $this->assertStringContainsString('--btn-scale: var(--press-scale);', $css);
        $this->assertStringContainsString('transition: scale var(--dur-press) var(--ease-out);', $css);
        $this->assertDoesNotMatchRegularExpression('/translateY|--lift|--metal|gradient\(/', $css);
        $this->assertMatchesRegularExpression('/\.btn \{(?:(?!translate)[^}])*\}/', $css, 'the button itself never moves (only the icon button\'s tip slides in)');
        // Ink inverts on a dark surface (white, ink label).
        $this->assertMatchesRegularExpression('/:is\(\.on-dark, \.on-night\) :is\(\.btn--ink, \.btn--primary\) \{\s*--btn-bg: var\(--on-dark\);\s*--btn-fg: var\(--ink\);/', $css);
    }

    public function test_buttons_drop_decorative_arrows_and_keep_the_allowed_ones(): void
    {
        foreach (Icons::ARROWS as $arrow) {
            Icons::flush();
            $html = $this->render('<x-d2.ui.button icon="'.$arrow.'" icon-end="'.$arrow.'" href="/en/">Go</x-d2.ui.button>');
            $this->assertSame([], $this->iconNames($html), "{$arrow}: no icon at all (D03)");
            $this->assertStringNotContainsString('btn__icon', $html);
            $this->assertStringContainsString('<span class="btn__label">Go</span>', $html);
            $this->assertSame([], Icons::used(), "{$arrow}: no glyph reaches the sprite");
        }

        // The allowed arrows have their own names and draw the existing glyphs (one symbol per glyph).
        Icons::flush();
        $next = $this->render('<x-d2.ui.button icon-end="form-next">Next</x-d2.ui.button>');
        $this->assertStringContainsString('<svg class="icon mirror-rtl" width="20" height="20" fill="none" data-icon="form-next" aria-hidden="true"><use href="#d2i-arrow"/></svg>', $next, 'the form\'s Next: the arrow glyph under its own name');
        $back = $this->render('<x-d2.ui.button variant="secondary" icon="form-back">Back</x-d2.ui.button>');
        $this->assertSame(['form-back'], $this->iconNames($back));
        $this->assertStringContainsString('<use href="#d2i-arrow-start"/>', $back);
        foreach (['carousel-prev' => 'chevron-start', 'carousel-next' => 'chevron'] as $name => $glyph) {
            $html = $this->render('<x-d2.ui.icon-button icon="'.$name.'" label="Card" />');
            $this->assertSame([$name], $this->iconNames($html));
            $this->assertStringContainsString('<use href="#d2i-'.$glyph.'"/>', $html);
        }
        $this->assertSame(['arrow', 'arrow-start', 'chevron-start', 'chevron'], Icons::used(), 'aliases record the glyph files, not new symbols');

        $this->assertSame(['external'], $this->iconNames($this->render('<x-d2.ui.button icon-end="external" href="https://example.test/" target="_blank">Site</x-d2.ui.button>')), 'another website: the ↗ SVG');
        $this->assertSame(['chevron-down'], $this->iconNames($this->render('<x-d2.ui.button variant="text" icon-end="chevron-down" aria-expanded="false">More</x-d2.ui.button>')), 'a disclosure keeps its chevron');
        $this->assertSame(['search'], $this->iconNames($this->render('<x-d2.ui.button icon="search">Search</x-d2.ui.button>')));
    }

    public function test_the_whatsapp_button_is_the_white_secondary_button_with_the_logo(): void
    {
        $html = $this->render('<x-d2.ui.button variant="whatsapp" href="https://wa.me/971524666191?text=x">Contact Us on WhatsApp</x-d2.ui.button>');
        $this->assertStringStartsWith('<a class="btn btn--whatsapp btn--48" href="https://wa.me/971524666191?text=x" target="_blank" rel="noopener noreferrer">', $html);
        $this->assertStringContainsString('<span class="btn__icon" aria-hidden="true"><span class="brand-mark brand-mark--whatsapp" data-brand="whatsapp" data-icon="whatsapp" aria-hidden="true">', $html, 'the logo is the default icon of the variant');
        $this->assertStringNotContainsString('d2i-whatsapp', $html, 'never a drawn glyph');
        $this->assertStringContainsString('(opens in a new tab)', $html);
        $this->assertNotContains('whatsapp', Icons::names(), 'the stroke-drawn imitation is gone from the set');
        $this->assertFileDoesNotExist(resource_path('svg/d2/icons/whatsapp.svg'));

        $all = implode("\n", array_map($this->css(...), self::CSS));
        $this->assertStringNotContainsString('var(--whatsapp)', $all, 'nothing recolours the mark, and never green text or a green fill');
        // The logo images are never recoloured (only the temporary placeholder takes a grey from its surface).
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $this->css('icon'), $rules, PREG_SET_ORDER);
        $logoRules = array_filter($rules, fn (array $r): bool => str_contains($r[1], 'brand-mark') && ! str_contains($r[1], 'placeholder'));
        $this->assertNotEmpty($logoRules);
        foreach ($logoRules as [, $selector, $body]) {
            $this->assertDoesNotMatchRegularExpression('/(^|[;\s])(fill|filter|mask|mask-image|color|mix-blend-mode|background):/', $body, trim($selector).': the logo files are never recoloured');
        }
    }

    public function test_chips_are_one_pill_without_icons_or_dots(): void
    {
        $html = $this->render('<x-d2.ui.chip icon="contract" count="6" :pressed="true">Employment Law</x-d2.ui.chip>');
        $this->assertMatchesRegularExpression('/^<button class="chip chip--filter" type="button" aria-pressed="true">\s*<span class="chip__label">Employment Law<\/span>\s*<span class="chip__count">6<\/span><\/button>$/', $html);
        foreach (['choice', 'hotspot', 'night'] as $variant) {
            $chip = $this->render('<x-d2.ui.chip variant="'.$variant.'" icon="file">Label</x-d2.ui.chip>');
            $this->assertStringNotContainsString('chip__pin', $chip, $variant);
            $this->assertSame([], $this->iconNames($chip), $variant);
        }

        $css = $this->css('chip');
        $this->assertMatchesRegularExpression('/\.chip \{[^}]*--chip-bg: var\(--canvas\);[^}]*--chip-edge: var\(--line-strong\);[^}]*--chip-count: var\(--ink-3\);[^}]*min-block-size: var\(--chip-h\);[^}]*border-radius: var\(--radius-pill\);/s', $css);
        $this->assertMatchesRegularExpression('/\.chip:is\(\[aria-pressed="true"\], \[aria-current="true"\], \.is-pressed\) \{\s*--chip-bg: var\(--ink\);\s*--chip-fg: var\(--on-dark\);/', $css, 'selected: ink fill, white label');
        $this->assertStringNotContainsString('chip__pin', $css);
        $this->assertStringNotContainsString('chip__icon', $css);
        // Round 2 (§3.5 chips, §3.2 figures): a count beside a label is not a column of figures, so it takes
        // proportional lining figures (Onest's tabular "1" is as wide as a "0": "All 11" read "All 1 1").
        $this->assertMatchesRegularExpression('/\.chip__count \{[^}]*color: var\(--chip-count\);[^}]*font-variant-numeric: lining-nums proportional-nums;/s', $css);
        $this->assertStringNotContainsString('tabular-nums', $css, 'no tabular figures in a chip');
    }

    public function test_badges_use_the_brief_pairs_and_status_badges_show_an_icon(): void
    {
        foreach (['info' => 'info', 'success' => 'check', 'warning' => 'warning', 'danger' => 'error'] as $tone => $icon) {
            $html = $this->render('<x-d2.ui.badge tone="'.$tone.'">Status</x-d2.ui.badge>');
            $this->assertStringContainsString('class="badge badge--'.$tone.'"', $html);
            $this->assertSame([$icon], $this->iconNames($html), "{$tone}: icon and text");
            $this->assertStringContainsString('Status', strip_tags($html));
        }
        $this->assertSame([], $this->iconNames($this->render('<x-d2.ui.badge tone="success" :icon="false">Done</x-d2.ui.badge>')));
        $this->assertSame([], $this->iconNames($this->render('<x-d2.ui.badge>Note</x-d2.ui.badge>')), 'neutral: a label, not a status');
        $this->assertStringContainsString('class="badge badge--brand"', $this->render('<x-d2.ui.badge tone="bronze">Brand</x-d2.ui.badge>'), 'bronze = brand');

        $css = $this->css('badge');
        $this->assertMatchesRegularExpression('/\.badge \{[^}]*min-block-size: var\(--badge-h\);[^}]*border-radius: var\(--radius-control\);[^}]*font-size: var\(--fs-caption\);[^}]*font-weight: var\(--fw-medium\);/s', $css);
        foreach (['neutral', 'info', 'success', 'warning', 'danger', 'brand'] as $pair) {
            $this->assertStringContainsString("var(--badge-{$pair}-bg)", $css);
            $this->assertStringContainsString("var(--badge-{$pair}-fg)", $css);
        }
    }

    public function test_icons_are_20_px_outline_glyphs_that_print_their_name(): void
    {
        $this->assertSame('<svg class="icon" width="20" height="20" fill="none" data-icon="search" aria-hidden="true"><use href="#d2i-search"/></svg>', $this->render('<x-d2.ui.icon name="search" duotone />'), '20 px by default; duotone is retired');
        $this->assertStringContainsString('data-icon="phone" role="img" aria-label="Phone">', $this->render('<x-d2.ui.icon name="phone" label="Phone" />'));
        $this->assertStringContainsString('class="icon mirror-rtl"', $this->render('<x-d2.ui.icon name="external" />'));

        foreach (['search', 'menu', 'close', 'chevron-down', 'external', 'phone', 'mail', 'clock', 'check', 'cross', 'upload', 'info', 'warning', 'error', 'form-back', 'form-next', 'carousel-prev', 'carousel-next'] as $name) {
            $this->assertTrue(Icons::allowed($name), $name);
        }
        foreach (['briefcase', 'contract', 'scales', 'globe', 'arrow', 'arrow-start', 'home', 'file', 'shield', 'tools', 'whatsapp'] as $name) {
            $this->assertFalse(Icons::allowed($name), "{$name} is not a functional glyph of the set");
        }
        foreach (Icons::ALLOWED as $name) {
            $this->assertFileExists(resource_path('svg/d2/icons/'.Icons::glyph($name).'.svg'), "{$name} draws a glyph of the set");
        }
        $this->assertTrue(Icons::isArrow('arrow') && Icons::isArrow('chevron-start'));
        $this->assertFalse(Icons::isArrow('chevron-down') || Icons::isArrow('form-next') || Icons::isArrow('carousel-next'));
        $this->assertTrue(Icons::isBrand('whatsapp'));
        $this->assertSame('M7.25 16.75 16.75 7.25', (string) preg_replace('/^.*?<path d="([^"]+)".*$/s', '$1', Icons::inner('external')), 'the external mark is a plain ↗');

        $css = $this->css('icon');
        $this->assertStringContainsString('--icon-stroke: 1.5px;', $css);
        $this->assertStringNotContainsString('icon--duotone', $css);
        $this->assertStringNotContainsString('--duotone-fill', $css);
        $this->assertMatchesRegularExpression('/\.icon--muted \{\s*color: var\(--ink-2\);/', $css, 'a standalone functional icon is ink-2');
    }

    public function test_links_body_nav_and_external(): void
    {
        $this->assertSame('<a class="link" href="/en/privacy-policy/">Privacy Policy</a>', $this->render('<x-d2.ui.link href="/en/privacy-policy/">Privacy Policy</x-d2.ui.link>'));
        $this->assertSame('<a class="nav-link" href="/en/" aria-current="page">Home</a>', $this->render('<x-d2.ui.link variant="nav" href="/en/" :current="true">Home</x-d2.ui.link>'));
        $external = $this->render('<x-d2.ui.link href="https://laborlawuae.com" external>Employment Law</x-d2.ui.link>');
        $this->assertStringStartsWith('<a class="link link--external" href="https://laborlawuae.com" target="_blank" rel="noopener noreferrer">Employment Law', $external);
        $this->assertSame(['external'], $this->iconNames($external), 'the ↗ is an SVG, never a character');
        $this->assertStringNotContainsString('↗', strip_tags($external));
        $this->assertStringContainsString('<span class="sr-only" data-copy-status="proposed">(opens in a new tab)</span>', $external);
        $this->assertMatchesRegularExpression('#Employment Law<span class="link__external"><svg class="icon mirror-rtl" width="16" height="16"[^>]*><use href="\#d2i-external"/></svg>\s*</span><span class="sr-only" data-copy-status="proposed">\(opens in a new tab\)</span></a>$#', $external, 'the ↗ in an inline-block right after the text: no underlined trailing space');

        $base = (string) file_get_contents(resource_path('css/d2/base/base.css'));
        $this->assertStringContainsString('.link', $base, 'body links: base.css');
        $this->assertStringContainsString('.nav-link', $base, 'nav links: base.css');
    }

    public function test_fields_take_the_error_icon_and_fill_their_column(): void
    {
        $html = $this->render('<x-d2.ui.field id="f-name" label="Name" error="Please enter your name."><x-d2.ui.input name="name" /></x-d2.ui.field>');
        $this->assertSame(['check', 'error'], $this->iconNames($html), 'the input\'s valid check, then the error icon under the field');
        $this->assertStringContainsString('aria-describedby="f-name-error" aria-invalid="true"', $html);
        $this->assertMatchesRegularExpression('#</span>\s*<p class="field__error" id="f-name-error" data-field-error>#', $html, 'the error under the field');

        $css = $this->css('field');
        $this->assertMatchesRegularExpression('/:is\(\.input, \.select, \.textarea\) \{[^}]*min-block-size: var\(--field-h\);[^}]*border: var\(--line-w\) solid var\(--ctl-edge\);[^}]*border-radius: var\(--radius-control\);[^}]*box-shadow: none;/s', $css);
        $this->assertStringContainsString('--ctl-edge-rest: var(--field-border);', $css);
        $this->assertMatchesRegularExpression('/\.field__label \{[^}]*font-size: var\(--fs-ui\);[^}]*font-weight: var\(--fw-medium\);/s', $css, 'label 15/500');
        $this->assertMatchesRegularExpression('/\.field__hint \{[^}]*color: var\(--ctl-muted\);[^}]*font-size: var\(--fs-small\);/s', $css, 'help: Small, ink-3');
        $this->assertMatchesRegularExpression('/\.field__error \{[^}]*color: var\(--ctl-danger\);/s', $css);
        $this->assertMatchesRegularExpression('/\.stepper \{[^}]*inline-size: 100%;[^}]*min-block-size: var\(--field-h\);/s', $css, 'D33: the stepper fills its column');
        $this->assertStringNotContainsString('max-inline-size: 15rem', $css, 'D33: no stepper cap');
        $this->assertStringNotContainsString('min-inline-size: 10rem', $css, 'D33: the date field fills its column');
        $this->assertStringNotContainsString('ellipsis', $css, 'D09: a select never cuts its option with "…"');
    }

    /**
     * Round 2 (D09, brief §3.6 rule 8): a long placeholder is never cut at the field's edge. With wrap-placeholder the
     * input keeps its placeholder (the accessible one) and a copy is drawn inside the field as text that wraps; it
     * shows only while the field is empty (:placeholder-shown, no script), the wrapper draws the field around the
     * borderless input, and the geometry is the FAQ search box's (one line fills 48 px; Arabic 8 + 30 + 8).
     */
    public function test_a_long_placeholder_wraps_inside_the_field_instead_of_being_cut(): void
    {
        $line = 'Enter a word such as: cheque, salary, will, tax';
        $html = $this->render('<x-d2.ui.field id="f-q" label="Search" :label-hidden="true"><x-d2.ui.input name="q" type="search" icon="search" placeholder="'.$line.'" wrap-placeholder /></x-d2.ui.field>');
        $this->assertMatchesRegularExpression('#<span class="control control--icon control--search control--wrap" data-control-wrap>#', $html, 'the wrapper is the field');
        $this->assertMatchesRegularExpression('#<input class="input" id="f-q" name="q" placeholder="'.preg_quote($line, '#').'" type="search">\s*<span class="control__placeholder" aria-hidden="true">'.preg_quote($line, '#').'</span>#', $html, 'the placeholder stays the accessible one; its copy follows the input');
        $this->assertStringNotContainsString('wrap-placeholder', $html, 'a prop, never an attribute of the input');
        $this->assertSame(['search', 'check'], $this->iconNames($html));

        // Off unless asked, and only for a 48 px field with a placeholder.
        foreach ([
            '<x-d2.ui.input name="q" placeholder="'.$line.'" />',
            '<x-d2.ui.input name="q" wrap-placeholder />',
            '<x-d2.ui.input name="q" placeholder=" " wrap-placeholder />',
            '<x-d2.ui.input name="q" size="40" placeholder="'.$line.'" wrap-placeholder />',
        ] as $blade) {
            $plain = $this->render($blade);
            $this->assertStringNotContainsString('control--wrap', $plain, $blade);
            $this->assertStringNotContainsString('control__placeholder', $plain, $blade);
        }

        $css = $this->css('field');
        $this->assertMatchesRegularExpression('/\.control__placeholder \{\s*display: none;\s*\}/', $css, 'hidden where :has() is missing (the native placeholder stays)');
        $this->assertMatchesRegularExpression('/@supports selector\(:has\(\*\)\) \{\s*\.control--wrap \{[^}]*border: var\(--line-w\) solid var\(--ctl-edge\);[^}]*border-radius: var\(--radius-control\);[^}]*background: var\(--ctl-bg\);/s', $css, 'the wrapper draws the field');
        $this->assertMatchesRegularExpression('/\.control--wrap > \.input \{[^}]*min-block-size: calc\(var\(--field-h\) - 2 \* var\(--line-w\)\);[^}]*border: 0;[^}]*background: transparent;/s', $css, 'a borderless 46 px input');
        $this->assertMatchesRegularExpression('/\.control--wrap > \.input \{[^{}]*(?:\{[^{}]*\}[^{}]*)*&::placeholder \{\s*opacity: 0;\s*\}/s', $css, 'whose own placeholder is not painted (the copy shows instead)');
        $this->assertMatchesRegularExpression('/\.control--wrap > \.control__placeholder \{\s*--hint-pad: var\(--s-3\);[^}]*line-height: calc\(var\(--field-h\) - 2 \* var\(--line-w\) - 2 \* var\(--hint-pad\)\);[^}]*overflow-wrap: break-word;[^}]*text-wrap: balance;[^}]*pointer-events: none;/s', $css, 'one line fills 48 px; more lines add one line height');
        $this->assertMatchesRegularExpression('/\.control--wrap > \.control__placeholder:lang\(ar\) \{\s*--hint-pad: var\(--s-2\);/', $css, 'Arabic 8 + 30 + 8');
        $this->assertMatchesRegularExpression('/\.control--wrap:has\(> \.input:placeholder-shown\) > \.control__placeholder \{\s*display: block;/', $css, 'only while the field is empty');
        $this->assertMatchesRegularExpression('/\.control--wrap:has\(> \.input:is\(:focus-visible, \.is-focus\)\) \{\s*outline: var\(--focus-w\) solid var\(--focus-color\);\s*outline-offset: var\(--focus-offset\);/', $css, 'the 2px ring on the field');
        $this->assertMatchesRegularExpression('/\.control--wrap > :is\(\.control__icon, \.control__status\) \{\s*inset-block: 0 auto;\s*block-size: calc\(var\(--field-h\) - 2 \* var\(--line-w\)\);/', $css, 'the icons stay on the first line');

        $script = (string) file_get_contents(resource_path('js/d2/modules/control-wrap.js'));
        $this->assertStringContainsString("closest('[data-control-wrap]')", $script, 'a press anywhere in the field focuses it');
    }

    public function test_overlays_carry_data_overlay_the_overlay_radius_and_the_one_shadow(): void
    {
        $this->assertStringStartsWith('<div class="modal modal--static" data-overlay="data-overlay"', $this->render('<x-d2.ui.modal id="t" title="T" :static="true">Body</x-d2.ui.modal>'));
        $this->assertStringStartsWith('<dialog class="modal" data-overlay="data-overlay" id="modal-t"', $this->render('<x-d2.ui.modal id="t" title="T">Body</x-d2.ui.modal>'));
        $this->assertStringStartsWith('<div class="sheet" data-overlay="data-overlay" data-sheet="s" hidden>', $this->render('<x-d2.ui.sheet id="s" title="S">Body</x-d2.ui.sheet>'));
        $this->assertStringContainsString('popover="manual" data-overlay data-placement="top"', $this->render('<x-d2.ui.tooltip text="Tip"><button type="button">B</button></x-d2.ui.tooltip>'));
        $this->assertStringContainsString('<span class="icon-btn__tip icon-btn__tip--top" aria-hidden="true" data-overlay>Close</span>', $this->render('<x-d2.ui.icon-button icon="close" label="Close" />'));
        $toast = (string) file_get_contents(resource_path('js/d2/lib/toast.js'));
        $this->assertStringContainsString("el.setAttribute('data-overlay', '');", $toast);
        $this->assertStringNotContainsString('toast__dot', $toast, 'no tone dot');
        $this->assertStringContainsString("bubble.setAttribute('data-overlay', '');", (string) file_get_contents(resource_path('js/d2/modules/tooltip.js')));

        foreach (['modal' => '.modal__panel', 'sheet' => '.sheet__panel', 'popover' => '.popover', 'toast' => '.toast', 'tooltip' => '.tooltip'] as $file => $selector) {
            $this->assertMatchesRegularExpression('/'.preg_quote($selector, '/').' \{[^}]*(border-radius|border-start-start-radius): var\(--radius-overlay\);[^}]*box-shadow: var\(--shadow-overlay\);/s', $this->css($file), $file);
        }
        $this->assertMatchesRegularExpression('/\.icon-btn__tip \{[^}]*border-radius: var\(--radius-overlay\);[^}]*box-shadow: var\(--shadow-overlay\);/s', $this->css('button'));
    }

    public function test_cards_never_move_and_turn_their_edge_ink_on_hover(): void
    {
        $css = $this->css('card');
        $this->assertMatchesRegularExpression('/\.card \{[^}]*--card-pad: var\(--pad-card\);[^}]*--card-edge: var\(--line\);[^}]*border-radius: var\(--radius-card\);[^}]*box-shadow: none;/s', $css);
        $this->assertMatchesRegularExpression('/\.card--interactive:hover \{\s*border-color: var\(--card-edge-hover\);\s*& \.card__title \{\s*text-decoration-line: underline;/', $css);
        $this->assertStringContainsString('--card-edge-hover: var(--ink);', $css);
        $this->assertDoesNotMatchRegularExpression('/transform|translate|scale|--lift|box-shadow: var/', $css, 'no lift, no zoom, no shadow (D05)');

        $html = $this->render('<x-d2.ui.card href="/en/practice-areas/labor-law/" title="Employment Law"><p class="card__text">Summary</p></x-d2.ui.card>');
        $this->assertSame(1, substr_count($html, '<a '), 'one link with one accessible name');
        $this->assertMatchesRegularExpression('#<h3 class="card__title">\s*<a class="card__link" href="/en/practice-areas/labor-law/">Employment Law</a>\s*</h3>#', $html);
    }

    public function test_tabs_segmented_and_accordion_follow_the_brief(): void
    {
        $tabs = $this->css('tabs');
        $this->assertMatchesRegularExpression('/&::after \{[^}]*block-size: 2px;[^}]*background: var\(--ink\);/s', $tabs, 'underline tabs: a 2px ink indicator');
        $this->assertStringNotContainsString('var(--gold', $tabs);

        $segmented = $this->css('segmented');
        $this->assertStringContainsString('--seg-track: var(--band);', $segmented);
        $this->assertStringContainsString('--seg-thumb: var(--canvas);', $segmented);
        $this->assertStringContainsString('--seg-thumb-edge: var(--line-strong);', $segmented, 'the thumb has an edge, not a shadow');
        $this->assertStringContainsString('translate var(--spring-snappy-dur) var(--spring-snappy)', $segmented, 'the thumb slides on the ~200 ms spring');

        $accordion = $this->css('accordion');
        $this->assertMatchesRegularExpression('/\.acc__title \{[^}]*font-size: var\(--fs-h3\);[^}]*font-weight: var\(--fw-semibold\);/s', $accordion, 'questions in the H3 role');
        $this->assertMatchesRegularExpression('/\.acc--card \{[^}]*border-radius: var\(--radius-card\);/s', $accordion);
    }

    public function test_component_css_holds_no_banned_pattern(): void
    {
        foreach (self::CSS as $name) {
            $css = $this->css($name);
            $this->assertDoesNotMatchRegularExpression('/gradient\(/', $css, "{$name}: no gradient");
            preg_match_all('/box-shadow:\s*([^;]+);/', $css, $shadows);
            foreach ($shadows[1] as $value) {
                $this->assertContains(trim($value), ['none', 'var(--shadow-overlay)'], "{$name}: shadows only on overlays");
            }
            foreach (['--font-mono', '--metal', '--lift', '--shadow-btn', '--shadow-ctl', '--shadow-seg', '--duotone', '--shimmer', '--glass', 'backdrop-filter', 'uppercase', 'ellipsis', 'line-clamp', '--fs-meta', '--fw-ui', '--fw-bold', '--r-sm', '--r-md', '--r-lg', '--r-xl', '--bronze-wash'] as $legacy) {
                $this->assertStringNotContainsString($legacy, $css, "{$name}: {$legacy} (Design 2.0 or banned)");
            }
            if ($name !== 'button') {
                $this->assertStringNotContainsString('var(--gold', $css, "{$name}: gold only on the hero's button");
            }
        }
    }

    public function test_the_styleguide_areas_show_every_component_in_every_language(): void
    {
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = (string) $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/styleguide/")->assertOk()->getContent();
            foreach (['icons', 'controls', 'surfaces', 'mobile', 'upload'] as $area) {
                $this->assertStringContainsString('<section class="sg-section" id="sg-'.$area.'">', $html, "{$locale} {$area}");
            }
            foreach (['btn-ink', 'btn-gold', 'btn-secondary', 'btn-text', 'btn-whatsapp', 'btn-aliases', 'links', 'ib-ghost', 'chip-filter', 'segmented', 'input', 'input-values', 'input-wrap', 'field-columns', 'checkbox', 'radio', 'badges', 'steps', 'controls-dark', 'icon-set', 'icon-arrows', 'whatsapp-light', 'whatsapp-dark', 'cards', 'tabs'] as $hook) {
                $this->assertStringContainsString('data-sg="'.$hook.'"', $html, "{$locale}: {$hook}");
            }

            // Round 2 (D09): the specimens show their approved values in full. The two search fields wrap the approved
            // FAQ example inside the field (as the live FAQ search does) instead of cutting it; the e-mail and telephone
            // sit in the wide grid, whose columns hold the approved e-mail (a 238 px cell cut it at 1440 px).
            $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
            foreach (['sg-in-search', 'sg-dark-search'] as $id) {
                $input = $document->getElementById($id);
                $this->assertNotNull($input, "{$locale}: #{$id}");
                $this->assertSame(ui('faq.search_placeholder', $locale), $input->getAttribute('placeholder'), "{$locale}: #{$id} keeps the approved placeholder");
                $control = $input->parentElement;
                $this->assertTrue($control->classList->contains('control--wrap') && $control->hasAttribute('data-control-wrap'), "{$locale}: #{$id} wraps its placeholder");
                $this->assertSame(ui('faq.search_placeholder', $locale), trim((string) $control->querySelector('.control__placeholder[aria-hidden="true"]')?->textContent), "{$locale}: #{$id} draws the whole approved line");
            }
            foreach (['sg-in-success' => setting('email'), 'sg-in-optional' => setting('phone_display')] as $id => $value) {
                $input = $document->getElementById($id);
                $this->assertSame($value, $input?->getAttribute('value'), "{$locale}: #{$id}");
                $this->assertNotNull($input->closest('.sg-grid--wide[data-sg="input-values"]'), "{$locale}: #{$id} in a column wide enough for its value");
            }

            // This area's specimens: functional icons only, no middle-dot strings, no text that ends in an ellipsis.
            // The tab bar inside the mobile area is the shell's component (it reports its own icons).
            $areas = '';
            foreach (['icons', 'controls', 'surfaces', 'mobile', 'upload'] as $area) {
                $start = (int) strpos($html, 'id="sg-'.$area.'"');
                $areas .= substr($html, $start, (int) strpos($html, '</section>', $start) - $start);
            }
            $mine = (string) preg_replace('#<nav class="tabbar\b.*?</nav>#s', '', $areas);
            foreach (array_unique($this->iconNames($mine)) as $name) {
                $this->assertTrue(Icons::allowed($name) || Icons::isBrand($name), "{$locale}: {$name} is not a functional icon");
            }
            $text = html_entity_decode(strip_tags((string) preg_replace('#<(script|style|template)\b.*?</\1>#s', '', $mine)));
            $this->assertStringNotContainsString(' · ', $text, "{$locale}: no middle-dot strings (D02)");
            $this->assertDoesNotMatchRegularExpression('/(…|\.\.\.)\s*$/mu', $text, "{$locale}: no text ends with an ellipsis");
            $this->assertStringContainsString('data-brand="whatsapp"', $mine);
            $this->assertStringNotContainsString('d2i-whatsapp', $html, "{$locale}: the WhatsApp mark is never a sprite glyph");
        }
    }

    /**
     * Lint rule 13 (qa/README.md): a styleguide gallery that shows icons out of their context carries
     * data-lint-specimen, so the lint checks each name there but not its place. Every icon of #sg-icons that is
     * allowed only in one place (clock: the contact block; external: a link to another website; minus / plus: a
     * stepper; calendar: a date field; the "go" chevrons: never on a link or card) sits in such a gallery, and the
     * "In context" buttons and link to another website stay unmarked, so their places are still checked.
     */
    public function test_the_icon_galleries_mark_their_out_of_context_specimens_for_the_lint(): void
    {
        $placed = ['clock', 'minus', 'plus', 'calendar', 'chevron', 'chevron-start'];
        $origin = (string) parse_url(url('/'), PHP_URL_HOST);
        foreach (['en', 'ar', 'ru'] as $locale) {
            $html = (string) $this->withUnencryptedCookies(['summit_design' => '2'])->get("/{$locale}/styleguide/")->assertOk()->getContent();
            $section = HTMLDocument::createFromString($html, LIBXML_NOERROR)->getElementById('sg-icons');
            $this->assertNotNull($section, "{$locale}: #sg-icons");

            foreach (['icon-set', 'icon-arrows', 'icon-sizes'] as $hook) {
                $this->assertTrue((bool) $section->querySelector('[data-sg="'.$hook.'"]')?->hasAttribute('data-lint-specimen'), "{$locale}: the {$hook} gallery is a specimen");
            }
            $rows = $section->querySelectorAll('.sg-states');
            $this->assertCount(3, $rows, "{$locale}: the three direction rows");
            foreach ($rows as $row) {
                $this->assertTrue($row->hasAttribute('data-lint-specimen'), "{$locale}: a direction row is a specimen");
            }

            $marked = 0;
            foreach ($section->querySelectorAll('[data-icon]:not([data-brand])') as $icon) {
                $name = (string) $icon->getAttribute('data-icon');
                $this->assertTrue(Icons::allowed($name), "{$locale}: {$name} is a functional icon");
                $host = (string) parse_url((string) $icon->closest('a[href]')?->getAttribute('href'), PHP_URL_HOST);
                $inPlace = $name === 'external' && $host !== '' && $host !== $origin;
                if (in_array($name, $placed, true) || ($name === 'external' && ! $inPlace)) {
                    $this->assertNotNull($icon->closest('[data-lint-specimen]'), "{$locale}: {$name} out of its context sits in a specimen gallery");
                    $marked++;
                }
            }
            $this->assertGreaterThan(0, $marked);
            $this->assertNotNull($section->querySelector('[data-lint-specimen] [data-icon="clock"].icon--inline'), "{$locale}: the clock beside the hours sentence");

            $context = $section->querySelector('[data-sg="icon-context"]');
            $this->assertNotNull($context);
            $this->assertGreaterThanOrEqual(3, $context->querySelectorAll('a[href] [data-icon]')->length, "{$locale}: the buttons and the external link");
            foreach ($context->querySelectorAll('a[href] [data-icon]') as $icon) {
                $this->assertNull($icon->closest('[data-lint-specimen]'), "{$locale}: an icon in context keeps its place checked");
            }
        }
    }
}
