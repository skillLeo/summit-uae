@extends('layouts.site')
@php
    use App\Support\SiteUrl;
    $t = fn (string $k) => __('styleguide.'.$k, [], 'en');
    $c = $p01['content'] ?? [];
    $services = content()->services();
    $guide = content()->guide('G04') ?? [];
    $colours = [
        ['ink', '#111111', '18.9:1 '.$t('on_white')], ['ink-2', '#555555', '7.5:1 '.$t('on_white')],
        ['black', '#000000', '21:1 / '.$t('on_white')], ['white', '#FFFFFF', '21:1 '.$t('on_black')],
        ['gold', '#C9A96B', '9.37:1 '.$t('on_black')], ['bronze', '#8A6A2E', '5.02:1 '.$t('on_white')],
        ['bronze-strong', '#7A5C24', '6.21:1 '.$t('on_white')], ['on-dark-2', '#A3A3A3', '8.3:1 '.$t('on_black')],
        ['rule', '#E4E4E1', $t('decorative')], ['rule-strong', '#8C8C88', '3.4:1 (1.4.11)'],
        ['field', '#F4F4F2', $t('decorative')], ['error', '#B42318', '6.6:1 '.$t('on_white')], ['success', '#1E6B47', '6.5:1 '.$t('on_white')],
    ];
    $icons = collect(glob(resource_path('icons/*.svg')) ?: [])->map(fn ($f) => basename($f, '.svg'))->all();
    $annot = 'lang=en dir=ltr';
@endphp

@section('content')
    <div class="container sg">
        <header class="page-head">
            <p class="sg-note" lang="en" dir="ltr">{{ $t('title') }}</p>
            <h1>{{ $p01['h1'] ?? '' }}</h1>
            <p lang="en" dir="ltr">{{ $t('intro') }}</p>
        </header>

        {{-- Colour --}}
        <section class="sg-section" aria-labelledby="sg-colour">
            <h2 id="sg-colour" lang="en" dir="ltr">{{ $t('colour') }}</h2>
            <ul class="sg-swatches">
                @foreach ($colours as [$name, $hex, $note])
                    <li class="sg-swatch" lang="en" dir="ltr">
                        <span class="sg-swatch__chip sg-chip--{{ $name }}"></span>
                        <code class="sg-code">--{{ $name }}</code>
                        <span class="sg-code">{{ $hex }}</span>
                        <span class="sg-note">{{ $note }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Type --}}
        <section class="sg-section" aria-labelledby="sg-type">
            <h2 id="sg-type" lang="en" dir="ltr">{{ $t('type') }}</h2>
            <div class="sg-type">
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">H1 · serif 400 · clamp(2.5–4.5rem)</code><p class="h1">{{ $p01['h1'] ?? '' }}</p></div>
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">H2 · serif 400 · clamp(2–3rem)</code><p class="h2">{{ $c['services']['heading'] ?? '' }}</p></div>
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">H3 · serif 400 · 1.5rem</code><p class="h3">{{ $services[4]['title'] ?? '' }}</p></div>
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">H4 · serif 400 · 1.25rem</code><p class="h4">{{ $guide['home_title'] ?? '' }}</p></div>
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">Lead · sans 400 · 1.25rem</code><p class="lead measure">{{ $c['hero']['lead'] ?? '' }}</p></div>
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">Body · sans 400 · 1.125rem</code><p class="measure">{{ $c['review']['paragraphs'][0] ?? '' }}</p></div>
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">Small · sans 400 · 0.9375rem</code><p class="small measure">{{ $guide['home_summary'] ?? '' }}</p></div>
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">UI · sans 500 · 0.875rem</code><p class="ui">{{ ui('nav.practice_areas') }} · {{ ui('nav.resources') }} · {{ ui('nav.faq') }}</p></div>
                <div class="sg-type__row"><code class="sg-code" lang="en" dir="ltr">Tabular figures · LTR island</code><p><bdi class="tabular" dir="ltr">{{ setting('phone_display') }}</bdi> · <bdi dir="ltr">{{ setting('email') }}</bdi></p></div>
            </div>
        </section>

        {{-- Space --}}
        <section class="sg-section" aria-labelledby="sg-space">
            <h2 id="sg-space" lang="en" dir="ltr">{{ $t('space') }}</h2>
            <div class="sg-space" lang="en" dir="ltr">
                @foreach ([4, 8, 12, 16, 24, 32, 48, 64, 96, 128, 160] as $step)
                    <div class="cluster"><code class="sg-code">--s-{{ $step }}</code><span class="sg-space__bar sg-w-{{ $step }}"></span></div>
                @endforeach
            </div>
        </section>

        {{-- Grid --}}
        <section class="sg-section" aria-labelledby="sg-grid">
            <h2 id="sg-grid" lang="en" dir="ltr">{{ $t('grid') }}</h2>
            <div class="grid sg-grid-demo" aria-hidden="true">
                @for ($i = 0; $i < 12; $i++)
                    <span class="d-none {{ $i < 4 ? 'd-block' : ($i < 8 ? 'd-sm-block' : 'd-lg-block') }}"></span>
                @endfor
            </div>
        </section>

        {{-- Shape, elevation, motion --}}
        <section class="sg-section" aria-labelledby="sg-shape">
            <h2 id="sg-shape" lang="en" dir="ltr">{{ $t('shape') }}</h2>
            <ul class="list-plain stack stack-8 sg-code" lang="en" dir="ltr">
                <li>--radius-0: 0 · --radius-control: 2px · --radius-sheet: 12px · --radius-pill</li>
                <li>--shadow-sticky: 0 1px 0 var(--rule) · --shadow-sheet: 0 24px 48px -12px rgb(0 0 0 / 28%)</li>
                <li>--dur-1 140ms · --dur-2 240ms · --dur-3 480ms · --dur-4 780ms</li>
                <li>--ease-out (.22,1,.36,1) · --ease-in-out (.65,0,.35,1) · --ease-sheet (.32,.72,0,1)</li>
            </ul>
        </section>

        {{-- Icons --}}
        <section class="sg-section" aria-labelledby="sg-icons">
            <h2 id="sg-icons" lang="en" dir="ltr">{{ $t('icons') }}</h2>
            <ul class="sg-icons" lang="en" dir="ltr">
                @foreach ($icons as $icon)
                    <li><x-icon :name="$icon" /><code class="sg-code">{{ $icon }}</code></li>
                @endforeach
            </ul>
        </section>

        {{-- Buttons --}}
        <section class="sg-section" aria-labelledby="sg-buttons">
            <h2 id="sg-buttons" lang="en" dir="ltr">{{ $t('buttons') }}</h2>
            <p class="sg-note" lang="en" dir="ltr">{{ $t('states') }}</p>
            <div class="sg-demo on-dark">
                <a class="btn btn--gold btn--lg" href="{{ SiteUrl::consultation() }}">{{ ui('cta.request_consultation') }}</a>
                <span class="btn btn--gold btn--lg is-hover">{{ ui('cta.request_consultation') }}</span>
                <span class="btn btn--gold btn--lg is-focus">{{ ui('cta.request_consultation') }}</span>
                <span class="btn btn--ghost-light btn--lg"><x-icon name="whatsapp" /><span>{{ ui('cta.whatsapp') }}</span></span>
            </div>
            <div class="sg-demo">
                <span class="btn btn--lg">{{ ui('form.submit') }}</span>
                <span class="btn btn--lg is-hover">{{ ui('form.submit') }}</span>
                <span class="btn btn--lg is-focus">{{ ui('form.submit') }}</span>
                <button class="btn btn--lg" type="button" disabled>{{ ui('form.submit') }}</button>
                <span class="btn btn--lg is-busy" aria-busy="true"><x-icon name="spinner" class="spin" /><span>{{ ui('form.state.busy') }}</span></span>
                <span class="btn btn--ghost">{{ ui('form.state.new_enquiry') }}</span>
            </div>
        </section>

        {{-- Links --}}
        <section class="sg-section" aria-labelledby="sg-links">
            <h2 id="sg-links" lang="en" dir="ltr">{{ $t('links') }}</h2>
            <div class="sg-demo">
                <a class="text-link" href="{{ SiteUrl::page('P02') }}">{{ ui('cta.view_all_practice_areas') }}</a>
                <a href="{{ SiteUrl::page('L01') }}">{{ ui('form.consent.link') }}</a>
                @php $x = content()->specialistSites()[0] ?? null; @endphp
                @if ($x)
                    <a href="{{ $x['url'] }}" target="_blank" rel="noopener noreferrer">{{ $x['label'] }} <bdi dir="ltr">{{ $x['domain'] }}</bdi><x-icon name="external" class="ext-icon" /><span class="sr-only">{{ proposed('external.new_tab') }}</span></a>
                @endif
            </div>
        </section>

        {{-- Form fields --}}
        <section class="sg-section" aria-labelledby="sg-forms">
            <h2 id="sg-forms" lang="en" dir="ltr">{{ $t('forms') }}</h2>
            <p class="sg-note" lang="en" dir="ltr">{{ $t('field_states') }}</p>
            <div class="form__row">
                <div class="field">
                    <label class="field__label" for="sg-name">{{ ui('form.name.label') }} <span class="field__req" aria-hidden="true">*</span></label>
                    <p class="field__help" id="sg-name-help">{{ ui('form.name.help') }}</p>
                    <input class="input" id="sg-name" type="text" aria-describedby="sg-name-help">
                </div>
                <div class="field">
                    <label class="field__label" for="sg-phone">{{ ui('form.phone.label') }}</label>
                    <p class="field__help" id="sg-phone-help">{{ ui('form.phone.help') }}</p>
                    <input class="input input--ltr" id="sg-phone" type="tel" dir="ltr" value="+971 52 466 6191" aria-describedby="sg-phone-help">
                </div>
                <div class="field">
                    <label class="field__label" for="sg-email">{{ ui('form.email.label') }}</label>
                    <input class="input input--ltr" id="sg-email" type="email" dir="ltr" value="name@" aria-invalid="true" aria-describedby="sg-email-error">
                    <p class="field__error" id="sg-email-error"><x-icon name="alert" /><span>{{ ui('form.email.error') }}</span></p>
                </div>
                <div class="field">
                    <label class="field__label" for="sg-disabled">{{ ui('form.deadline_details.label') }}</label>
                    <input class="input" id="sg-disabled" type="text" disabled>
                </div>
                <div class="field">
                    <label class="field__label" for="sg-matter">{{ ui('form.matter_type.label') }}</label>
                    <div class="select-wrap">
                        <select class="select" id="sg-matter">
                            <option>{{ ui('form.matter_type.placeholder') }}</option>
                            @foreach (array_keys(config('summit.services')) as $code)
                                <option>{{ ui('form.matter_type.option.'.$code) }}</option>
                            @endforeach
                        </select>
                        <x-icon name="chevron-down" />
                    </div>
                </div>
                <div class="field">
                    <label class="field__label" for="sg-date">{{ ui('form.deadline_date.label') }}</label>
                    <input class="input" id="sg-date" type="date">
                </div>
            </div>
            <div class="field">
                <label class="field__label" for="sg-summary">{{ ui('form.summary.label') }}</label>
                <textarea class="textarea" id="sg-summary" rows="3"></textarea>
            </div>
            <div class="cluster">
                <label class="check"><input class="check__input" type="checkbox" checked><span class="check__text">{{ content()->service('S05')['documents'][0] ?? '' }}</span></label>
                <label class="check"><input class="check__input" type="radio" name="sg-radio" checked><span class="check__text">{{ content()->service('S05')['situations'][0] ?? '' }}</span></label>
            </div>
        </section>

        {{-- Delivery states --}}
        <section class="sg-section" aria-labelledby="sg-notices">
            <h2 id="sg-notices" lang="en" dir="ltr">{{ $t('notices') }}</h2>
            <div class="notice notice--error"><x-icon name="alert" /><div class="notice__body"><p class="notice__title">{{ ui('form.state.summary') }}</p><ul><li><a href="#sg-name">{{ ui('form.name.error') }}</a></li><li><a href="#sg-phone">{{ ui('form.contact_group.error') }}</a></li></ul></div></div>
            <div class="notice notice--success"><x-icon name="check-circle" /><div class="notice__body"><p>{{ ui('form.state.success') }}</p><p>{{ proposed('form.reference') }} <bdi class="notice__ref" dir="ltr">SLC-261109-7Q2KMD</bdi></p></div></div>
            <div class="notice notice--error"><x-icon name="alert" /><div class="notice__body"><p>{{ ui('form.state.failure') }}</p></div></div>
            <div class="notice notice--warning"><x-icon name="info" /><div class="notice__body"><p>{{ ui('form.state.uncertain') }}</p></div></div>
            <div class="notice notice--warning"><x-icon name="clock" /><div class="notice__body"><p>{{ ui('form.state.rate_limited') }}</p></div></div>
        </section>

        {{-- Disclosure --}}
        <section class="sg-section" aria-labelledby="sg-disclosure">
            <h2 id="sg-disclosure" lang="en" dir="ltr">{{ $t('disclosure') }}</h2>
            <div class="disclosure-list">
                @foreach (array_slice($c['faq']['items'] ?? [], 0, 2) as $item)
                    <details class="disclosure" @if ($loop->first) open @endif>
                        <summary><h3 class="disclosure__q">{{ $item['question'] }}</h3><x-icon name="plus" /></summary>
                        <div class="disclosure__a"><p>{{ $item['answer'] }}</p></div>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- Index row --}}
        <section class="sg-section" aria-labelledby="sg-index">
            <h2 id="sg-index" lang="en" dir="ltr">{{ $t('index') }}</h2>
            <ol class="svc-list">
                @foreach (array_slice($services, 0, 2) as $i => $s)
                    <li class="svc-row">
                        <span class="svc-row__index index-num ltr" aria-hidden="true">{{ sprintf('%02d', $i + 1) }}</span>
                        <h3 class="svc-row__title"><span class="svc-row__static">{{ $s['title'] }}</span></h3>
                        <div class="svc-row__body"><p class="svc-row__summary">{{ $s['home_summary'] ?? '' }}</p><a class="text-link svc-row__cta" href="{{ SiteUrl::service($s['code']) }}">{{ ui('cta.explore_service') }}</a></div>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Spine --}}
        <section class="sg-section" aria-labelledby="sg-spine">
            <h2 id="sg-spine" lang="en" dir="ltr">{{ $t('spine') }}</h2>
            <div class="method__list">
                <span class="method__rail" aria-hidden="true"></span>
                <ol class="method__steps">
                    @foreach ($c['review']['steps'] ?? [] as $i => $step)
                        <li class="method-step"><span class="method-step__marker" aria-hidden="true"><span class="ltr">{{ $i + 1 }}</span></span><h3 class="h4">{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Slips --}}
        <section class="sg-section" aria-labelledby="sg-slips">
            <h2 id="sg-slips" lang="en" dir="ltr">{{ $t('slips') }}</h2>
            <div class="sg-demo on-dark">
                <div class="hero__inner grid sg-full">
                    <div class="file" aria-hidden="true">
                        <span class="file__spine"></span>
                        <ol class="file__ticks">@foreach ($c['review']['steps'] ?? [] as $step)<li class="file__tick"><span class="file__label">{{ $step['title'] }}</span></li>@endforeach</ol>
                        <div class="file__slips">@for ($i = 0; $i < 7; $i++)<span class="slip">@for ($b = 0; $b < 4; $b++)<span class="slip__bar"></span>@endfor</span>@endfor</div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Breadcrumbs + language --}}
        <section class="sg-section" aria-labelledby="sg-breadcrumbs">
            <h2 id="sg-breadcrumbs" lang="en" dir="ltr">{{ $t('breadcrumbs') }} · {{ $t('language') }}</h2>
            @php $s5 = content()->service('S05') ?? []; @endphp
            <nav class="breadcrumbs" aria-label="{{ proposed('nav.breadcrumb') }}">
                <ol>
                    <li><a href="{{ SiteUrl::home() }}">{{ $s5['breadcrumb'][0] ?? '' }}</a></li>
                    <li><a href="{{ SiteUrl::page('P02') }}">{{ $s5['breadcrumb'][1] ?? '' }}</a></li>
                    <li><span aria-current="page">{{ $s5['breadcrumb'][2] ?? '' }}</span></li>
                </ol>
            </nav>
            <ul class="lang-menu__list sg-static">
                @foreach (array_keys(config('summit.locales')) as $code)
                    <li><a class="lang-option" href="{{ SiteUrl::abs(SiteUrl::swapLocale(request()->getPathInfo(), $code)) }}" lang="{{ $code }}" dir="{{ locale_dir($code) }}" @if ($code === app()->getLocale()) aria-current="true" @endif><span>{{ ui('lang.'.$code, $code) }}</span>@if ($code === app()->getLocale())<x-icon name="check" />@endif</a></li>
                @endforeach
            </ul>
        </section>

        {{-- Portraits --}}
        <section class="sg-section" aria-labelledby="sg-portraits">
            <h2 id="sg-portraits" lang="en" dir="ltr">{{ $t('portraits') }}</h2>
            <div class="sg-portraits">
                @foreach (content()->teamMembers() as $member)
                    @php
                        // Real widths only: the sources cannot supply 1200w at 4:5 (952w / 852w max).
                        $widths = collect(glob(public_path('images/team/'.($member['portrait'] ?? '_none').'-*.jpg')) ?: [])
                            ->map(fn ($f) => (int) preg_replace('/\D/', '', basename($f, '.jpg')))->sort()->values();
                        $srcset = fn (string $ext) => $widths->map(fn ($w) => "/images/team/{$member['portrait']}-{$w}.{$ext} {$w}w")->implode(', ');
                    @endphp
                    <figure class="stack stack-8">
                        @if ($widths->isNotEmpty())
                            <div class="portrait">
                                <picture>
                                    <source type="image/avif" srcset="{{ $srcset('avif') }}" sizes="(min-width: 1024px) 20rem, 80vw">
                                    <source type="image/webp" srcset="{{ $srcset('webp') }}" sizes="(min-width: 1024px) 20rem, 80vw">
                                    <img src="/images/team/{{ $member['portrait'] }}-800.jpg" alt="{{ $member['alt'] ?? $member['name'] }}" width="800" height="1000" loading="lazy" decoding="async">
                                </picture>
                            </div>
                        @else
                            <div class="portrait portrait--pending"><x-brand name="emblem" /><span class="portrait__caption">{{ proposed('team.portrait_pending') }}</span></div>
                        @endif
                        <figcaption><strong>{{ $member['name'] }}</strong><br><span class="small">{{ $member['role'] ?? '' }}</span></figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    </div>
@endsection
