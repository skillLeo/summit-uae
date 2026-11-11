<?php

namespace App\Content\Cms\Editing;

use App\Content\Cms\ContentCache;
use App\Content\Cms\Status;
use App\Models\Content\Page;
use App\Models\Content\Service;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Slugs of the CMS (docs/briefs/design-2.1-and-admin-cms.md §8.3): the published slug of every page (pages.slug, the
 * path after the language: "practice-areas/") and practice area (services.slug) decides its address, and a changed slug
 * leaves a 301 redirect from the old address in every language.
 *
 * The site's addresses are the same in English, Arabic and Russian (/en/practice-areas/labor-law/,
 * /ar/practice-areas/labor-law/ …), so one slug serves all three languages and the redirect is written for each.
 *
 * apply() runs when the routes are registered (routes/web.php): it puts the published slugs into config('summit.pages.*.path')
 * and config('summit.services'), which the routes and App\Support\SiteUrl read. While they equal today's config, nothing
 * changes. The map is cached under the content cache version.
 */
final class Slugs
{
    private const SLUG = '#^[a-z0-9]+(?:-[a-z0-9]+)*$#';

    private const PATH = '#^(?:[a-z0-9]+(?:-[a-z0-9]+)*/)+$#';

    /** Put the published slugs into the route config. */
    public static function apply(): void
    {
        try {
            $map = Cache::rememberForever(ContentCache::key('slugs'), fn () => self::build());
        } catch (Throwable) {
            return; // no database yet (before migrate): the config paths stay
        }
        foreach ($map['pages'] ?? [] as $code => $path) {
            if (config("summit.pages.{$code}") !== null) {
                config(["summit.pages.{$code}.path" => $path]);
            }
        }
        foreach ($map['services'] ?? [] as $code => $slug) {
            if (config("summit.services.{$code}") !== null) {
                config(["summit.services.{$code}" => $slug]);
            }
        }
    }

    /** @return array{pages: array<string, string>, services: array<string, string>} */
    private static function build(): array
    {
        $map = ['pages' => [], 'services' => []];
        try {
            foreach (DB::table('pages')->where('status', Status::PUBLISHED)->whereNull('deleted_at')->get(['code', 'slug']) as $row) {
                if ($row->code !== 'P01' && is_string($row->slug) && preg_match(self::PATH, $row->slug)) {
                    $map['pages'][$row->code] = $row->slug;
                }
            }
            foreach (DB::table('services')->where('status', Status::PUBLISHED)->whereNull('deleted_at')->get(['code', 'slug']) as $row) {
                if (is_string($row->slug) && preg_match(self::SLUG, $row->slug)) {
                    $map['services'][$row->code] = $row->slug;
                }
            }
        } catch (QueryException) {
            // before migrate
        }

        return $map;
    }

    /** Whether a slug is valid for the model ("labor-law" for a practice area, "about/" for a page). */
    public static function valid(Model $record, ?string $slug): bool
    {
        return match (true) {
            $record instanceof Page => $slug !== null && preg_match(self::PATH, $slug) === 1,
            $record instanceof Service => $slug !== null && preg_match(self::SLUG, $slug) === 1,
            default => true,
        };
    }

    /**
     * After a publish: when the slug changed, a 301 from the old address to the new one in every language (and the
     * practice-area pages under a renamed Practice Areas page). An older redirect to the old address is pointed at the
     * new one, so no chain forms; a redirect FROM the new address is removed, so the page is reachable again.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<Redirect>
     */
    public static function afterPublish(Model $record, array $before, array $after): array
    {
        $old = $before['slug'] ?? null;
        $new = $after['slug'] ?? null;
        if (! ($record instanceof Page || $record instanceof Service) || ! is_string($old) || ! is_string($new) || $old === $new || $old === '') {
            return [];
        }
        $pairs = [];
        foreach (array_keys(config('summit.locales')) as $locale) {
            if ($record instanceof Page) {
                $pairs[] = ["/{$locale}/{$old}", "/{$locale}/{$new}"];
                if ($record->getKey() === 'P02') {
                    foreach (config('summit.services') as $slug) {
                        $pairs[] = ["/{$locale}/{$old}{$slug}/", "/{$locale}/{$new}{$slug}/"];
                    }
                }
            } else {
                $base = (string) config('summit.pages.P02.path');
                $pairs[] = ["/{$locale}/{$base}{$old}/", "/{$locale}/{$base}{$new}/"];
            }
        }
        $made = [];
        foreach ($pairs as [$from, $to]) {
            Redirect::query()->where('to_path', $from)->update(['to_path' => $to]);
            Redirect::query()->where('from_path', $to)->delete();
            $made[] = Redirect::query()->updateOrCreate(['from_path' => $from], [
                'to_path' => $to, 'code' => 301, 'enabled' => true, 'updated_by' => auth()->id(),
            ]);
        }
        ContentCache::bump();

        return $made;
    }
}
