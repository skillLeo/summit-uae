@php
    use App\Support\Seo;
    use App\Support\SiteUrl;
    $u01 = content()->page('U01');
    // U01 fields live at the top level of the record (NotFoundMapper).
    $c = ($u01['content'] ?? []) ?: $u01;
    $seo = Seo::make($u01['seo_title'] ?? ($u01['h1'] ?? ''), null, index: false, alternates: false);
    $pageKey = 'error';
@endphp
@extends('layouts.site')

{{-- U01: real HTTP 404 at the requested address. No countdown, no redirect, no fake search,
     and the missing URL is never echoed into the page. --}}
@section('content')
    <div class="container error-page">
        <div class="error-page__emblem" aria-hidden="true"><x-brand name="emblem" /></div>
        <p class="error-page__code ltr">{{ $u01['code_label'] ?? $c['code_label'] ?? '404' }}</p>
        <h1>{{ $u01['h1'] ?? '' }}</h1>
        <p>{{ $c['text'] ?? '' }}</p>
        <div class="error-page__actions">
            <a class="btn" href="{{ SiteUrl::home() }}">{{ $c['actions']['home_label'] ?? '' }}</a>
            <a class="btn btn--ghost" href="{{ SiteUrl::page('P02') }}">{{ $c['actions']['services_label'] ?? '' }}</a>
        </div>
        @if (! empty($c['links']))
            <div class="error-page__more">
                <p>{{ $c['more_heading'] ?? '' }}</p>
                <ul>
                    @foreach ($c['links'] as $link)
                        <li><a href="{{ target_url($link['target']) }}">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
