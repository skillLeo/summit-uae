<?php

use App\Design\D2\Digits;
use App\Design\D2\IconSprite;
use App\Design\DesignManager;
use App\Http\Middleware\DesignSwitcher;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetDesign;
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
        // Design 1 / Design 2 (docs/upgrade-plan.md): global so unmatched URLs render their 404 in the
        // chosen design, and again after StartSession so the choice is also kept in the session.
        $middleware->append(SetDesign::class);
        $middleware->append(DesignSwitcher::class);
        $middleware->appendToGroup('web', SetDesign::class);
        // Preference cookies written by the browser (language choice, dismissed suggestion bar)
        // hold no secrets and cannot be encrypted client-side. The design cookie must be readable
        // by the global middleware, before the web group decrypts cookies.
        $middleware->encryptCookies(except: ['summit_lang', 'summit_suggest_off', 'summit_design', 'summit_qa']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Design 2 error pages (DesignManager::apply): an error thrown before the global SetDesign middleware
        // ran (maintenance mode's 503) still renders in the visitor's design (cookie, else the default).
        // Renders nothing itself: Laravel then renders errors::{status} as usual.
        $exceptions->render(function (Throwable $e, Request $request) {
            rescue(function () use ($request) {
                $designs = app(DesignManager::class);
                if (! $designs->applied()) {
                    $designs->apply(DesignManager::normalize($request->cookies->get((string) config('design.cookie'))) ?? $designs->default());
                }
            }, report: false);

            return null;
        });

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
