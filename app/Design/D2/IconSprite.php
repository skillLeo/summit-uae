<?php

namespace App\Design\D2;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Global middleware (bootstrap/app.php, prepended: the outermost layer, so error pages rendered inside the
 * pipeline, maintenance mode's 503 included, pass through it too). <x-d2.ui.icon> prints
 * <svg><use href="#d2i-{name}"/></svg> and records the glyph; this puts the one hidden sprite with a <symbol>
 * per recorded glyph right after <body> of the HTML response (Icons::withSprite()). A response that rendered
 * no Design 2 icon (every Design 1 page, JSON, files, the admin) is returned untouched, byte for byte.
 */
final class IconSprite
{
    public function handle(Request $request, Closure $next): Response
    {
        Icons::flush();

        try {
            $response = $next($request);

            if (Icons::used() !== []
                && ! $response instanceof StreamedResponse
                && ! $response instanceof BinaryFileResponse
                && str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
                $html = $response->getContent();
                if (is_string($html) && $html !== '') {
                    $withSprite = Icons::withSprite($html);
                    if ($withSprite !== $html) {
                        $response->setContent($withSprite);
                        $response->headers->remove('Content-Length');
                    }
                }
            }

            return $response;
        } finally {
            Icons::flush();
        }
    }
}
