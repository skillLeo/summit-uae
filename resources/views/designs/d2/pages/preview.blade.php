{{--
    Design 2 inner pages (replaces Design 1's pages.preview by name; same PagePreviewController data:
    $seo, $pageKey, $navCurrent, $h1, $intro, $crumbs). The template is chosen from the route:
    a {service} slug → inner.service, otherwise the page code (route default 'code').
--}}
@php
    $route = request()->route();
    $serviceSlug = $route?->parameter('service');
    $serviceCode = $serviceSlug ? array_search($serviceSlug, config('summit.services'), true) : null;
    $pageCode = $serviceCode ? null : $route?->parameter('code');
    $template = match (true) {
        $serviceCode !== null && $serviceCode !== false => 'service',
        $pageCode === 'P02' => 'practice-areas',
        $pageCode === 'P03' => 'resources',
        $pageCode === 'P04' => 'case-studies',
        $pageCode === 'P05' => 'faq',
        $pageCode === 'P06' => 'about',
        $pageCode === 'P07' => 'contact',
        in_array($pageCode, ['P08', 'L01', 'L02'], true) => 'legal',
        default => 'generic',
    };
@endphp
@extends('d2::layouts.app')

@section('content')
    @includeFirst(['d2::inner.'.$template, 'd2::inner.generic'], [
        'serviceCode' => $serviceCode ?: null,
        'pageCode' => $pageCode,
    ])
@endsection
