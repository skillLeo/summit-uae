@props([
    'block',        // the block (content()->blocks('P01'): id, type, enabled, anchor, background, copy_source, data)
    'scope' => [],  // the home view's data (HomeController: $c, $services, $navServices …), for the section partial
])
{{--
    Home block "Services grid (from Practice Areas)" (docs/briefs/design-2.1-and-admin-cms.md §8.4; App\Content\Cms\HomeBlocks). Renders the section
    partial d2::sections.home.services with the view's data; the section id is the block's anchor.
--}}
@include('d2::sections.home.services', ['sectionId' => $block['anchor'], 'homeBlock' => $block] + $scope)
