{{--
    Design 2 home: Anatomy of a Document (#anatomy; upgrade spec §5.4, §7.3 row 5, §12 C3; Design 2.1 brief D28, §6).
    A switchable block between approved sections (pages/home.blade.php, Pending::block('anatomy')): all copy is proposed.

    Document choice: the one chip (<x-d2.ui.chip>, aria-pressed toggle buttons) in a toolbar (one Tab stop, arrow keys).
    Each document panel:
      - the desk: the sample document (role="img", data-illustration) and, over it, one hotspot button per part
        (.anatomy__hotspots: one Tab stop, arrow keys; the 2px focus ring is drawn on the document around the field);
        d2Anatomy measures each field and places its button from 768 px (below, the fields are too small to be targets:
        a tap on a field still selects it, and the parts list is the control);
      - the parts list beside the document: a vertical toolbar of toggle buttons (aria-pressed, aria-controls → note);
      - the explanation card ("What it can show", "What we check"), announced politely, and the consultation link.
    The list and the hotspots stay in sync: hovering or focusing a part highlights its field on the document
    ([data-peek] on the field) and hovering or focusing a hotspot highlights its row (.is-peek); choosing either
    (click, Enter, Space) selects the part in both (aria-pressed), marks the field ([data-hot]) and shows its note.

    Without JavaScript: the first document, every one of its explanations listed in the card, and the consultation link
    (the document chips, the parts list and the hotspots are hidden: they would do nothing).
    Behaviour: resources/js/d2/components/anatomy.js (d2Anatomy). Styles: resources/css/d2/sections/anatomy.css.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Docs;
    use App\Design\D2\Prefill;

    $types = Docs::ANATOMY;
    $part = fn (string $type, string $id, string $key) => d2p('anatomy.fields.'.$type.'.'.$id.'.'.$key);
    $proposed = app()->isProduction() ? null : 'proposed';
@endphp
<section class="section anatomy" id="anatomy" aria-labelledby="anatomy-title">
    <div class="container">
        <div class="section__head">
            <h2 id="anatomy-title" @proposed>{{ d2p('anatomy.title') }}</h2>
            <p @proposed>{{ d2p('anatomy.intro') }}</p>
        </div>

        <div class="anatomy__app" x-data="d2Anatomy" data-anatomy>
            <div class="anatomy__docs" role="toolbar" aria-label="{{ d2p('anatomy.docs_label') }}" @proposed data-anatomy-docs>
                @foreach ($types as $i => $type)
                    <x-d2.ui.chip :pressed="$i === 0" data-anatomy-doc="{{ $type }}" aria-controls="anatomy-panel-{{ $type }}" :data-copy-status="$proposed">{{ Docs::name($type) }}</x-d2.ui.chip>
                @endforeach
            </div>

            @foreach ($types as $i => $type)
                @php
                    $hotspots = Docs::HOTSPOTS[$type];
                    $service = Docs::SERVICE[$type] ?? null;
                    $name = Docs::name($type);
                @endphp
                <div class="anatomy__panel" id="anatomy-panel-{{ $type }}" data-anatomy-panel="{{ $type }}" @if ($i > 0) hidden @endif>
                    <div class="anatomy__desk">
                        <div class="anatomy__plate" data-anatomy-plate>
                            <div class="anatomy__figure" role="img" aria-label="{{ Docs::alt($type) }}" @proposed data-illustration>
                                <x-dynamic-component :component="'d2.doc.'.$type" variant="anatomy" :hot="$hotspots[0]" />
                            </div>
                            <div class="anatomy__hotspots" role="toolbar" aria-orientation="vertical" aria-label="{{ d2p('anatomy.hotspots_label', ['document' => $name]) }}" @proposed data-anatomy-hotspots>
                                @foreach ($hotspots as $j => $id)
                                    <button type="button" class="anatomy__hotspot" data-anatomy-hotspot="{{ $id }}" aria-pressed="{{ $j === 0 ? 'true' : 'false' }}"
                                        aria-controls="anatomy-{{ $type }}-{{ $id }}" aria-label="{{ $part($type, $id, 'name') }}" tabindex="{{ $j === 0 ? '0' : '-1' }}" @proposed></button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="anatomy__inspector">
                        <div class="anatomy__parts" role="toolbar" aria-orientation="vertical" aria-label="{{ d2p('anatomy.parts_label', ['document' => $name]) }}" @proposed data-anatomy-parts>
                            @foreach ($hotspots as $j => $id)
                                <button type="button" class="anatomy__part" data-anatomy-part="{{ $id }}" aria-pressed="{{ $j === 0 ? 'true' : 'false' }}"
                                    aria-controls="anatomy-{{ $type }}-{{ $id }}" tabindex="{{ $j === 0 ? '0' : '-1' }}" @proposed>{{ Bidi::isolate($part($type, $id, 'name')) }}</button>
                            @endforeach
                        </div>

                        <ol class="anatomy__notes" role="list">
                            @foreach ($hotspots as $j => $id)
                                <li @class(['anatomy__note', 'is-current' => $j === 0]) id="anatomy-{{ $type }}-{{ $id }}" data-anatomy-note="{{ $id }}"
                                    data-announce="{{ d2p('anatomy.announce', ['field' => $part($type, $id, 'name'), 'show' => $part($type, $id, 'show')]) }}">
                                    <h3 class="anatomy__name" @proposed>{{ Bidi::isolate($part($type, $id, 'name')) }}</h3>
                                    <dl class="anatomy__facts">
                                        <div class="anatomy__fact">
                                            <dt @proposed>{{ d2p('anatomy.can_show') }}</dt>
                                            <dd @proposed>{{ Bidi::isolate($part($type, $id, 'show')) }}</dd>
                                        </div>
                                        <div class="anatomy__fact">
                                            <dt @proposed>{{ d2p('anatomy.we_check') }}</dt>
                                            <dd @proposed>{{ Bidi::isolate($part($type, $id, 'check')) }}</dd>
                                        </div>
                                    </dl>
                                </li>
                            @endforeach
                        </ol>

                        <div class="anatomy__foot">
                            <x-d2.ui.button variant="secondary" :href="Prefill::url(['service' => $service])" data-anatomy-cta data-service="{{ $service }}">{{ ui('cta.request_consultation') }}</x-d2.ui.button>
                            <p class="anatomy__caveat" @proposed><x-d2.ui.icon name="info" size="20" />{{ d2p('anatomy.caveat') }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
