{{--
    Design 2 home (replaces Design 1's pages.home by name; same HomeController data).

    Section order (Design 2.1 brief §5.1, the order of 01_Homepage_EN_Revised.docx, same in AR and RU; D26): the
    approved sections 01-09 are fixed. The switchable blocks (App\Design\D2\Pending::block(), switches in
    config('design.d2.home.blocks'); Phase 4 moves them to the admin) sit between approved sections, never inside one:
        01 hero
           [start]      "Start your matter" (pending copy)
        02 services
           [anatomy]    "Anatomy of a Document" (pending copy)
        03 who we help
        04 review       "What a Review of Your Matter Can Clarify"
           [navigator]  "Where Do You Stand?" (pending copy)
        05 about
           [team]       the "Our Team" grid: off by default (D24, client question)
        06 case studies
        07 guides
        08 questions
        09 contact      #consultation-form
    Gone in Phase 2: the trust row (D20: its four cards repeated About, Services Overview and Contact, which the
    DOCX's Section 04 note forbids) and the calculators (moved to their own page, /{locale}/calculators/).
    Phones: no sticky consultation bar on the home (service pages keep theirs).
--}}
@php
    use App\Design\D2\Pending;
@endphp
@extends('d2::layouts.app')

@section('content')
    @include('d2::sections.home.hero')
    @if (Pending::block('start'))
        @include('d2::sections.home.start')
    @endif
    @include('d2::sections.home.services')
    @if (Pending::block('anatomy'))
        @include('d2::sections.home.anatomy')
    @endif
    @include('d2::sections.home.who')
    @include('d2::sections.home.method')
    @if (Pending::block('navigator'))
        @include('d2::sections.home.navigator')
    @endif
    @include('d2::sections.home.about')
    @if (Pending::block('team'))
        @include('d2::sections.home.team')
    @endif
    @include('d2::sections.home.cases')
    @include('d2::sections.home.guides')
    @include('d2::sections.home.faq')
    @include('d2::sections.home.contact')
@endsection
