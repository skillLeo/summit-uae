<?php

namespace App\Models;

use App\Content\Cms\ContentCache;
use Illuminate\Database\Eloquent\Model;

/**
 * A redirect (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.11): from_path → to_path with 301 or 302. Changing a
 * slug in the admin creates one automatically (App\Content\Cms\Editing\Slugs); App\Http\Middleware\ServeRedirects serves
 * them before routing.
 */
final class Redirect extends Model
{
    /** The redirect map is cached with the content (App\Http\Middleware\ServeRedirects): any change replaces it. */
    protected static function booted(): void
    {
        $bump = fn () => ContentCache::bump();
        static::saved($bump);
        static::deleted($bump);
    }

    protected $guarded = ['id'];

    protected $casts = [
        'code' => 'integer',
        'hits' => 'integer',
        'enabled' => 'boolean',
        'last_hit_at' => 'datetime',
    ];
}
