@extends('layouts.site')

{{-- P01 Home. Sections follow 01_Homepage DOCX order; all copy is imported approved copy. --}}
@section('content')
    @include('pages.home.hero')
    @include('pages.home.services')
    @include('pages.home.who')
    @include('pages.home.review')
    @include('pages.home.navigator')
    @include('pages.home.about')
    @include('pages.home.cases')
    @include('pages.home.guides')
    @include('pages.home.faq')
    @include('pages.home.contact')
@endsection

@push('head')
    @include('partials.structured-data')
@endpush
