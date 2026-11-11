@props([
    'prefix' => 'wz',          // id namespace: the input is {prefix}-attachment, its error {prefix}-attachment-error (the wizard's field contract)
    'error' => null,           // the server's message for "attachment" (no-JS round trip: $errors->first('attachment'))
    'specimen' => null,        // styleguide only, renders whatever the feature flag says and never posts (no name):
                               //   live (interactive) · idle · hover · focus · over · file · error · disabled · plain (as without JavaScript)
    'specimenFile' => null,    // styleguide "file" state: ['name' => …, 'size' => bytes, 'type' => pdf | jpeg | png]
    'specimenError' => null,   // styleguide "error" state: error_type | error_size | error_count | error_rejected
])
{{--
    <x-d2.upload.dropzone :prefix="$p" :error="$e('attachment')" />

    The consultation form's one optional document (docs/enquiries.md §6, Contact DOCX rule 8). Rendered only
    while config('summit.features.attachments_enabled') is true: with the flag off (Phase 1) it prints
    nothing and the form looks complete without it. One file; PDF, JPEG or PNG (the server reads the type
    from the content); at most config('summit.enquiries.attachment_max_bytes') (10,000,000 bytes = the
    approved "10 MB").

    Without JavaScript: <x-d2.ui.field> with the approved label and help, and the native file input (its
    button styled as a secondary button); the form posts it as `attachment` (the form needs
    enctype="multipart/form-data"). With JavaScript (Alpine d2Upload, resources/js/d2/components/upload.js)
    the same input lies invisible over the whole dashed zone: click, Enter or Space opens the picker, a file
    can be dropped on it, the checks run in the browser with the approved ui('form.attachment.error_*')
    messages, and an accepted file shows as a chip with its name, format, size and "Remove Document".
    Errors follow the field contract (#{id}-error, .field__error-text, aria-describedby while shown).
    Styles: resources/css/d2/components/upload.css. Styleguide: d2::styleguide.upload.
    Design 2.1 (Phase 1, home-b): only the upload glyph remains (brief 3.6 rule 5 allows it); the file glyphs
    and the dot between the file's format and size are gone (D02, D04): the meta row's gap separates them.
--}}
@php
    $enabled = (bool) config('summit.features.attachments_enabled');
    $states = ['live', 'idle', 'hover', 'focus', 'over', 'file', 'error', 'disabled', 'plain'];
    $state = $specimen === null ? null : (in_array($specimen, $states, true) ? $specimen : 'idle');
@endphp
@if ($enabled || $state !== null)
    @php
        $id = $prefix.'-attachment';
        $locale = app()->getLocale();
        $maxBytes = max(1, (int) config('summit.enquiries.attachment_max_bytes', 10_000_000));
        $errorKeys = ['error_type', 'error_size', 'error_count', 'error_rejected'];
        $messages = array_combine($errorKeys, array_map(fn (string $key) => ui('form.attachment.'.$key), $errorKeys));
        $serverError = is_string($error) && trim($error) !== '' ? $error : null;
        $shownError = $serverError ?? ($state === 'error' ? ($messages[$specimenError] ?? $messages['error_type']) : null);
        $real = $state === null;                       // a real control: named, posted, enhanced
        $enhance = $state !== 'plain' && in_array($state, [null, 'live'], true);
        $forced = ! $real && ! in_array($state, ['live', 'plain'], true);   // a static picture of one state
        $types = (array) trans('proposed.d2.upload.type');

        // The styleguide's "file" state: the chip as the script fills it (same size rule as formatSize()).
        $file = null;
        if ($state === 'file' && is_array($specimenFile)) {
            $bytes = max(0, (int) ($specimenFile['size'] ?? 0));
            $mb = $bytes >= 1_000_000;
            $value = $mb ? round($bytes / 1_000_000, 1) : max(1, (int) round($bytes / 1000));
            $sep = $locale === 'ru' ? ',' : '.';
            $number = $mb ? rtrim(rtrim(number_format($value, 1, $sep, ''), '0'), $sep) : (string) $value;
            $file = [
                'name' => (string) ($specimenFile['name'] ?? ''),
                'size' => d2p($mb ? 'upload.size_mb' : 'upload.size_kb', ['size' => $number]),
                'type' => (string) ($types[$specimenFile['type'] ?? 'pdf'] ?? ''),
            ];
        }

        $describedBy = implode(' ', array_filter([$id.'-hint', $shownError !== null ? $id.'-error' : null, $file ? $id.'-file-text' : null]));
        $copy = [
            'locale' => $locale,
            'errors' => $messages,
            'attached' => d2p('upload.attached'),
            'removed' => d2p('upload.removed'),
            'sizes' => ['kb' => d2p('upload.size_kb'), 'mb' => d2p('upload.size_mb')],
            'types' => $types,
        ];
        $json = json_encode($copy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @endphp
    <x-d2.ui.field :id="$id" :label="ui('form.attachment.label')" :hint="ui('form.attachment.help')"
        :error="$shownError ?? $messages['error_type']" :invalid="$shownError !== null" :optional-label="true"
        {{ $attributes->class(['upload-field']) }}>
        <div @class(['upload', 'is-enhanced' => $forced, 'is-specimen' => ! $real, 'is-plain' => $state === 'plain', 'is-disabled' => $state === 'disabled'])
            data-upload data-max-bytes="{{ $maxBytes }}" @if ($enhance) x-data="d2Upload" @endif>
            <script type="application/json" data-upload-copy>{!! $json !!}</script>

            <div @class([
                'upload__zone',
                'is-hover' => $state === 'hover',
                'is-focus' => $state === 'focus',
                'is-over' => $state === 'over',
                'has-file' => $file !== null,
                'is-invalid' => $shownError !== null,
            ]) data-upload-zone>
                <span class="upload__icon" aria-hidden="true"><x-d2.ui.icon name="upload" size="24" /></span>
                <span class="upload__prompt" aria-hidden="true">
                    <span class="upload__prompt-idle" @proposed>{{ d2p('upload.prompt') }}</span>
                    <span class="upload__prompt-over" @proposed>{{ d2p('upload.drop') }}</span>
                </span>
                <input class="upload__input" type="file" id="{{ $id }}" @if ($real) name="attachment" @endif
                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                    aria-describedby="{{ $describedBy }}" @if ($shownError !== null) aria-invalid="true" @endif
                    @if ($state === 'disabled') disabled @endif data-upload-input>
                {{-- A second label of the same input: its accessible name says what the zone shows ("Choose a file"). --}}
                <label class="upload__button" for="{{ $id }}" id="{{ $id }}-choose" @proposed>
                    <span>{{ d2p('upload.choose') }}</span>
                </label>
            </div>

            <div class="upload__file" id="{{ $id }}-file" data-upload-file @unless ($file) hidden @endunless>
                <span class="upload__file-text" id="{{ $id }}-file-text" data-upload-file-text>
                    <bdi class="upload__file-name" dir="auto" data-upload-name>{{ $file['name'] ?? '' }}</bdi>
                    <span class="upload__file-meta" @proposed>
                        <span class="upload__file-type" data-upload-type>{{ $file['type'] ?? '' }}</span><bdi class="upload__file-size" data-upload-size>{{ $file['size'] ?? '' }}</bdi>
                    </span>
                </span>
                <x-d2.ui.button variant="text" size="40" icon="close" class="upload__remove" data-upload-remove :disabled="$state === 'disabled'">{{ ui('form.attachment.remove') }}</x-d2.ui.button>
            </div>
        </div>
    </x-d2.ui.field>
@endif
