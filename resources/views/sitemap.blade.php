{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
@foreach ($locales as $code => $cfg)
    <url>
        <loc>{{ \App\Support\SiteUrl::canonical($code.'/'.$page['path']) }}</loc>
@foreach ($locales as $alt => $altCfg)
        <xhtml:link rel="alternate" hreflang="{{ $altCfg['hreflang'] }}" href="{{ \App\Support\SiteUrl::canonical($alt.'/'.$page['path']) }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ \App\Support\SiteUrl::canonical(config('summit.default_locale').'/'.$page['path']) }}"/>
    </url>
@endforeach
@endforeach
</urlset>
