{{--
    Styleguide: Design 2 sample documents (<x-d2.doc.*>) and the Anatomy of a Document section, every
    state. Annotations are internal: lang/en/styleguide_d2/documents.php (English on every page, marked
    lang="en"). The documents are proposed illustration copy (lang/{locale}/proposed-d2/docs.php) in the
    page language and direction. Pins (Alpine d2DocPins) are drawn from each root's data-callouts.
--}}
@php
    use App\Design\D2\Docs;

    $t = fn (string $key) => __('styleguide_d2/documents.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';
    $all = ['cheque', 'memo', 'contract', 'tenancy', 'transfer', 'messages', 'notice'];
    $part = fn (string $type, string $id) => d2p('anatomy.fields.'.$type.'.'.$id.'.name');
@endphp
<section class="sg-section" id="sg-documents">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ contract --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('contract') }}</h3>
    <ul class="sg-doc-notes" {{ $en }}>
        @foreach ($t('contract_items') as $item)
            <li class="sg-note">{{ $item }}</li>
        @endforeach
    </ul>

    {{-- ------------------------------------------------------------------ stage --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('stage') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('stage_note') }}</p>
    <div class="sg-docs">
        @foreach ($all as $type)
            <figure class="sg-specimen sg-doc" x-data="d2DocPins">
                <div class="sg-doc__stage" role="img" aria-label="{{ Docs::alt($type) }}" @proposed>
                    <x-dynamic-component :component="'d2.doc.'.$type" variant="stage" />
                </div>
                <figcaption class="sg-doc__caption">
                    <span class="sg-label" {{ $en }}><code class="sg-code">&lt;x-d2.doc.{{ $type }}/&gt;</code> {{ implode(' × ', Docs::SIZE[$type]) }} px</span>
                    <ol class="sg-doc__callouts">
                        @foreach (Docs::callouts($type) as $i => $c)
                            <li class="sg-label"><span class="sg-doc__num">{{ $i + 1 }}</span> <span @proposed>{{ $c['label'] }}</span> <span class="sg-code">{{ $c['id'] }}, x {{ $c['x'] }}, y {{ $c['y'] }}, {{ $c['side'] }}</span></li>
                        @endforeach
                    </ol>
                </figcaption>
            </figure>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ mobile --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('mobile') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('mobile_note') }}</p>
    <div class="sg-docs">
        @foreach (['cheque', 'contract', 'tenancy'] as $type)
            <figure class="sg-specimen sg-doc">
                <div class="sg-doc__frame" role="img" aria-label="{{ Docs::alt($type) }}" @proposed>
                    <x-dynamic-component :component="'d2.doc.'.$type" variant="mobile" />
                </div>
                <figcaption class="sg-label" {{ $en }}><code class="sg-code">variant="mobile"</code> {{ $type }}, {{ round(Docs::SIZE[$type][0] * Docs::SCALE['mobile']) }} × {{ round(Docs::SIZE[$type][1] * Docs::SCALE['mobile']) }} px {{ $t('in_stage') }}</figcaption>
            </figure>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ still --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('still') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('still_note') }}</p>
    <div class="sg-docs">
        @foreach ($all as $type)
            <figure class="sg-specimen sg-doc">
                <div class="sg-doc__stage" role="img" aria-label="{{ Docs::alt($type) }}" @proposed>
                    <x-dynamic-component :component="'d2.doc.'.$type" variant="still" />
                </div>
                <figcaption class="sg-label" {{ $en }}><code class="sg-code">variant="still"</code> {{ $type }}</figcaption>
            </figure>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ anatomy variant --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('anatomy_variant') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('anatomy_variant_note') }}</p>
    <div class="sg-doc-column">
        @foreach (Docs::ANATOMY as $type)
            <figure class="sg-doc sg-doc--column">
                <div class="sg-doc__desk" role="img" aria-label="{{ Docs::alt($type) }}" @proposed>
                    <x-dynamic-component :component="'d2.doc.'.$type" variant="anatomy" />
                </div>
                <figcaption class="sg-label" {{ $en }}><code class="sg-code">variant="anatomy"</code> {{ $type }}</figcaption>
            </figure>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ selected field --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('hot') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('hot_note') }}</p>
    <div class="sg-docs">
        @foreach (Docs::HOTSPOTS['cheque'] as $id)
            <figure class="sg-specimen sg-doc">
                <div class="sg-doc__stage" role="img" aria-label="{{ Docs::alt('cheque') }}" @proposed>
                    <x-d2.doc.cheque variant="still" :hot="$id" />
                </div>
                <figcaption class="sg-label"><span {{ $en }}><code class="sg-code">:hot="'{{ $id }}'"</code></span> <span @proposed>{{ $part('cheque', $id) }}</span></figcaption>
            </figure>
        @endforeach
        @foreach (['contract' => 'wage', 'tenancy' => 'rent', 'transfer' => 'txid', 'notice' => 'hearing'] as $type => $id)
            <figure class="sg-specimen sg-doc">
                <div class="sg-doc__stage" role="img" aria-label="{{ Docs::alt($type) }}" @proposed>
                    <x-dynamic-component :component="'d2.doc.'.$type" variant="still" :hot="$id" />
                </div>
                <figcaption class="sg-label"><span {{ $en }}><code class="sg-code">{{ $type }} :hot="'{{ $id }}'"</code></span> <span @proposed>{{ $part($type, $id) }}</span></figcaption>
            </figure>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ watermark --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('watermark') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('watermark_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            {{-- A document's watermark on paper: illustration (its mono, capitals and tracking stay inside it). --}}
            <div class="sg-wm doc" aria-hidden="true" data-illustration>
                <span class="doc__wm sg-wm__block">
                    @foreach (Docs::watermark() as $line)
                        <span class="doc__wm-line" lang="{{ $line['lang'] }}" dir="{{ $line['dir'] }}" data-wm="{{ $line['text'] }}"></span>
                    @endforeach
                </span>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('watermark_full') }}</p>
        </div>
        <div class="sg-specimen">
            <div class="sg-wm sg-wm--min doc" aria-hidden="true" data-illustration>
                <span class="doc__wm sg-wm__block">
                    @foreach (Docs::watermark() as $line)
                        <span class="doc__wm-line" lang="{{ $line['lang'] }}" dir="{{ $line['dir'] }}" data-wm="{{ $line['text'] }}"></span>
                    @endforeach
                </span>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('watermark_min') }}</p>
        </div>
        <div class="sg-specimen">
            <ul class="stack" {{ $en }}>
                @foreach ($t('watermark_rules') as $rule)
                    <li class="t-meta">{{ $rule }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ anatomy section --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('section') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('section_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen">
            <div class="anatomy__docs">
                <x-d2.ui.chip :pressed="true" tabindex="-1">{{ Docs::name('cheque') }}</x-d2.ui.chip>
                <x-d2.ui.chip :pressed="false" class="is-hover" tabindex="-1">{{ Docs::name('contract') }}</x-d2.ui.chip>
                <x-d2.ui.chip :pressed="false" class="is-focus" tabindex="-1">{{ Docs::name('tenancy') }}</x-d2.ui.chip>
                <x-d2.ui.chip :pressed="false" class="is-active" tabindex="-1">{{ Docs::name('transfer') }}</x-d2.ui.chip>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('chips_docs') }}</p>
        </div>
        <div class="sg-specimen">
            {{-- The parts list (D28): pressed, lit by its hovered hotspot or by the pointer (.is-peek), keyboard focus, rest. --}}
            <div class="anatomy__parts">
                <button type="button" class="anatomy__part" aria-pressed="true" tabindex="-1">{{ $part('cheque', 'date') }}</button>
                <button type="button" class="anatomy__part is-peek" aria-pressed="false" tabindex="-1">{{ $part('cheque', 'payee') }}</button>
                <button type="button" class="anatomy__part is-focus" aria-pressed="false" tabindex="-1">{{ $part('cheque', 'words') }}</button>
                <button type="button" class="anatomy__part" aria-pressed="false" tabindex="-1">{{ $part('cheque', 'figures') }}</button>
            </div>
            <p class="sg-label" {{ $en }}>{{ $t('chips_parts') }}</p>
        </div>
        <div class="sg-specimen">
            <ol class="anatomy__notes" role="list">
                <li class="anatomy__note is-current">
                    <h4 class="anatomy__name">{{ $part('cheque', 'memo') }}</h4>
                    <dl class="anatomy__facts">
                        <div class="anatomy__fact"><dt>{{ d2p('anatomy.can_show') }}</dt><dd>{{ d2p('anatomy.fields.cheque.memo.show') }}</dd></div>
                        <div class="anatomy__fact"><dt>{{ d2p('anatomy.we_check') }}</dt><dd>{{ d2p('anatomy.fields.cheque.memo.check') }}</dd></div>
                    </dl>
                </li>
            </ol>
            <p class="sg-label" {{ $en }}>{{ $t('card') }}</p>
        </div>
        <div class="sg-specimen">
            <ol class="anatomy__notes anatomy__notes--all" role="list">
                @foreach (array_slice(Docs::HOTSPOTS['transfer'], 0, 3) as $id)
                    <li class="anatomy__note">
                        <h4 class="anatomy__name">{{ \App\Design\D2\Bidi::isolate($part('transfer', $id)) }}</h4>
                        <dl class="anatomy__facts">
                            <div class="anatomy__fact"><dt>{{ d2p('anatomy.can_show') }}</dt><dd>{{ \App\Design\D2\Bidi::isolate(d2p('anatomy.fields.transfer.'.$id.'.show')) }}</dd></div>
                            <div class="anatomy__fact"><dt>{{ d2p('anatomy.we_check') }}</dt><dd>{{ \App\Design\D2\Bidi::isolate(d2p('anatomy.fields.transfer.'.$id.'.check')) }}</dd></div>
                        </dl>
                    </li>
                @endforeach
            </ol>
            <p class="sg-label" {{ $en }}>{{ $t('card_nojs') }}</p>
        </div>
    </div>
    <ul class="sg-doc-notes" {{ $en }}>
        @foreach ($t('section_items') as $item)
            <li class="sg-note">{{ $item }}</li>
        @endforeach
    </ul>
    <div class="sg-doc-live">
        @include('d2::sections.home.anatomy')
    </div>
</section>
