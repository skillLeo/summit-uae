@php
    use App\Support\SiteUrl;
    // The approved H1 is set on two lines at its sentence boundary; the wording is unchanged.
    $h1 = $page['h1'] ?? '';
    $lines = preg_split('/(?<=[.!?。])\s+/u', $h1, 2) ?: [$h1];
    $steps = $c['review']['steps'] ?? [];
    $hours = content()->page('P07')['content']['details']['hours'] ?? ($c['contact']['hours'] ?? '');
@endphp
<section class="hero on-dark" aria-labelledby="hero-title" data-hero>
    <div class="container grid hero__inner">
        <div class="hero__copy">
            <h1 class="hero__title" id="hero-title">
                @foreach ($lines as $line)
                    <span class="hero__line">{{ $line }}</span>
                @endforeach
            </h1>
            <div class="hero__text">
                <p>{{ $c['hero']['lead'] ?? '' }}</p>
                <p>{{ $c['hero']['body'] ?? '' }}</p>
            </div>
            <div class="hero__actions">
                <a class="btn btn--gold btn--lg" href="{{ SiteUrl::consultation() }}" data-consult-link>{{ ui('cta.request_consultation') }}</a>
                <a class="btn btn--ghost-light btn--lg" href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer">
                    <x-icon name="whatsapp" />
                    <span>{{ ui('cta.whatsapp') }}</span>
                    <span class="sr-only">{{ proposed('external.new_tab') }}</span>
                </a>
            </div>
            <p class="hero__quiet">
                <a href="{{ setting('phone_href') }}">
                    <x-icon name="phone" />
                    <bdi dir="ltr">{{ setting('phone_display') }}</bdi>
                </a>
                <span>{{ $hours }}</span>
                @if (config('summit.features.open_now_indicator'))
                    <span class="status" data-open-status hidden
                        data-open-label="{{ proposed('hours.open') }}" data-closed-label="{{ proposed('hours.closed') }}"></span>
                @endif
            </p>
        </div>

        {{-- "The File": seven abstract slips settle once onto a gold spine indexed by the
             four approved method steps. Decorative; the steps are read in section 04. --}}
        <div class="file" aria-hidden="true" data-file>
            <span class="file__spine" data-file-spine></span>
            <ol class="file__ticks">
                @foreach ($steps as $step)
                    <li class="file__tick" data-file-tick><span class="file__label">{{ $step['title'] }}</span></li>
                @endforeach
            </ol>
            <div class="file__slips">
                @for ($i = 0; $i < 7; $i++)
                    <span class="slip" data-slip>
                        @for ($b = 0; $b < ($i === 6 ? 6 : 4); $b++)
                            <span class="slip__bar"></span>
                        @endfor
                    </span>
                @endfor
            </div>
        </div>
    </div>
</section>
