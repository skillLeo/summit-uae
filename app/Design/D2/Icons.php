<?php

namespace App\Design\D2;

/**
 * Design 2 icon set: resources/svg/d2/icons/{name}.svg (24 px grid, 1.5 px stroke, round caps and
 * joins). Shapes marked class="duo" were meant for the Design 2.0 duotone fill, which Design 2.1 retired
 * (§3.5: outline icons only); class="dot" marks zero-length strokes drawn with the heavier dot pen.
 *
 * Design 2.1 (brief §3.5, §3.6 rule 5; contract docs/d2.1/phase-1/components.md §3):
 * - Icons are functional only, 20 px, 1.5 px pen. ALLOWED lists the names the design lint accepts (rule 13);
 *   every <x-d2.ui.icon> carries data-icon="<name>" so the lint can check it. Everything else in the set
 *   (the service objects, briefcase, globe …) is decorative and on its way out (D04): the areas remove them.
 * - Arrows are banned in buttons, links and card corners (rule 3). ALIASES give the allowed exceptions their
 *   own names: carousel-prev / carousel-next (the carousel's previous and next), form-back / form-next (the
 *   form's Back and Next). They draw an existing glyph (one <symbol> per glyph) but print their own
 *   data-icon, so a decorative "arrow" and the form's "form-next" are told apart. <x-d2.ui.button> drops a
 *   decorative arrow or chevron (ARROWS) from its icon slots.
 * - The WhatsApp mark is NOT in this set: <x-d2.ui.icon name="whatsapp"> renders Meta's official logo files
 *   through <x-d2.ui.brand-mark> (App\Design\D2\WhatsAppLogo), never a drawn imitation (D10, §3.8).
 *
 * Sprite (platform, final pass): <x-d2.ui.icon> prints <svg …><use href="#d2i-{glyph}"/></svg> and records
 * the glyph with use(); App\Design\D2\IconSprite (outermost global middleware) then puts one hidden sprite
 * with a <symbol> per glyph right after <body> (withSprite()). Nothing reaches into a <use> shadow tree from
 * CSS, so everything the glyph needs is baked into its symbol: the pen in currentColor with round caps and
 * joins, vector-effect="non-scaling-stroke" on every shape (the pen stays 1.5 px at every size), stroke-width
 * 2.25 on dots, fill="none" on the shapes that are not class="duo". The rest reaches the shapes by inheritance
 * from the outer <svg> (components/icon.css): color (the pen) and stroke-width (--icon-stroke). inner() stays
 * the raw markup for SVG that draws a glyph inline (App\Design\D2\Cover).
 */
final class Icons
{
    /** Prefix of the sprite symbol ids (d2i-phone …). */
    public const SYMBOL_PREFIX = 'd2i-';

    /** Pen of the class="dot" shapes (components/icon.css --icon-dot), baked into the symbols. */
    public const DOT_STROKE = '2.25';

    /**
     * Functional icons (brief §3.6 rule 5) and the names of the allowed exceptions. The design lint (rule 13)
     * accepts these data-icon values; an icon is still banned inside a card title, chip, KPI tile or menu item.
     * - search, menu, close, the chevrons (disclosure and select chevrons, carousel and tab scroll), external
     *   link, phone, email, clock (contact block only), check and cross (useful steps, mistakes to avoid),
     *   upload, info, warning, error: the brief's list;
     * - carousel-prev / carousel-next, form-back / form-next: the arrows rule 3 allows;
     * - minus / plus (the stepper's own buttons) and calendar (the date field): control glyphs that are the
     *   control's only visible label;
     * - pause / play: the home hero's autoplay toggle (the owner's autoplay; WCAG 2.2.2 needs a visible pause),
     *   the control's only visible label.
     *
     * @var list<string>
     */
    public const ALLOWED = [
        'search', 'menu', 'close', 'cross',
        'chevron', 'chevron-start', 'chevron-down', 'chevron-up',
        'external', 'phone', 'mail', 'email', 'clock',
        'check', 'upload', 'info', 'warning', 'error',
        'carousel-prev', 'carousel-next', 'form-back', 'form-next',
        'minus', 'plus', 'calendar',
        'pause', 'play',
    ];

    /**
     * Explicit names that draw an existing glyph (alias => glyph file). The alias is what data-icon says.
     *
     * @var array<string, string>
     */
    public const ALIASES = [
        'carousel-prev' => 'chevron-start',
        'carousel-next' => 'chevron',
        'form-back' => 'arrow-start',
        'form-next' => 'arrow',
        'cross' => 'close',
        'email' => 'mail',
    ];

    /**
     * Arrows and "go" chevrons: decorative in a button, link or card corner (rule 3). <x-d2.ui.button> renders
     * nothing for these in its icon slots; the allowed exceptions use the ALIASES above. Disclosure chevrons
     * (chevron-down, chevron-up) are functional and stay.
     *
     * @var list<string>
     */
    public const ARROWS = ['arrow', 'arrow-start', 'chevron', 'chevron-start'];

    /** Names drawn by a brand mark (official third-party files, never a glyph of this set). */
    public const BRANDS = ['whatsapp'];

    /** @var array<string, true> glyphs printed since the last flush(), in first-use order */
    private static array $used = [];

    /**
     * Directional icons: they point along the reading direction, so they mirror in RTL.
     * Objects never mirror: phone, WhatsApp, check, clock, search, documents, the service objects.
     */
    public const MIRRORED = ['arrow', 'arrow-start', 'chevron', 'chevron-start', 'external',
        'list', // inner-kit: TOC list icon, its bullets sit on the inline-start side
    ];

    /**
     * Service code (config('summit.services')) => the object icon drawn for that service.
     * Decorative (D04): kept for the areas that still print them until Phase 2 removes them, and for the
     * generated guide covers (App\Design\D2\Cover). Never add a new use.
     *
     * @var array<string, string>
     */
    public const SERVICE = [
        'S01' => 'cheque',           // Debt Recovery and Cheque Cases
        'S02' => 'scales',           // Criminal Cases (scales, never a gavel)
        'S03' => 'coin-chip',        // Cryptocurrency and Digital Currency Cases
        'S04' => 'briefcase',        // Corporate and Commercial Law
        'S05' => 'building-key',     // Property and Tenancy Disputes
        'S06' => 'contract',         // Employment Law (the employment contract with its seal)
        'S07' => 'tax-receipt',      // Tax Disputes and Tax Compliance
        'S08' => 'family',           // Family Law and Wills
        'S09' => 'compliance-seal',  // Compliance and Regulatory Matters
        'S10' => 'translate',        // Legal Translation
        'S11' => 'stamp',            // Business Services (a stamp, never a handshake)
    ];

    /** @var array<string, string> */
    private static array $cache = [];

    /** The glyph file an icon name draws: an alias's glyph, or the name itself (path parts removed). */
    public static function glyph(string $name): string
    {
        $name = basename($name);

        return self::ALIASES[$name] ?? $name;
    }

    public static function inner(string $name): string
    {
        $glyph = self::glyph($name);

        return self::$cache[$glyph] ??= self::load($glyph);
    }

    /** Records the glyph for this response's sprite and returns its symbol id (for <use href="#…">). */
    public static function use(string $name): string
    {
        $glyph = self::glyph($name);
        self::$used[$glyph] = true;

        return self::symbolId($glyph);
    }

    public static function symbolId(string $name): string
    {
        return self::SYMBOL_PREFIX.(string) preg_replace('/[^A-Za-z0-9_-]/', '-', self::glyph($name));
    }

    /** @return list<string> the glyphs recorded since the last flush() */
    public static function used(): array
    {
        return array_keys(self::$used);
    }

    /** Forgets the recorded glyphs (IconSprite calls it before and after every request). */
    public static function flush(): void
    {
        self::$used = [];
    }

    /** One glyph as a <symbol> that renders like the inline glyph inside <svg class="icon">. */
    public static function symbol(string $name): string
    {
        $shapes = (string) preg_replace_callback('/<(path|circle|ellipse|rect|line|polyline|polygon)\b([^>]*?)(\/?)>/', function (array $m): string {
            $attrs = $m[2];
            $classes = preg_match('/\bclass="([^"]*)"/', $attrs, $c) === 1 ? preg_split('/\s+/', trim($c[1])) : [];
            $add = ' vector-effect="non-scaling-stroke"';
            if (in_array('dot', $classes, true) && ! str_contains($attrs, 'stroke-width=')) {
                $add .= ' stroke-width="'.self::DOT_STROKE.'"';
            }
            // class="duo" shapes inherit the outer <svg>'s fill (none since Design 2.1); every other shape never fills.
            if (! in_array('duo', $classes, true) && ! preg_match('/\bfill=/', $attrs)) {
                $add .= ' fill="none"';
            }

            return '<'.$m[1].rtrim($attrs).$add.($m[3] !== '' ? '/' : '').'>';
        }, self::inner($name));

        return '<symbol id="'.e(self::symbolId($name)).'" viewBox="0 0 24 24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">'
            .trim((string) preg_replace('/>\s+</', '><', $shapes)).'</symbol>';
    }

    /** The hidden sprite of every glyph recorded since the last flush(); '' when there is none. */
    public static function sprite(): string
    {
        if (self::$used === []) {
            return '';
        }

        return '<svg class="icon-sprite" width="0" height="0" hidden aria-hidden="true" focusable="false" data-icon-sprite>'
            .implode('', array_map(self::symbol(...), array_keys(self::$used))).'</svg>';
    }

    /**
     * The response HTML with the sprite right after the opening <body> tag (before every icon, so each one
     * paints as soon as it arrives), or first in a fragment without <body>. Unchanged when no glyph was
     * recorded or the HTML already holds a sprite.
     */
    public static function withSprite(string $html): string
    {
        $sprite = self::sprite();
        if ($sprite === '' || str_contains($html, '<svg class="icon-sprite"')) {
            return $html;
        }
        if (preg_match('/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE) === 1) {
            $at = $m[0][1] + strlen($m[0][0]);

            return substr($html, 0, $at).$sprite.substr($html, $at);
        }

        return $sprite.$html;
    }

    public static function mirrors(string $name): bool
    {
        return in_array(self::glyph($name), self::MIRRORED, true);
    }

    /** A functional icon name (brief §3.6 rule 5, ALLOWED): what the design lint accepts. */
    public static function allowed(string $name): bool
    {
        return in_array(basename($name), self::ALLOWED, true);
    }

    /** A decorative arrow or "go" chevron: never in a button, link or card corner (rule 3). */
    public static function isArrow(string $name): bool
    {
        return in_array(basename($name), self::ARROWS, true);
    }

    /** A brand mark drawn from official files (BRANDS), not from this set. */
    public static function isBrand(string $name): bool
    {
        return in_array(basename($name), self::BRANDS, true);
    }

    /** The object icon for a service code (S01–S11); 'file' for an unknown code. Decorative (D04). */
    public static function forService(string $code): string
    {
        return self::SERVICE[strtoupper(trim($code))] ?? 'file';
    }

    /** @return list<string> the glyph files of the set (aliases and brand marks are not files) */
    public static function names(): array
    {
        return array_map(fn ($f) => basename($f, '.svg'), glob(resource_path('svg/d2/icons/*.svg')) ?: []);
    }

    private static function load(string $name): string
    {
        $path = resource_path('svg/d2/icons/'.basename($name).'.svg');
        if (! is_file($path)) {
            // Visible outside production so a missing icon is caught in QA; empty in production.
            return app()->isProduction() ? '' : '<rect x="3" y="3" width="18" height="18" rx="2" stroke-dasharray="2 2"/>';
        }
        $svg = (string) file_get_contents($path);

        return trim((string) preg_replace(['#^.*?<svg[^>]*>#s', '#</svg>\s*$#s'], '', $svg));
    }
}
