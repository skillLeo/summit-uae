@props([
    'id',                     // required: short id; open it with data-modal-open="{id}" or openModal('{id}')
    'title',                  // required: the dialog's accessible name (visible unless titleHidden)
    'titleHidden' => false,   // keep the title for assistive technology only (the command palette)
    'titleProposed' => false, // the title is proposed copy (adds data-copy-status)
    'closable' => true,       // show the close button (Esc and the backdrop always close)
    'closeLabel' => null,     // accessible name of the close button (default: proposed('sheet.close'))
    'static' => false,        // styleguide specimen: drawn in place, inert, never opened
])
{{--
    <x-d2.ui.modal id="palette" :title="…" :title-hidden="true">
        <x-slot:head> search field, kbd hint … </x-slot:head>
        results …
        <x-slot:footer> actions … </x-slot:footer>
    </x-d2.ui.modal>

    An overlay (data-overlay, brief §3.4). Native <dialog> opened with showModal() (resources/js/d2/components/
    modal.js): top layer, page inert, 640 px wide (--modal-w), --radius-overlay, --shadow-overlay, the --scrim
    backdrop; full screen below 768 px. Enter and exit with
    transform/opacity only (opacity only under reduced motion). Esc, the backdrop, [data-modal-close] and
    the Back gesture close it; focus returns to the opener.
    Slots: default = body (scrolls), head = extra header content between the title and the close button
    (for example the palette's search field), footer = action row. DOM ids: #modal-{id}, #modal-{id}-title.
    Put [autofocus] on a control to focus it instead of the title.
--}}
@php
    $dialogId = 'modal-'.$id;
    $titleId = $dialogId.'-title';
    $closeLabel ??= proposed('sheet.close');
    $tag = $static ? 'div' : 'dialog';
@endphp
<{{ $tag }} {{ $attributes->merge(['data-overlay' => true])->class(['modal', 'modal--static' => $static]) }}
    @if ($static) inert aria-hidden="true" @else id="{{ $dialogId }}" data-modal="{{ $id }}" aria-labelledby="{{ $titleId }}" @endif>
    <div class="modal__panel">
        <div @class(['modal__head', 'modal__head--custom' => isset($head)])>
            <h2 @class(['modal__title', 'sr-only' => $titleHidden]) @unless ($static) id="{{ $titleId }}" tabindex="-1" @endunless @if ($titleProposed) @proposed @endif>{{ $title }}</h2>
            @isset($head)
                <div class="modal__head-slot">{{ $head }}</div>
            @endisset
            @if ($closable)
                <x-d2.ui.icon-button class="modal__close" icon="close" :label="$closeLabel" size="48" tip="none" data-modal-close />
            @endif
        </div>
        <div class="modal__body">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="modal__foot">{{ $footer }}</div>
        @endisset
    </div>
</{{ $tag }}>
