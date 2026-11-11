<?php

namespace App\Content\Cms;

use App\Content\ContentRepository;
use Illuminate\Support\Facades\Cache;

/**
 * The published-content cache (docs/briefs/design-2.1-and-admin-cms.md §8.6).
 *
 * The local cache store (file) has no tags, so the cache is versioned instead: every entry of the content snapshot is
 * stored under "summit.cms.{version}.{part}", and any change to published content (a content model saved, deleted or
 * restored, a settings group saved, an import) writes a new version. Readers then miss and rebuild; the previous
 * version's entries are forgotten. The version itself lives in the same store ("summit.cms.version").
 */
final class ContentCache
{
    private const VERSION_KEY = 'summit.cms.version';

    /** Every part a version writes (forgotten when the version is replaced). */
    public const PARTS = ['shared', 'records.en', 'records.ar', 'records.ru', 'slugs', 'redirects'];

    public static function version(): string
    {
        $version = Cache::get(self::VERSION_KEY);

        return is_string($version) && $version !== '' ? $version : self::bump(flushRepository: false);
    }

    public static function key(string $part): string
    {
        return 'summit.cms.'.self::version().'.'.$part;
    }

    /** A new version: the next read rebuilds the snapshot. Returns the new version. */
    public static function bump(bool $flushRepository = true): string
    {
        $previous = Cache::get(self::VERSION_KEY);
        $version = bin2hex(random_bytes(6));
        Cache::forever(self::VERSION_KEY, $version);
        if (is_string($previous) && $previous !== '') {
            foreach (self::PARTS as $part) {
                Cache::forget('summit.cms.'.$previous.'.'.$part);
            }
        }
        if ($flushRepository && app()->resolved(ContentRepository::class)) {
            app(ContentRepository::class)->forget();
        }

        return $version;
    }
}
