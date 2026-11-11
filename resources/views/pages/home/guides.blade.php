@php use App\Support\SiteUrl; @endphp
{{-- 08 Legal Guides: nine guides grouped into the three approved Knowledge Centre categories. --}}
<section class="section section--ruled" id="guides" aria-labelledby="guides-title">
    <div class="container">
        <header class="section-head">
            <h2 id="guides-title">{{ $c['guides']['heading'] ?? '' }}</h2>
            <p>{{ $c['guides']['intro'] ?? '' }}</p>
        </header>
        <div class="guide-groups">
            @foreach ($guideCategories as $category)
                <section aria-labelledby="gc-{{ $category['code'] }}">
                    <h3 class="guide-group__title" id="gc-{{ $category['code'] }}">{{ $category['name'] }}</h3>
                    <ul class="guide-list">
                        @foreach ($category['guides'] ?? [] as $code)
                            @php $guide = content()->guide($code) ?? []; $gid = 'guide-'.strtolower($code); @endphp
                            <li class="guide-item">
                                <h4 id="{{ $gid }}-title">{{ $guide['home_title'] ?? '' }}</h4>
                                <p>{{ $guide['home_summary'] ?? '' }}</p>
                                <div>
                                    <a class="text-link" href="{{ SiteUrl::guide($code) }}" id="{{ $gid }}-cta"
                                        aria-labelledby="{{ $gid }}-cta {{ $gid }}-title">{{ ui('cta.read_guide') }}</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
        <footer class="section-foot">
            <a class="text-link" href="{{ SiteUrl::page('P03') }}">{{ ui('cta.visit_resources') }}</a>
        </footer>
    </div>
</section>
