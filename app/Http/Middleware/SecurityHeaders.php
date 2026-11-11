<?php

namespace App\Http\Middleware;

use App\Providers\Filament\AdminPanelProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nonce-based CSP (no unsafe-inline, no unsafe-eval: Alpine runs on its CSP build) and the usual
 * hardening headers. Cloudflare Turnstile is the only third-party origin.
 *
 * The internal admin panel (/admin, Filament) and Livewire's endpoints get their own, relaxed policy
 * (adminPolicy()): Filament runs Alpine without the CSP build ('unsafe-eval') and writes inline styles
 * ('unsafe-inline' for styles only). Every other response keeps exactly the public policy below.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        if (self::isAdminRequest($request)) {
            return $this->harden($request, $response, $this->adminPolicy($nonce));
        }

        $turnstile = 'https://challenges.cloudflare.com';
        $script = ["'self'", "'nonce-{$nonce}'", $turnstile];
        $style = ["'self'", "'nonce-{$nonce}'"];
        $connect = ["'self'", $turnstile];

        // Vite dev servers (npm run dev / npm run dev:d2) serve modules and inject styles from another origin.
        foreach (['hot', (string) config('design.d2.hot_file', 'hot-d2')] as $hotFile) {
            if (! is_file(public_path($hotFile))) {
                continue;
            }
            $dev = rtrim((string) file_get_contents(public_path($hotFile)));
            $ws = preg_replace('#^http#', 'ws', $dev);
            array_push($script, $dev);
            array_push($style, $dev);
            array_push($connect, $dev, $ws);
        }

        $csp = [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "img-src 'self' data:",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            "frame-src {$turnstile}",
            "worker-src 'self'",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        return $this->harden($request, $response, $csp);
    }

    /**
     * The admin panel's pages (/admin, /admin/…) and Livewire's endpoints (/livewire-{hash}/…: component
     * updates and its script), which only the admin panel uses.
     */
    public static function isAdminRequest(Request $request): bool
    {
        $admin = trim(AdminPanelProvider::PATH, '/');
        if ($request->is($admin, "{$admin}/*")) {
            return true;
        }

        if (class_exists(EndpointResolver::class)) {
            $livewire = trim(EndpointResolver::prefix(), '/');

            return $livewire !== '' && $request->is("{$livewire}/*");
        }

        return false;
    }

    /**
     * Same origin only, no third party at all: scripts need the nonce (Filament and Livewire print it on
     * their inline scripts) plus 'unsafe-eval' for Alpine's expression evaluator; styles allow inline
     * style attributes. Images allow data: (the local initials avatars) and blob:. Only the admin theme's
     * own Vite dev server (public/hot-admin) is added in development.
     *
     * @return list<string>
     */
    private function adminPolicy(string $nonce): array
    {
        $script = ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'"];
        $style = ["'self'", "'unsafe-inline'"];
        $connect = ["'self'"];

        if (is_file(public_path('hot-admin'))) {
            $dev = rtrim((string) file_get_contents(public_path('hot-admin')));
            $ws = preg_replace('#^http#', 'ws', $dev);
            array_push($script, $dev);
            array_push($style, $dev);
            array_push($connect, $dev, $ws);
        }

        return [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "img-src 'self' data: blob:",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            "frame-src 'none'",
            "worker-src 'self'",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];
    }

    /** @param  list<string>  $csp */
    private function harden(Request $request, Response $response, array $csp): Response
    {
        if ($request->isSecure()) {
            $csp[] = 'upgrade-insecure-requests';
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'DENY');

        if (config('summit.noindex')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
