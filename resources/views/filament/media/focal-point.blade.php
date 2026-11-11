{{--
    The focal point picker (App\Filament\Resources\MediaLibrary\Forms\FocalPointPicker; brief §8.9): the image is one
    button (click = set the point, arrow keys = move it by 1 %, Shift + arrow = 5 %), a marker shows the point, and two
    number fields set x and y exactly. Image coordinates are physical (from the image's left and top edge), so the box is
    dir="ltr" in every admin language; inset-inline-start is then the left edge. State: [x, y] with one decimal, or null.
--}}
@php
    $url = $getImageUrl();
    $statePath = $getStatePath();
    $id = $getId();
    $disabled = $isDisabled();
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="cms-focal"
        x-data="{
            state: $wire.$entangle(@js($statePath)),
            get x() { return Array.isArray(this.state) && this.state.length === 2 ? Number(this.state[0]) : null },
            get y() { return Array.isArray(this.state) && this.state.length === 2 ? Number(this.state[1]) : null },
            round(v) { return Math.round(Math.min(100, Math.max(0, v)) * 10) / 10 },
            set(x, y) { this.state = [this.round(x), this.round(y)] },
            pick(e) {
                const r = e.currentTarget.getBoundingClientRect();
                if (!r.width || !r.height) return;
                this.set((e.clientX - r.left) / r.width * 100, (e.clientY - r.top) / r.height * 100);
            },
            key(e) {
                const s = e.shiftKey ? 5 : 1;
                const m = { ArrowLeft: [-s, 0], ArrowRight: [s, 0], ArrowUp: [0, -s], ArrowDown: [0, s] }[e.key];
                if (!m) return;
                e.preventDefault();
                this.set((this.x ?? 50) + m[0], (this.y ?? 50) + m[1]);
            },
            field(i, v) {
                if (v === '' || v === null || isNaN(Number(v))) return;
                const p = [this.x ?? 50, this.y ?? 50];
                p[i] = Number(v);
                this.set(p[0], p[1]);
            },
        }"
    >
        @if ($url !== null)
            <div class="cms-focal__frame" dir="ltr">
                <button
                    type="button"
                    class="cms-focal__image"
                    id="{{ $id }}-image"
                    @disabled($disabled)
                    x-on:click="pick($event)"
                    x-on:keydown="key($event)"
                    aria-describedby="{{ $id }}-readout"
                    aria-label="{{ __('cms_media.focal.image_label') }}"
                >
                    <img src="{{ $url }}" alt="" draggable="false" decoding="async">
                    <span
                        class="cms-focal__marker"
                        x-cloak
                        x-show="x !== null"
                        x-bind:style="x !== null ? `inset-inline-start:${x}%;inset-block-start:${y}%` : ''"
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
            <p class="cms-focal__hint">{{ __('cms_media.focal.hint') }}</p>
        @elseif ($getEmptyText())
            <p class="cms-focal__hint">{{ $getEmptyText() }}</p>
        @endif

        <div class="cms-focal__fields">
            @foreach (['x' => 0, 'y' => 1] as $axis => $index)
                <label class="cms-focal__field" for="{{ $id }}-{{ $axis }}">
                    <span class="cms-focal__label">{{ __('cms_media.focal.'.$axis) }}</span>
                    <x-filament::input.wrapper :disabled="$disabled" suffix="%">
                        <x-filament::input
                            type="number"
                            id="{{ $id }}-{{ $axis }}"
                            min="0"
                            max="100"
                            step="0.1"
                            inputmode="decimal"
                            dir="ltr"
                            :disabled="$disabled"
                            x-bind:value="{{ $axis }} ?? ''"
                            x-on:change="field({{ $index }}, $event.target.value)"
                        />
                    </x-filament::input.wrapper>
                </label>
            @endforeach
            @unless ($disabled)
                <x-filament::button color="gray" size="sm" x-show="x !== null" x-on:click="state = null" class="cms-focal__clear">
                    {{ __('cms_media.focal.clear') }}
                </x-filament::button>
            @endunless
        </div>
        <p class="cms-focal__readout" id="{{ $id }}-readout" aria-live="polite"
            x-text="x === null ? @js(__('cms_media.focal.none')) : @js(__('cms_media.focal.readout', ['x' => '__X__', 'y' => '__Y__'])).replace('__X__', x).replace('__Y__', y)"></p>
    </div>
</x-dynamic-component>
