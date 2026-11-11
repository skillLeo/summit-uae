@php
    use App\Support\SiteUrl;
    $heading = config('summit.features.navigator_heading') === 'approved'
        ? ($c['review']['questions_heading'] ?? '')
        : proposed('navigator.heading');
    $audienceStep = config('summit.features.navigator_audience_step');
    $total = $audienceStep ? 3 : 2;
    $situationIntro = ui('service.situations_intro');
    $navigatorJson = $navPayload + [
        'labels' => [
            'pick' => proposed('navigator.step_pick'),
            'file' => proposed('navigator.step_file'),
            'change' => proposed('navigator.change'),
            'copied' => proposed('navigator.link_copied'),
            'progress' => proposed('navigator.progress', ['current' => ':current', 'total' => $total]),
            'printDate' => proposed('navigator.print_date', ['date' => ':date']),
        ],
        'total' => $total,
        'locale' => app()->getLocale(),
        'dateLocale' => config('summit.locales.'.app()->getLocale().'.date'),
    ];
@endphp
{{-- 05 Matter Navigator: approved situations → a printable starting file → the form.
     Runs entirely in the browser; without JS the GET form renders the file on the server. --}}
<section class="section section--ruled" id="navigator" aria-labelledby="navigator-title">
    <div class="container grid navigator__grid" x-data="navigator" data-navigator>
        <header class="navigator__head">
            <h2 id="navigator-title">{{ $heading }}</h2>
            <p>{{ proposed('navigator.intro') }}</p>
        </header>

        {{-- Mobile (JS): the flow opens in a bottom sheet. --}}
        <div class="navigator__launch">
            <template x-if="current">
                <p class="file-card__kicker"><span>{{ proposed('navigator.step_file') }}</span><strong x-text="current.service.title"></strong></p>
            </template>
            <button class="btn btn--block" type="button" x-on:click="openSheet" aria-haspopup="dialog" aria-controls="navigator-app">
                <x-icon name="services" />
                <span x-text="current ? labels.change : labels.pick">{{ proposed('navigator.step_pick') }}</span>
            </button>
        </div>
        <div class="navigator__scrim" x-bind:class="{ 'is-open': sheetOpen }" x-on:click="closeSheet"></div>

        <div class="navigator__app" id="navigator-app" data-step="pick" x-bind:data-step="step" x-bind:class="{ 'is-open': sheetOpen }"
            x-bind:role="isSheet ? 'dialog' : null" x-bind:aria-modal="isSheet ? 'true' : null" aria-labelledby="navigator-title"
            x-on:keydown.escape="closeSheet" x-ref="app">
            <div class="navigator__sheet-head sheet__header" data-sheet-drag x-ref="sheetHead">
                <span class="sheet__handle" aria-hidden="true"></span>
                <div>
                    <p class="sheet__title" x-text="step === 'file' ? labels.file : labels.pick">{{ proposed('navigator.step_pick') }}</p>
                    <p class="sheet__meta ltr" x-text="progressLabel"></p>
                </div>
                <button class="icon-btn" type="button" x-on:click="closeSheet">
                    <x-icon name="close" />
                    <span class="sr-only">{{ proposed('navigator.close') }}</span>
                </button>
            </div>

            {{-- Step 1–2: choose an approved situation --}}
            <div class="navigator__pane navigator__pane--pick">
                <form method="get" action="{{ SiteUrl::home() }}#navigator" x-on:submit.prevent="noop" novalidate>
                <fieldset class="picker">
                    @if ($audienceStep)
                        <div class="cluster" role="group" aria-label="{{ proposed('navigator.step_audience') }}">
                            <button class="who-segment" type="button" data-audience="" x-on:click="setAudience" x-bind:aria-pressed="audience === ''">{{ proposed('navigator.audience_all') }}</button>
                            @foreach ($c['who_we_help']['items'] ?? [] as $item)
                                <button class="who-segment" type="button" data-audience="{{ $item['key'] }}" x-on:click="setAudience" x-bind:aria-pressed="audience === '{{ $item['key'] }}'">{{ $item['title'] }}</button>
                            @endforeach
                        </div>
                    @endif

                    <legend class="picker__legend" id="navigator-pick-label">
                        <span class="picker__step ltr">{{ $audienceStep ? '2' : '1' }}</span>
                        {{ proposed('navigator.step_pick') }}
                    </legend>

                    <div class="field">
                        <label class="sr-only" for="navigator-filter">{{ proposed('navigator.filter_label') }}</label>
                        <div class="picker__filter">
                            <x-icon name="filter" />
                            <input class="input" id="navigator-filter" type="search" autocomplete="off" enterkeyhint="search"
                                placeholder="{{ proposed('navigator.filter_placeholder') }}" x-model="query" x-on:input="applyFilter">
                        </div>
                    </div>

                    <div class="picker__list" x-ref="list">
                        @foreach ($navServices as $i => $s)
                            <fieldset class="picker-group" data-group="{{ $s['code'] }}">
                                <legend class="picker-group__name">
                                    <span class="index-num ltr" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</span>
                                    <span>{{ $s['title'] }}</span>
                                </legend>
                                @foreach ($s['situations'] as $j => $situation)
                                    @php $ref = $s['code'].'-'.($j + 1); @endphp
                                    <label class="picker-option" data-option data-ref="{{ $ref }}">
                                        <input class="check__input" type="radio" name="navigator" value="{{ $ref }}"
                                            @checked($selected && $selected['service'] === $s['code'] && $selected['index'] === $j + 1)
                                            x-on:change="choose">
                                        <span class="check__text">{{ $situation }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endforeach
                        <div class="picker__empty" hidden x-bind:hidden="!noResults">
                            <p>{{ proposed('navigator.no_results') }}</p>
                            <button class="text-link" type="button" x-on:click="clearFilter">{{ proposed('navigator.clear_filter') }}</button>
                        </div>
                    </div>

                    <button class="btn picker__submit" type="submit" formnovalidate>{{ proposed('navigator.show_file') }}</button>
                </fieldset>
                </form>
            </div>

            {{-- Step 3: the starting file --}}
            <div class="navigator__pane navigator__pane--file" aria-live="polite">
                {{-- Server-rendered file (no JS, or ?navigator=S05-2). Alpine replaces it on start. --}}
                <div x-ref="ssr">
                    @if ($selectedService)
                        @include('pages.home.navigator-file', ['s' => $selectedService, 'situation' => $selectedService['situations'][$selected['index'] - 1], 'situationIntro' => $situationIntro])
                    @else
                        <div class="file-card">
                            <div class="file-card__empty"><p>{{ proposed('navigator.empty') }}</p></div>
                        </div>
                    @endif
                </div>

                <template x-if="ready && !current">
                    <div class="file-card">
                        <div class="file-card__empty"><p>{{ proposed('navigator.empty') }}</p></div>
                    </div>
                </template>

                <template x-if="ready && current">
                    <article class="file-card" aria-labelledby="navigator-file-title" x-ref="card">
                        <div class="file-card__print-head">
                            <x-brand name="lockup" />
                            <span x-text="printDate"></span>
                        </div>
                        <header class="file-card__head">
                            <p class="file-card__kicker">
                                <span class="index-num ltr" x-text="current.service.index"></span>
                                <span>{{ proposed('navigator.step_file') }}</span>
                            </p>
                            <h3 class="file-card__title" id="navigator-file-title" tabindex="-1" x-text="current.service.title"></h3>
                            <p class="file-card__situation"><span>{{ $situationIntro }}</span> <span x-text="current.situation"></span></p>
                        </header>

                        <section class="file-card__section" aria-labelledby="navigator-docs-title">
                            <h4 id="navigator-docs-title" x-text="current.service.headings.documents"></h4>
                            <ul class="doc-list">
                                <template x-for="(doc, d) in current.service.documents" x-bind:key="current.ref + d">
                                    <li class="doc-row">
                                        <label class="check">
                                            <input class="check__input" type="checkbox" x-bind:checked="isTicked(d)" x-on:change="tick" x-bind:data-doc="d">
                                            <span class="check__text" x-text="doc"></span>
                                        </label>
                                    </li>
                                </template>
                            </ul>
                            <p class="file-card__note" x-text="current.service.documents_note"></p>
                            <div class="file-card__saved">
                                <span>{{ proposed('navigator.saved_note') }}</span>
                                <button type="button" x-on:click="clearTicks">{{ proposed('navigator.clear_ticks') }}</button>
                            </div>
                        </section>

                        <div class="file-card__cols">
                            <section class="file-card__section">
                                <h4 x-text="current.service.headings.first_steps"></h4>
                                <ul class="bullet-list">
                                    <template x-for="(item, k) in current.service.first_steps" x-bind:key="'f' + k">
                                        <li x-text="item"></li>
                                    </template>
                                </ul>
                            </section>
                            <section class="file-card__section">
                                <h4 x-text="current.service.headings.mistakes"></h4>
                                <ul class="bullet-list bullet-list--mistakes">
                                    <template x-for="(item, k) in current.service.mistakes" x-bind:key="'m' + k">
                                        <li x-text="item"></li>
                                    </template>
                                </ul>
                            </section>
                        </div>

                        <div class="file-card__links">
                            <template x-for="guide in current.service.guides" x-bind:key="guide.code">
                                <a class="text-link" x-bind:href="guide.url" x-text="guide.label"></a>
                            </template>
                            <a class="text-link" x-bind:href="current.service.url">{{ ui('cta.explore_service') }}</a>
                        </div>

                        <div class="file-card__actions">
                            <button class="btn btn--gold" type="button" x-on:click="continueToForm">{{ ui('cta.request_consultation') }}</button>
                            <button class="btn btn--ghost" type="button" x-on:click="print"><x-icon name="print" /><span>{{ proposed('navigator.print') }}</span></button>
                            <button class="btn btn--ghost" type="button" x-on:click="copyLink"><x-icon name="link" /><span>{{ proposed('navigator.copy_link') }}</span></button>
                        </div>

                        <p class="file-card__print-foot">{{ ui('footer.disclaimer') }}</p>
                    </article>
                </template>
            </div>

            <div class="navigator__sheet-foot">
                <template x-if="step === 'file'">
                    <button class="btn btn--ghost" type="button" x-on:click="backToPick">{{ proposed('navigator.change') }}</button>
                </template>
                <template x-if="step === 'file' && current">
                    <button class="btn btn--gold" type="button" x-on:click="continueToForm">{{ ui('cta.request_consultation') }}</button>
                </template>
            </div>
        </div>

        <span class="sr-only" aria-live="polite" x-text="announcement"></span>
        <script type="application/json" data-navigator-json>{!! json_encode($navigatorJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) !!}</script>
    </div>
</section>
