@php use App\Support\SiteUrl; @endphp
{{-- 02 Our Legal Services in the UAE: an 11-row index (01–11 is a true index). --}}
<section class="section" id="services" aria-labelledby="services-title">
    <div class="container">
        <header class="section-head">
            <h2 id="services-title">{{ $c['services']['heading'] ?? '' }}</h2>
            <p>{{ $c['services']['intro'] ?? '' }}</p>
        </header>

        <ol class="svc-list">
            @foreach ($services as $i => $service)
                @php $n = sprintf('%02d', $i + 1); $id = 'svc-'.strtolower($service['code']); @endphp
                <li class="svc-row" data-svc-row>
                    <span class="svc-row__index index-num ltr" aria-hidden="true">{{ $n }}</span>
                    <h3 class="svc-row__title" id="{{ $id }}-title">
                        <span class="svc-row__static">{{ $service['title'] }}</span>
                        <button class="svc-row__toggle" type="button" aria-expanded="false" aria-controls="{{ $id }}-body" data-svc-toggle>
                            <span>{{ $service['title'] }}</span>
                            <x-icon name="chevron-down" />
                        </button>
                    </h3>
                    <div class="svc-row__body" id="{{ $id }}-body">
                        <p class="svc-row__summary">{{ $service['home_summary'] ?? '' }}</p>
                        <a class="text-link svc-row__cta" href="{{ SiteUrl::service($service['code']) }}"
                            id="{{ $id }}-cta" aria-labelledby="{{ $id }}-cta {{ $id }}-title">{{ ui('cta.explore_service') }}</a>
                    </div>
                </li>
            @endforeach
        </ol>

        <footer class="section-foot">
            <p>{{ $c['services']['closing'] ?? '' }}</p>
            <a class="text-link" href="{{ SiteUrl::page('P02') }}">{{ ui('cta.view_all_practice_areas') }}</a>
        </footer>
    </div>
</section>
