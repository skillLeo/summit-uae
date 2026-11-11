@extends('layouts.site')

{{-- Phase 2 route preview: approved breadcrumb, H1 and introduction only (noindex). --}}
@section('content')
    <div class="container">
        <header class="page-head">
            <nav class="breadcrumbs" aria-label="{{ proposed('nav.breadcrumb') }}">
                <ol>
                    @foreach ($crumbs as $crumb)
                        <li>
                            @if ($crumb['href'])
                                <a href="{{ $crumb['href'] }}">{{ $crumb['label'] }}</a>
                            @else
                                <span aria-current="page">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
            <h1>{{ $h1 }}</h1>
            @foreach ($intro as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </header>

        <div class="section">
            <div class="preview-note">
                <p>{{ proposed('preview.notice') }}</p>
                <div class="cluster">
                    <a class="btn" href="{{ \App\Support\SiteUrl::consultation() }}">{{ ui('cta.request_consultation') }}</a>
                    <a class="text-link" href="{{ \App\Support\SiteUrl::home() }}">{{ ui('nav.home') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
