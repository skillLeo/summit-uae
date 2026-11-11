<?php

namespace App\Design\D2;

use App\Support\SiteUrl;

/**
 * The Design 2 calculators page (brief §5.1): its path and its localised URL. The page itself is
 * App\Http\Controllers\CalculatorsController; links to it render only while pending copy is shown
 * (Pending::visible()), because the page and its estimators are pending client approval.
 *
 * Linked (<x-d2.tool.link>) from home section 09 (the page), the Employment Law page S06 (#tool-gratuity) and
 * the Property and Tenancy page S05 (#tool-rent).
 */
final class Calculators
{
    public const PATH = 'calculators/';

    /** Service page => the calculator it links to (its panel is #tool-<name> on the page). */
    public const SERVICES = ['S06' => 'gratuity', 'S05' => 'rent'];

    /** e.g. /en/calculators/ or /ar/calculators/#tool-rent (absolute, trailing slash kept). */
    public static function url(?string $locale = null, ?string $fragment = null): string
    {
        return SiteUrl::abs('/'.SiteUrl::locale($locale).'/'.self::PATH).($fragment ? '#'.$fragment : '');
    }

    /** Whether links to the page may render. */
    public static function linked(): bool
    {
        return Pending::visible();
    }

    /** The calculator a service page links to ('gratuity', 'rent'), or null. */
    public static function forService(?string $code): ?string
    {
        return self::SERVICES[(string) $code] ?? null;
    }
}
