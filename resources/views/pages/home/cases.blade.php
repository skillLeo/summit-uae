@php use App\Support\SiteUrl; @endphp
{{-- 07 Case Studies: two text previews linked to their anchors (A03, A04). No outcome is asserted. --}}
<section class="section section--ruled" id="case-studies" aria-labelledby="cases-title">
    <div class="container">
        <header class="section-head">
            <h2 id="cases-title">{{ $c['cases']['heading'] ?? '' }}</h2>
            <p>{{ $c['cases']['intro'] ?? '' }}</p>
        </header>
        <div class="cases">
            @foreach (['case-01', 'case-04'] as $code)
                @php $case = content()->caseStudy($code) ?? []; $id = 'preview-'.$code; @endphp
                <article class="case-preview" aria-labelledby="{{ $id }}">
                    @if (! empty($case['matter']))
                        {{-- "Matter:" line reused from 04_Case_Studies (approved). --}}
                        <p class="case-preview__matter">{{ $case['matter'] }}</p>
                    @endif
                    <h3 id="{{ $id }}">{{ $case['home_title'] ?? '' }}</h3>
                    <p>{{ $case['home_summary'] ?? '' }}</p>
                    <div><a class="text-link" href="{{ SiteUrl::caseStudy($code) }}">{{ $case['home_link_label'] ?? '' }}</a></div>
                </article>
            @endforeach
        </div>
        <footer class="section-foot">
            <a class="text-link" href="{{ SiteUrl::page('P04') }}">{{ ui('cta.view_all_case_studies') }}</a>
        </footer>
    </div>
</section>
