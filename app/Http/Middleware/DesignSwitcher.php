<?php

namespace App\Http\Middleware;

use App\Design\DesignManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Injects the floating "Design 1 | Design 2" switch into HTML responses when SUMMIT_DESIGN_SWITCHER is
 * on (or, when Settings, Design Preview sets it, for admins only or admins and share links: DesignManager::showsSwitcher). Injecting it here (inline CSS/JS carrying the CSP nonce) keeps every Design 1 template and both
 * asset builds untouched. Requests with the QA cookie (summit_qa=1) or header (X-Summit-QA) never get it,
 * so screenshots and Lighthouse runs stay clean.
 */
final class DesignSwitcher
{
    public function __construct(private readonly DesignManager $designs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->has((string) config('design.qa_header'))
            || $request->cookies->get((string) config('design.qa_cookie')) === '1'
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || $response->isRedirection()
            || ! str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')
            || ! $this->designs->showsSwitcher($request)) {
            return $response;
        }

        $html = $response->getContent();
        if (! is_string($html) || ! str_contains($html, '</body>') || ! str_contains($html, '</head>')) {
            return $response;
        }

        $nonce = Vite::cspNonce();
        $dir = resource_path('views/design-switch');
        $style = '<style nonce="'.e($nonce).'">'.file_get_contents($dir.'/switch.css').'</style>';
        $markup = view('design-switch.switch', [
            'current' => $this->designs->current(),
            'links' => $this->links($request),
            'nonce' => $nonce,
            'script' => file_get_contents($dir.'/switch.js'),
        ])->render();

        $html = substr_replace($html, $style.'</head>', (int) strrpos($html, '</head>'), strlen('</head>'));
        $html = substr_replace($html, $markup.'</body>', (int) strrpos($html, '</body>'), strlen('</body>'));
        $response->setContent($html);

        return $response;
    }

    /** @return array<int, string> design => same path and query with ?design=N */
    private function links(Request $request): array
    {
        $query = $request->query();
        unset($query['design']);
        $out = [];
        foreach (DesignManager::DESIGNS as $design) {
            $out[$design] = $request->getPathInfo().'?'.http_build_query($query + ['design' => $design], '', '&', PHP_QUERY_RFC3986);
        }

        return $out;
    }
}
