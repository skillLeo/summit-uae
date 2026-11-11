{{--
    Design 2 calculators page, /{locale}/calculators/ (Design 2.1 brief §5.1; App\Http\Controllers\CalculatorsController).
    The two estimators moved here from the home page (Phase 2): the gratuity estimator (<x-d2.tool.gratuity>) and the
    Dubai rent increase check (<x-d2.tool.rent>), chosen with underline tabs (D35, <x-d2.ui.tabs>). Every text is
    pending client approval; the page is noindex and renders only while pending copy is shown (Pending::visible()).

    - One H1 (the head); each tool is named by a visually hidden H2 (its tab names it on screen).
    - Deep links: #tool-gratuity and #tool-rent open that calculator (d2Tabs; anything inside a panel, such as
      #rent-result, opens its panel too). Switching writes the panel's address (sync-hash), so it can be shared.
    - Without JavaScript the tabs are jump links and both calculators show one after the other.
    - Every figure is calculated in the browser; nothing is sent until the visitor submits the consultation form
      (the tools only prefill it). Linked from home section 09, the Employment Law page (S06, #tool-gratuity) and
      the Property and Tenancy page (S05, #tool-rent) through <x-d2.tool.link>.
--}}
@extends('d2::layouts.app')

@section('content')
    <x-d2.inner.head :title="d2p('tools.section.title')" :crumbs="$crumbs" :intro="[d2p('tools.section.intro')]" class="calculators__head" />

    <section class="section tools" id="tools" aria-labelledby="page-title" x-data="d2Tools">
        <div class="container">
            <x-d2.ui.tabs class="tools__tabs" :tabs="[['tool-gratuity', d2p('tools.switch.gratuity')], ['tool-rent', d2p('tools.switch.rent')]]"
                :label="d2p('tools.switch.label')" :sync-hash="true" data-tools-switch :data-copy-status="app()->isProduction() ? null : 'proposed'">
                <div class="tools__panels">
                    <x-d2.ui.tab-panel id="tool-gratuity" class="tools__panel tools__panel--gratuity" data-tool-panel="gratuity">
                        <x-d2.tool.gratuity id-prefix="gr" heading-level="2" />
                    </x-d2.ui.tab-panel>
                    <x-d2.ui.tab-panel id="tool-rent" class="tools__panel tools__panel--rent" data-tool-panel="rent">
                        <x-d2.tool.rent id-prefix="rent" heading-level="2" />
                    </x-d2.ui.tab-panel>
                </div>
            </x-d2.ui.tabs>
        </div>
    </section>
@endsection
