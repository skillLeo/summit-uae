{{--
    Styleguide: document upload (<x-d2.upload.dropzone>, owner: pages-people). Every state of the one optional
    attachment, a live specimen (the browser checks run, nothing is sent) and a preview of the control in the
    wizard's Documents step. Annotations: lang/en/styleguide_d2/upload.php (English on every language
    version, marked lang="en"). Specimens render with specimen=… whatever the feature flag says and never
    carry name="attachment".
--}}
@php
    use App\Content\NavigatorData;
    use App\Design\D2\Bidi;

    $t = fn (string $key) => __('styleguide_d2/upload.'.$key);
    $en = app()->isLocale('en') ? '' : 'lang=en';
    // Design 2 shows the control only with attachments enabled AND a backend that scans uploads (it does not today).
    $enabled = \App\Design\D2\Attachment::enabled();
    $sample = ['name' => $t('sample_file'), 'size' => 2_440_000, 'type' => 'pdf'];
    $states = [
        ['idle', 'state_idle', []],
        ['hover', 'state_hover', []],
        ['focus', 'state_focus', []],
        ['over', 'state_over', []],
        ['file', 'state_file', ['specimen-file' => $sample]],
        ['error', 'state_error_type', ['specimen-error' => 'error_type']],
        ['error', 'state_error_size', ['specimen-error' => 'error_size']],
        ['error', 'state_error_count', ['specimen-error' => 'error_count']],
        ['error', 'state_error_rejected', ['specimen-error' => 'error_rejected']],
        ['disabled', 'state_disabled', []],
        ['plain', 'state_plain', []],
    ];
    $s06 = collect((new NavigatorData(content()))->services())->firstWhere('code', 'S06') ?? [];
    $docs = array_slice(array_values((array) ($s06['documents'] ?? [])), 0, 4);
@endphp
<section class="sg-section" id="sg-upload">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>
    <p class="sg-note" {{ $en }}><code class="sg-code">App\Design\D2\Attachment::enabled()</code> — {{ $enabled ? $t('flag_on') : $t('flag_off') }}</p>

    {{-- ------------------------------------------------------------------ live --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('live') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('live_note') }}</p>
    <div class="sg-grid sg-grid--wide">
        <div class="sg-specimen" data-sg="upload-live">
            <x-d2.upload.dropzone prefix="sg-up-live" specimen="live" />
        </div>
    </div>

    {{-- ------------------------------------------------------------------ states --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('states') }}</h3>
    <div class="sg-grid sg-grid--wide">
        @foreach ($states as $i => [$state, $label, $extra])
            <div class="sg-specimen" data-sg="upload-{{ $state }}">
                <x-d2.upload.dropzone :prefix="'sg-up-'.$i" :specimen="$state"
                    :specimen-file="$extra['specimen-file'] ?? null" :specimen-error="$extra['specimen-error'] ?? null" />
                <p class="sg-label" {{ $en }}>{{ $t($label) }}</p>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ in the wizard --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('in_wizard') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('in_wizard_note') }}</p>
    <div class="sg-grid sg-grid--full">
        <div class="sg-specimen">
            <div class="wizard is-stepped sg-upload-wizard" data-sg="upload-wizard">
                <x-d2.wizard.step :n="4" step-key="documents" prefix="sg-up-wz">
                    @if ($docs !== [])
                        <x-d2.ui.field as="fieldset" id="sg-up-wz-documents" :label="(string) ($s06['headings']['documents'] ?? '')">
                            <div class="wizard__docs">
                                @foreach ($docs as $i => $doc)
                                    <div class="wizard__doc">
                                        <x-d2.ui.checkbox :id="'sg-up-wz-doc-'.($i + 1)" :value="$i" :checked="$i === 0">
                                            <span>{{ Bidi::isolate((string) $doc) }}</span>
                                        </x-d2.ui.checkbox>
                                    </div>
                                @endforeach
                            </div>
                        </x-d2.ui.field>
                    @endif
                    <x-d2.upload.dropzone prefix="sg-up-wz" specimen="live" />
                </x-d2.wizard.step>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ contract --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('contract') }}</h3>
    <p class="sg-note" {{ $en }}>{{ $t('contract_note') }}</p>
</section>
