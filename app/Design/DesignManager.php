<?php

namespace App\Design;

use App\Filament\Cms\Support\CmsAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

/**
 * Two designs on the same routes, controllers, content and form backend.
 *
 *  - Design 1 ("The File") renders from resources/views (frozen at the git tag design-1).
 *  - Design 2 searches resources/views/designs/d2 first: a Design 2 view replaces the Design 1 view
 *    of the same name, and any view Design 2 does not have yet falls back to Design 1.
 *
 * Design 2 internals are referenced through the d2:: namespace and never reuse a Design 1 view name
 * (tests/Feature/Design/ViewIsolationTest.php), so a Design 1 fallback page always renders with
 * Design 1's own layout, partials and assets.
 *
 * Settings, Design Preview (App\Settings\DesignSettings, brief 2.1 §12.3) may set the default design and who sees the
 * switch; both are null by default, and then SUMMIT_DEFAULT_DESIGN and SUMMIT_DESIGN_SWITCHER decide exactly as before.
 */
final class DesignManager
{
    public const DESIGNS = [1, 2];

    private ?int $current = null;

    /** @var list<string>|null the finder paths before any design was applied */
    private ?array $basePaths = null;

    /** @var list<string>|null config('view.paths') before any design was applied */
    private ?array $baseConfigPaths = null;

    public function __construct(private readonly Factory $views) {}

    /** A valid design number from untrusted input ("1", "2", 1, 2), otherwise null. */
    public static function normalize(mixed $value): ?int
    {
        if (is_int($value)) {
            return in_array($value, self::DESIGNS, true) ? $value : null;
        }
        if (is_string($value) && preg_match('/^\d$/', $value) === 1) {
            return self::normalize((int) $value);
        }

        return null;
    }

    public function default(): int
    {
        return self::normalize(self::setting('default_design')) ?? self::normalize(config('design.default')) ?? 1;
    }

    /** One value of the Design Preview settings (null when unset or unreadable: the environment decides). */
    public static function setting(string $name): mixed
    {
        try {
            return content()->settings('design')[$name] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Who sees the "Design 1 | Design 2" switch: "everyone", "admins" (signed-in admins), "link" (admins and the
     * browsers that opened a share link) or "off". The setting decides when it is set; null = SUMMIT_DESIGN_SWITCHER
     * (on = everyone, as before). SUMMIT_DESIGN_SWITCHER=false in the environment always wins in production.
     */
    public function switcherMode(): string
    {
        if (app()->isProduction() && config('design.switcher_env') === false) {
            return 'off';
        }
        $setting = self::setting('switcher');
        if (in_array($setting, ['off', 'admins', 'link'], true)) {
            return $setting;
        }

        return config('design.switcher') ? 'everyone' : 'off';
    }

    /** Whether this request gets the switch (the QA cookie and header are checked by the callers, as before). */
    public function showsSwitcher(Request $request): bool
    {
        return match ($this->switcherMode()) {
            'everyone' => true,
            'admins' => $this->viewerIsAdmin($request),
            'link' => $this->viewerIsAdmin($request) || $this->hasShareCookie($request),
            default => false,
        };
    }

    private function viewerIsAdmin(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false; // outside the web group (an unmatched URL's 404): no sign-in to read
        }
        try {
            $user = auth()->guard('web')->user();

            return $user !== null && CmsAccess::admin($user);
        } catch (Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------ share links

    public static function shareVersion(): int
    {
        $version = self::setting('share_version');

        return is_int($version) && $version > 0 ? $version : 1;
    }

    /**
     * A share link (Settings, Design Preview): the signed home page address, valid config('design.share_days') days,
     * that sets the share cookie in the browser that opens it (SetDesign::acceptShareLink).
     *
     * @return array{url: string, expires: CarbonImmutable}
     */
    public static function createShareLink(string $locale = 'en'): array
    {
        $expires = CarbonImmutable::now()->addDays((int) config('design.share_days', 7));
        $url = URL::temporarySignedRoute('home', $expires, ['locale' => $locale, (string) config('design.share_param') => self::shareVersion()]);
        // The home page's own address ends with a slash; the signature covers the URL without it (Request::url()).
        $url = (string) preg_replace('#^([^?]*[^/?])\?#', '$1/?', $url);

        return ['url' => $url, 'expires' => $expires];
    }

    /** The cookie a valid share link sets: "<expires>.<version>.<HMAC>", until the link expires. */
    public static function shareCookie(int $expires, int $version, bool $secure): Cookie
    {
        return Cookie::create(
            (string) config('design.share_cookie'),
            $expires.'.'.$version.'.'.self::shareMac($expires, $version),
            $expires,
            '/',
            null,
            $secure,
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    public function hasShareCookie(Request $request): bool
    {
        $value = $request->cookies->get((string) config('design.share_cookie'));
        if (! is_string($value) || preg_match('/^(\d{1,12})\.(\d{1,9})\.([0-9a-f]{64})$/', $value, $m) !== 1) {
            return false;
        }
        [, $expires, $version, $mac] = $m;

        return (int) $expires > CarbonImmutable::now()->getTimestamp()
            && (int) $version === self::shareVersion()
            && hash_equals(self::shareMac((int) $expires, (int) $version), $mac);
    }

    private static function shareMac(int $expires, int $version): string
    {
        return hash_hmac('sha256', 'design-share|'.$expires.'|'.$version, (string) config('app.key'));
    }

    public function current(): int
    {
        return $this->current ?? $this->default();
    }

    public function is(int $design): bool
    {
        return $this->current() === $design;
    }

    /** Whether SetDesign (or the error renderer) applied a design in this request. */
    public function applied(): bool
    {
        return $this->current !== null;
    }

    public function apply(int $design): void
    {
        $finder = $this->views->getFinder();
        if (! $finder instanceof FileViewFinder) {
            return;
        }
        $this->basePaths ??= array_values(array_filter(
            $finder->getPaths(),
            fn (string $p) => rtrim($p, '/') !== rtrim((string) config('design.d2.views'), '/'),
        ));

        $paths = $design === 2 ? [(string) config('design.d2.views'), ...$this->basePaths] : $this->basePaths;
        $finder->setPaths($paths);
        $finder->flush();

        // Error pages: Laravel's exception handler rebuilds the errors:: namespace from config('view.paths')
        // ({path}/errors, then its stock views) right before it renders errors::{status}
        // (RegisterErrorViewPaths), so Design 2 lists its folder first there too: designs/d2/errors/403 …
        // replace the stock pages, any status Design 2 lacks keeps the stock page. Design 1: the original list.
        $this->baseConfigPaths ??= array_values(array_filter(
            (array) config('view.paths'),
            fn ($p) => rtrim((string) $p, '/') !== rtrim((string) config('design.d2.views'), '/'),
        ));
        config(['view.paths' => $design === 2 ? [(string) config('design.d2.views'), ...$this->baseConfigPaths] : $this->baseConfigPaths]);

        $this->current = $design;
        $this->views->share('design', $design);
    }
}
