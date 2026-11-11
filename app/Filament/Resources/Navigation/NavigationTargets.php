<?php

namespace App\Filament\Resources\Navigation;

use App\Content\Cms\Editing\Registry;
use App\Models\Content\CaseStudy;
use App\Models\Content\Guide;
use App\Models\Content\Service;
use App\Models\Content\SpecialistSite;
use Illuminate\Database\Eloquent\Model;

/**
 * Which targets a navigation item may have, per type (App\Models\Content\NavigationItem::TARGET_TYPES), as
 * App\Content\ContentRepository::menu() and App\Support\SiteUrl::target() resolve them:
 *   page     a page of the site map (P01 … P08, L01, L02)
 *   record   a practice area (S01 …), an associated site (X01 …), a guide (G01 …) or a case study (case-01 …) that exists
 *   anchor   a shared anchor of the site map (A01 … A04) or "#an-id" on the same page
 *   url      a full https:// (or http://) address, or a path of this site ("/en/…")
 *   tel, email, whatsapp: no target (the contact settings and the WA01 draft give the address)
 */
final class NavigationTargets
{
    /** The error code (cms_site.navigation.errors.*) of a target, or null when it is valid. */
    public static function error(string $type, ?string $target): ?string
    {
        if (in_array($type, NavigationResource::NO_TARGET, true)) {
            return null;
        }
        if ($target === null || $target === '') {
            return 'required';
        }

        return match ($type) {
            'page' => array_key_exists($target, (array) config('summit.pages')) ? null : 'page',
            'record' => self::recordExists($target) ? null : 'record',
            'anchor' => array_key_exists($target, (array) config('summit.anchors')) || preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $target) === 1 ? null : 'anchor',
            'url' => self::url($target) ? null : 'url',
            default => 'type',
        };
    }

    private static function recordExists(string $target): bool
    {
        $model = self::model($target);

        return $model !== null && $model::query()->whereKey($target)->exists();
    }

    /** "S06: Employment Law" for a record target (its title in the admin's language), else the target. */
    public static function recordLabel(string $target): string
    {
        $model = self::model($target);
        $record = $model !== null ? $model::query()->find($target) : null;

        return $record !== null ? $target.' '.Registry::label($record) : $target;
    }

    /** @return class-string<Model>|null */
    private static function model(string $target): ?string
    {
        return match (true) {
            (bool) preg_match('/^S\d{2}$/', $target) => Service::class,
            (bool) preg_match('/^X\d{2}$/', $target) => SpecialistSite::class,
            (bool) preg_match('/^G\d{2}$/', $target) => Guide::class,
            (bool) preg_match('/^case-\d{2}$/', $target) => CaseStudy::class,
            default => null,
        };
    }

    private static function url(string $target): bool
    {
        if (preg_match('#^/(?!/)[^\s]*$#', $target) === 1) {
            return true;
        }

        return preg_match('#^https?://#i', $target) === 1 && filter_var($target, FILTER_VALIDATE_URL) !== false;
    }
}
