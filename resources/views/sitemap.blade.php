{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
{{-- /sitemap.xml (App\Http\Controllers\SiteController::sitemap(), client PDF item 23): App\Support\SitePages, each page in
     the languages in which it exists, with its reciprocal alternates and x-default (the default language's version).
     The XML declaration must stay the very first bytes of the file. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
@foreach ($page['locales'] as $code)
    <url>
        <loc>{{ \App\Support\SiteUrl::canonical($code.'/'.$page['path']) }}</loc>
@if (count($page['locales']) > 1)
@foreach ($page['locales'] as $alt)
        <xhtml:link rel="alternate" hreflang="{{ $locales[$alt]['hreflang'] }}" href="{{ \App\Support\SiteUrl::canonical($alt.'/'.$page['path']) }}"/>
@endforeach
@if (in_array($default, $page['locales'], true))
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ \App\Support\SiteUrl::canonical($default.'/'.$page['path']) }}"/>
@endif
@endif
    </url>
@endforeach
@endforeach
</urlset>
