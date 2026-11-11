{{--
    Design 2 home (replaces Design 1's pages.home by name; same HomeController data).

    The page is its blocks (docs/briefs/design-2.1-and-admin-cms.md §8.4): the blocks of page P01 in the CMS, in their
    order, each rendered by its component in components/d2/blocks/ (App\Content\Cms\HomeBlocks lists the types and the
    defaults the import writes). A block renders when it is on and, when its copy is pending, pending copy is shown
    (App\Design\D2\Pending::shows()). Its anchor is the section's id. The default order (brief §5.1, the order of
    01_Homepage_EN_Revised.docx, same in AR and RU; D26): the approved sections 01-09 are fixed, the switchable blocks
    sit between approved sections, never inside one:
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
    use App\Content\Cms\HomeBlocks;
    use App\Design\D2\Pending;
@endphp
@extends('d2::layouts.app')

@section('content')
    @php $homeScope = $__data; @endphp
    @foreach (content()->blocks(HomeBlocks::PAGE) as $homeBlockItem)
        @if (in_array($homeBlockItem['type'], HomeBlocks::types(), true) && Pending::shows($homeBlockItem))
            <x-dynamic-component :component="'d2.blocks.'.$homeBlockItem['type']" :block="$homeBlockItem" :scope="$homeScope" />
        @endif
    @endforeach
@endsection
