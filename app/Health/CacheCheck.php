<?php

namespace App\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/** Cache: the default store writes, reads back and forgets a value. */
final class CacheCheck extends Check
{
    protected ?string $name = 'cache';

    public function run(): Result
    {
        $store = (string) config('cache.default');
        try {
            $key = 'health:cache-check:'.Str::random(8);
            $value = Str::random(16);
            Cache::put($key, $value, 10);
            $read = Cache::get($key);
            Cache::forget($key);

            return $read === $value
                ? Outcome::ok('cache_ok', ['store' => $store])
                : Outcome::failed('cache_failed', ['store' => $store]);
        } catch (Throwable) {
            return Outcome::failed('cache_failed', ['store' => $store]);
        }
    }
}
