<?php

namespace App\Design\D2;

/**
 * Design 2 team portraits (Design 2.1 D42, D43): one 4:5 frame and one eye line for every photo, and the "photo
 * pending" panel. Settings: config('design.d2.team') (the admin sets them per photo in Phase 4):
 *   eye_line                     the eyes' height in every frame, % from the top
 *   portraits.<code>.focus       [x, y], % of the photo: the midpoint between the pupils (the focal point)
 *   portraits.<code>.photo_pending  no portrait yet: <x-d2.team.portrait> draws the initials panel
 *
 * Placement: the photo files are 4:5, like the frame, so object-fit alone cannot move a face. The photo is scaled
 * (never below 1, so it always covers the frame) about an origin chosen so that the focal point lands on the eye
 * line, centred across: a point P of the photo goes to O + s·(P − O), and O = (s·P − T) / (s − 1) for the target T.
 * The smallest s that keeps O inside the photo (so no frame edge is ever left uncovered) is used. The values are
 * printed once per page as custom properties in a nonce'd <style> (the CSP forbids style="" attributes);
 * components/profile.css applies them to .portrait__img.
 */
final class Portraits
{
    /** Target across: faces are centred. */
    private const CENTRE = 0.5;

    /**
     * The settings of one member: the focal point (fractions of the photo) or null, and the pending flag.
     *
     * @return array{focus: array{0: float, 1: float}|null, pending: bool}
     */
    public static function settings(string $code): array
    {
        $entry = config('design.d2.team.portraits.'.$code);
        $entry = is_array($entry) ? $entry : [];
        $focus = $entry['focus'] ?? null;
        $point = null;
        if (is_array($focus) && count($focus) === 2 && is_numeric($focus[0] ?? null) && is_numeric($focus[1] ?? null)) {
            $x = (float) $focus[0];
            $y = (float) $focus[1];
            if ($x > 0 && $x < 100 && $y > 0 && $y < 100) {
                $point = [$x / 100, $y / 100];
            }
        }

        return ['focus' => $point, 'pending' => (bool) ($entry['photo_pending'] ?? false)];
    }

    /** Whether the member's photo is flagged as pending (the initials panel is drawn even if files exist). */
    public static function pending(string $code): bool
    {
        return self::settings($code)['pending'];
    }

    /** The shared eye line as a fraction of the frame height (config eye_line, %; default 35). */
    public static function eyeLine(): float
    {
        $line = config('design.d2.team.eye_line', 35);
        $line = is_numeric($line) ? (float) $line : 35.0;

        return max(10.0, min(90.0, $line)) / 100;
    }

    /**
     * Scale and origin that move the focal point (fx, fy) of a photo filling its frame to (0.5, eye).
     *
     * @return array{scale: float, x: float, y: float} origin in fractions of the frame
     */
    public static function geometry(float $fx, float $fy, float $eye): array
    {
        $tx = self::CENTRE;
        $scale = max(1.0, $tx / $fx, (1 - $tx) / (1 - $fx), $eye / $fy, (1 - $eye) / (1 - $fy));
        if ($scale - 1.0 < 1e-6) {
            return ['scale' => 1.0, 'x' => $tx, 'y' => $eye];
        }
        $origin = fn (float $p, float $t) => max(0.0, min(1.0, ($scale * $p - $t) / ($scale - 1)));

        return ['scale' => $scale, 'x' => $origin($fx, $tx), 'y' => $origin($fy, $eye)];
    }

    /** The custom properties of every configured portrait with a focal point (one rule per member). */
    public static function css(): string
    {
        $eye = self::eyeLine();
        $rules = [];
        foreach ((array) config('design.d2.team.portraits', []) as $code => $entry) {
            $code = (string) $code;
            if (preg_match('/^team-[a-z]+$/', $code) !== 1) {
                continue;
            }
            $focus = self::settings($code)['focus'];
            if ($focus === null) {
                continue;
            }
            [$fx, $fy] = $focus;
            $g = self::geometry($fx, $fy, $eye);
            $rules[] = sprintf(
                '.portrait[data-member="%s"]{--portrait-x:%s%%;--portrait-y:%s%%;--portrait-scale:%s;--portrait-ox:%s%%;--portrait-oy:%s%%}',
                $code,
                self::number($fx * 100),
                self::number($fy * 100),
                self::number($g['scale'], 4),
                self::number($g['x'] * 100),
                self::number($g['y'] * 100),
            );
        }

        // Unlayered on purpose: a @layer statement printed in the page could come before the stylesheet's own layer
        // order (the dev server injects the CSS later) and reorder the layers. Only these properties are set here.
        return implode('', $rules);
    }

    private static function number(float $value, int $decimals = 2): string
    {
        $text = rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
