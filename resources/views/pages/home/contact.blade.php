@php
    use App\Support\SiteUrl;
    $contact = $c['contact'] ?? [];
    $p07 = content()->page('P07')['content'] ?? [];
    $details = $p07['details'] ?? [];
@endphp
{{-- 10 Start with Clear Legal Advice: direct channels + the consultation form (A01). --}}
<section class="section section--ruled" id="consultation-form" aria-labelledby="contact-title" tabindex="-1">
    <div class="container grid contact__grid">
        <div class="contact__info">
            <header class="stack stack-24">
                <h2 id="contact-title">{{ $contact['heading'] ?? '' }}</h2>
                <p class="lead">{{ $contact['intro'] ?? '' }}</p>
            </header>

            <ul class="channels">
                <li class="channel">
                    <span class="channel__label">{{ $details['telephone_label'] ?? '' }}</span>
                    <a class="channel__value" href="{{ setting('phone_href') }}"><x-icon name="phone" /><bdi dir="ltr">{{ setting('phone_display') }}</bdi></a>
                </li>
                <li class="channel">
                    <span class="channel__label">{{ $details['whatsapp_label'] ?? '' }}</span>
                    <a class="channel__value" href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer">
                        <x-icon name="whatsapp" /><span>{{ ui('cta.whatsapp') }}</span><span class="sr-only">{{ proposed('external.new_tab') }}</span>
                    </a>
                </li>
                <li class="channel">
                    <span class="channel__label">{{ $details['email_label'] ?? '' }}</span>
                    <a class="channel__value" href="{{ setting('email_href') }}"><x-icon name="mail" /><bdi dir="ltr">{{ setting('email') }}</bdi></a>
                </li>
            </ul>

            <div class="contact__facts">
                <p>{{ $contact['languages'] ?? '' }}</p>
                <p class="lang-trio" aria-hidden="true">
                    @foreach (array_keys(config('summit.locales')) as $code)
                        <span lang="{{ $code }}" dir="{{ locale_dir($code) }}">{{ ui('lang.'.$code, $code) }}</span>
                    @endforeach
                </p>
                <p>{{ $contact['hours'] ?? '' }}</p>
                @if (config('summit.features.open_now_indicator'))
                    <p><span class="status" data-open-status hidden data-open-label="{{ proposed('hours.open') }}" data-closed-label="{{ proposed('hours.closed') }}"></span></p>
                @endif
            </div>
        </div>

        <div class="contact__form">
            <x-consultation-form :idempotency-key="$idempotencyKey" :preset="request()->query('matter')" />
        </div>
    </div>
</section>
