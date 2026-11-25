<?php

namespace App\Http\Middleware;

use App\Support\SiteUrl;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Design 2 is the only design (owner, 2026-11-25). Old links and old browsers of the retired "Design 1 | Design 2"
 * switch keep working:
 *
 *  - a GET or HEAD request whose query has the old switch parameter (any value) gets a 301 to the same address
 *    without it: the path keeps its trailing slash, every other query parameter stays as it was sent, in its order and
 *    encoding, and the browser keeps the #anchor. An old share link of the former Design Preview settings loses its
 *    three parameters (the link version and its signature) the same way.
 *  - the old cookies (the design choice and the share-link cookie) are ignored and expired on the next response.
 *
 * Global (bootstrap/app.php), so an unknown address with the parameter is redirected before its 404 as well.
 */
final class RetireDesignSwitch
{
    /** The retired switch's query parameter. */
    public const PARAMETER = 'design';

    /** The parameters of a retired share link (Settings, Design Preview): its version and its URL signature. */
    public const SHARE_LINK = ['design_preview', 'expires', 'signature'];

    /** The retired cookies: the visitor's design choice and the share-link cookie. */
    public const COOKIES = ['summit_design', 'summit_design_share'];

    public function handle(Request $request, Closure $next): Response
    {
        $drop = $this->droppedParameters($request);
        $response = $drop !== [] && ($request->isMethod('GET') || $request->isMethod('HEAD'))
            ? new RedirectResponse($this->addressWithout($request, $drop), 301)
            : $next($request);

        foreach (self::COOKIES as $cookie) {
            if ($request->cookies->has($cookie)) {
                $response->headers->clearCookie($cookie, '/', null, $request->isSecure(), true, 'lax');
            }
        }

        return $response;
    }

    /** @return list<string> */
    private function droppedParameters(Request $request): array
    {
        $drop = [];
        if ($request->query->has(self::PARAMETER)) {
            $drop[] = self::PARAMETER;
        }
        if ($request->query->has(self::SHARE_LINK[0])) {
            array_push($drop, ...self::SHARE_LINK);
        }

        return $drop;
    }

    /**
     * The request's own address without the given query parameters. The raw query string is filtered pair by pair,
     * so the parameters that stay keep their order and encoding (a list parameter such as name[]=… included).
     *
     * @param  list<string>  $drop
     */
    private function addressWithout(Request $request, array $drop): string
    {
        $kept = array_filter(
            explode('&', (string) $request->server->get('QUERY_STRING', '')),
            function (string $pair) use ($drop): bool {
                if ($pair === '') {
                    return false;
                }
                $name = (string) preg_replace('/\[.*$/s', '', urldecode(explode('=', $pair, 2)[0]));

                return ! in_array($name, $drop, true);
            },
        );

        return SiteUrl::abs($request->getPathInfo()).($kept === [] ? '' : '?'.implode('&', $kept));
    }
}
