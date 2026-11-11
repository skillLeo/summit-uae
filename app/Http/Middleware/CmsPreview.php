<?php

namespace App\Http\Middleware;

use App\Content\Cms\Editing\Preview;
use App\Content\ContentRepository;
use App\Design\DesignManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed previews of unpublished content (docs/briefs/design-2.1-and-admin-cms.md §8.5; App\Content\Cms\Editing\Preview).
 * A request with ?cms_preview=… renders the page in Design 2 with the record's working copy in place of its published
 * row. A link that is altered or older than 60 minutes is refused with 403. A preview is never cached and never indexed.
 * Any other request passes through untouched.
 *
 * Registered globally (so the 404 page can be previewed too) and again in the web group after SetDesign, which would
 * otherwise put the visitor's design choice back.
 */
final class CmsPreview
{
    public function __construct(private readonly DesignManager $designs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $preview = Preview::fromRequest($request);
        if ($preview === null) {
            return $next($request);
        }
        if (! $preview['valid'] || $preview['expired']) {
            abort(403, $preview['expired'] ? 'This preview link has expired.' : 'This preview link is not valid.');
        }
        $repository = app(ContentRepository::class);
        if (! $repository->previewing()) {
            $repository->preview(Preview::overlay($preview['record']));
        }
        $this->designs->apply(2);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
