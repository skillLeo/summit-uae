@props([
    'block',        // the block (content()->blocks('P01'): id, type, enabled, anchor, background, copy_source, data)
    'scope' => [],  // the home view's data (HomeController: $c, $services, $navServices …), for the section partial
])
{{--
    Home block "Start your matter" (docs/briefs/design-2.1-and-admin-cms.md §8.4; App\Content\Cms\HomeBlocks). Renders the section
    partial d2::sections.home.start with the view's data; the section id is the block's anchor.
    Its Continue hands the situation to the consultation form, so it renders only while the form is on (client PDF
    item 7, App\Support\ConsultationForm; the block is off by default since then: App\Content\Cms\HomeBlocks).
--}}
@if (\App\Support\ConsultationForm::enabled())
    @include('d2::sections.home.start', ['sectionId' => $block['anchor'], 'homeBlock' => $block, 'c' => (array) ($block['data'] ?? []) + (array) ($scope['c'] ?? [])] + $scope)
@endif
