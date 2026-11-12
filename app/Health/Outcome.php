<?php

namespace App\Health;

use Illuminate\Support\HtmlString;
use Spatie\Health\Checks\Result;

/**
 * The result of a Summit health check (App\Health\*): the status and a message key of lang/{locale}/system.php
 * ("health.message.<key>") with its parameters. The key and the parameters are kept in the result's meta, so the
 * Health screen shows the message in the staff member's language whatever language the check ran in.
 */
final class Outcome
{
    /** @param  array<string, scalar|null>  $params */
    public static function ok(string $key, array $params = []): Result
    {
        return self::make('ok', $key, $params);
    }

    /** @param  array<string, scalar|null>  $params */
    public static function warning(string $key, array $params = []): Result
    {
        return self::make('warning', $key, $params);
    }

    /** @param  array<string, scalar|null>  $params */
    public static function failed(string $key, array $params = []): Result
    {
        return self::make('failed', $key, $params);
    }

    /** @param  array<string, scalar|null>  $params */
    private static function make(string $status, string $key, array $params): Result
    {
        $message = 'system.health.message.'.$key;
        $result = Result::make()->meta(['message' => $key, 'params' => $params] + $params);

        return $result->{$status}($message)->shortSummary((string) __('system.health.status.'.$status, [], 'en'));
    }

    /**
     * The message as HTML for the Health screen: the text escaped, every parameter that is not a plain number inside
     * <bdi dir="ltr"> (names, versions, sizes read left to right in Arabic too) and `commands` as LTR code.
     */
    public static function html(array $meta, ?string $fallback = null): HtmlString
    {
        $key = $meta['message'] ?? null;
        $tkey = 'system.health.message.'.(is_string($key) ? $key : '');
        $template = is_string($key) && $key !== '' && __($tkey) !== $tkey ? (string) __($tkey) : (string) $fallback;
        $html = e($template);
        $params = array_filter((array) ($meta['params'] ?? []), fn ($v) => is_scalar($v) || $v === null);
        uksort($params, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));
        $replace = [];
        foreach ($params as $name => $value) {
            $value = (string) $value;
            $replace[':'.$name] = preg_match('/^\d+$/', $value) === 1 ? e($value) : '<bdi dir="ltr">'.e($value).'</bdi>';
        }
        $html = strtr($html, $replace);
        $html = (string) preg_replace('/`([^`]+)`/', '<code class="slc-system-code" dir="ltr">$1</code>', $html);

        return new HtmlString($html);
    }

    /** The message of a stored result in the current language. */
    public static function message(array $meta, ?string $fallback = null): string
    {
        $key = $meta['message'] ?? null;
        if (is_string($key) && $key !== '' && __('system.health.message.'.$key) !== 'system.health.message.'.$key) {
            $params = array_filter((array) ($meta['params'] ?? []), fn ($v) => is_scalar($v) || $v === null);

            return (string) __('system.health.message.'.$key, array_map(fn ($v) => (string) $v, $params));
        }

        return (string) $fallback;
    }
}
