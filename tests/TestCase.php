<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel's test client trims trailing slashes; Summit page URLs end with "/" (and
     * EnsureTrailingSlash 301s the slashless form), so keep the slash when a test asks for it.
     */
    protected function prepareUrlForRequest($uri)
    {
        $url = parent::prepareUrlForRequest($uri);
        $path = parse_url((string) $uri, PHP_URL_PATH) ?? '';

        if (str_ends_with($path, '/') && $path !== '/') {
            $query = parse_url((string) $uri, PHP_URL_QUERY);
            $base = $query !== null ? substr($url, 0, (int) strpos($url, '?')) : $url;

            return rtrim($base, '/').'/'.($query !== null ? '?'.$query : '');
        }

        return $url;
    }
}
