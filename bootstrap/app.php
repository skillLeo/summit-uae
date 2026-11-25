<?php

use App\Design\D2\Digits;
use App\Design\D2\IconSprite;
use App\Http\Middleware\CmsPreview;
use App\Http\Middleware\EditThisPageBar;
use App\Http\Middleware\RetireDesignSwitch;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ServeRedirects;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global and ahead of the framework's own global middleware (PreventRequestsDuringMaintenance among them),
        // so every response carries the same CSP and hardening headers: error pages (404) and maintenance mode's
        // 503 included. Only headers change; the nonce is created before any view renders.
        $middleware->prepend(SecurityHeaders::class);
        // Design 2 icon sprite (App\Design\D2\Icons): outermost, so every HTML response that printed a Design 2
        // icon gets its <symbol>s, error pages and maintenance mode's 503 included; any other response is untouched.
        $middleware->prepend(IconSprite::class);
        // Arabic digits (App\Design\D2\Digits, Design 2.1 brief §3.7): outermost, so every Design 2 Arabic HTML response
        // (error pages and maintenance mode's 503 included) shows one digit style; every other response is untouched.
        $middleware->prepend(Digits::class);
        // Design 2 is the only design (docs/upgrade-plan.md): links of the retired design switch get a 301 to the same
        // address without its parameter, and its old cookies are expired. Global, so unknown addresses are covered too.
        $middleware->append(RetireDesignSwitch::class);
        // CMS (docs/briefs/design-2.1-and-admin-cms.md §8.3, §8.5): redirects before routing (none exist today, so
        // nothing changes), signed previews of drafts (global, so the 404 page can be previewed too), and the admins'
        // "Edit this page" bar (visitors' responses are untouched).
        $middleware->append(ServeRedirects::class);
        $middleware->append(CmsPreview::class);
        $middleware->appendToGroup('web', EditThisPageBar::class);
        // Preference cookies written by the browser (language choice, dismissed suggestion bar)
        // hold no secrets and cannot be encrypted client-side.
        $middleware->encryptCookies(except: ['summit_lang', 'summit_suggest_off']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // U01: a real HTTP 404 in the language of the requested URL, at the requested address.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }
            $locales = config('summit.locales');
            $segment = (string) $request->segment(1);
            $locale = array_key_exists($segment, $locales) ? $segment : config('summit.default_locale');
            app()->setLocale($locale);
            URL::defaults(['locale' => $locale]);

            return response()->view('errors.404', [
                'locale' => $locale,
                'dir' => $locales[$locale]['dir'],
            ], 404);
        });
    })->create();
