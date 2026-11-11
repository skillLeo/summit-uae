@props([
    'id',                     // required: short id; open it with data-sheet-open="{id}" or openSheet('{id}')
    'title',                  // required: visible title, also the dialog's accessible name
    'handle' => true,         // grab handle + drag to dismiss (bottom layout)
    'closeLabel' => null,     // accessible name of the close button (default: proposed('sheet.close'))
    'titleProposed' => false, // the title is proposed copy (adds data-copy-status)
    'static' => false,        // styleguide specimen: drawn in place, inert, never opened
])
{{--
    <x-d2.ui.sheet id="services" :title="ui('nav.practice_areas')"> … <x-slot:footer>…</x-slot:footer></x-d2.ui.sheet>

    An overlay (data-overlay, brief §3.4): bottom sheet below 768 px (--radius-overlay on the top corners, 36×5
    handle, max 90svh, white, --shadow-overlay, --scrim backdrop, --spring-sheet); at 768 px and wider the same
    markup is a side panel on the inline-end edge (resources/css/d2/components/sheet.css). Behaviour: resources/js/d2/lib/sheet.js (focus in and
    trapped, background inert, Esc, Back, drag to dismiss by the handle and head (finger or mouse) or by
    pulling the content down while it is at its top (touch; [data-sheet-no-drag] opts an area out), the
    backdrop fading with the distance, focus returns to the opener). Without the handle there is no drag.

    Render page sheets at body level with @push('sheets') … @endpush (the layout prints the stack after the
    global sheets), so no transformed ancestor can trap the fixed overlay.
    DOM ids: the dialog is #sheet-{id} (openers' aria-controls), its title #sheet-{id}-title.
    Put [autofocus] on a control to focus it instead of the title (for example a search field).
--}}
@php
    $panelId = 'sheet-'.$id;
    $titleId = $panelId.'-title';
    $closeLabel ??= proposed('sheet.close');
@endphp
@if ($static)
<div {{ $attributes->merge(['data-overlay' => true])->class(['sheet', 'sheet--static']) }} inert aria-hidden="true">
@else
<div {{ $attributes->merge(['data-overlay' => true])->class(['sheet']) }} data-sheet="{{ $id }}" hidden>
    <div class="sheet__backdrop" data-sheet-close aria-hidden="true"></div>
@endif
    <div class="sheet__panel" @unless ($static) id="{{ $panelId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}" tabindex="-1" @endunless>
        <div class="sheet__head" @if ($handle && ! $static) data-sheet-drag @endif>
            @if ($handle)
                <span class="sheet__handle" aria-hidden="true"></span>
            @endif
            <h2 class="sheet__title" @unless ($static) id="{{ $titleId }}" tabindex="-1" @endunless @if ($titleProposed) @proposed @endif>{{ $title }}</h2>
            <x-d2.ui.icon-button class="sheet__close" icon="close" :label="$closeLabel" size="48" tip="none" data-sheet-close />
        </div>
        <div class="sheet__body">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="sheet__foot">{{ $footer }}</div>
        @endisset
    </div>
</div>
