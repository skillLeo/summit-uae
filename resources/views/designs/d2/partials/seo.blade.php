{{--
    The SEO part of the Design 2 head (client PDF items 21-27), from $seo (App\Support\Seo::make()):
      title, description   the page's approved SEO title and meta description, in the page language
      robots               "noindex, follow" on a page that may not be indexed, and on every page of the test site
                           (SUMMIT_NOINDEX, or the SEO screen's switch)
      canonical            one self-referencing URL on the production host, on every current page
      alternates           hreflang en / ar / ru / x-default to the equivalent pages (current pages only)
      Open Graph, Twitter  the page's own title, description and address, the social image (absolute https, 1200 × 630
                           by default, with its type, size and alt text: what WhatsApp and LinkedIn need for a large
                           preview), the page language and its other languages; twitter:card summary_large_image
      JSON-LD              App\Support\StructuredData (current pages only)
--}}
@php
    /** @var array $seo */
    $image = $seo['image'];
@endphp
    <title>{{ $seo['title'] }}</title>
    @if ($seo['description'])
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    @unless ($seo['index'])
        <meta name="robots" content="noindex, follow">
    @endunless
    @if ($seo['canonical'])
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif
    @foreach ($seo['alternates'] as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ ui('brand.name') }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    @if ($seo['description'])
        <meta property="og:description" content="{{ $seo['description'] }}">
    @endif
    @if ($seo['og_url'])
        <meta property="og:url" content="{{ $seo['og_url'] }}">
    @endif
    <meta property="og:image" content="{{ $image['url'] }}">
    @if (str_starts_with($image['url'], 'https://'))
        <meta property="og:image:secure_url" content="{{ $image['url'] }}">
    @endif
    <meta property="og:image:type" content="{{ $image['type'] }}">
    <meta property="og:image:width" content="{{ $image['width'] }}">
    <meta property="og:image:height" content="{{ $image['height'] }}">
    <meta property="og:image:alt" content="{{ $image['alt'] }}">
    <meta property="og:locale" content="{{ $seo['og_locale'] }}">
    @foreach ($seo['og_alternates'] as $alt)
        <meta property="og:locale:alternate" content="{{ $alt }}">
    @endforeach
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image:alt" content="{{ $image['alt'] }}">
    @php $structuredData = \App\Support\StructuredData::graph($seo, isset($crumbs) && is_array($crumbs) ? $crumbs : []); @endphp
    @if ($structuredData !== null)
        {{-- schema.org (client PDF item 26; App\Support\StructuredData): a data block, never executed; it carries the
             CSP nonce all the same, and its JSON escapes "<", ">" and "&". --}}
        <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! \App\Support\StructuredData::json($structuredData) !!}</script>
    @endif
