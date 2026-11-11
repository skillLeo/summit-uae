{{--
    Design 2 home section 06: case studies (#case-studies; Design 2.1 brief §5.1 item 7, D25).
    The homepage DOCX: "Use two text based case previews. Link to the matching case anchors. These previews describe
    the issue and method without asserting a disputed outcome." So: the approved heading and introduction
    ($c['cases']), then the two approved text previews in their DOCX order (<x-d2.case.preview>: the case studies that
    carry the homepage preview copy, home_title / home_summary / home_link_label, imported from the homepage DOCX:
    "A Signed Blank Cheque and Earlier Judgments" with "Read the Cheque Case Study" to P04#case-01, and "A USDT Sale
    and a Complaint Involving a Third Party" with "Read the Cryptocurrency Case Study" to P04#case-04), then the
    approved "View All Case Studies". No numbers, icons, chips, timelines or repeated notices (D25, D46).
    Each preview keeps Design 1's home anchor (#preview-case-01, #preview-case-04).
    Layout (sections/cases.css): two equal columns from 768 px whose titles, paragraphs and labels share the row's
    tracks (subgrid, lint rule 12), stacked below.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $cases = $c['cases'] ?? [];
    $intro = trim((string) ($cases['intro'] ?? ''));
    $previews = array_values(array_filter(content()->all('case_studies'), fn ($case) => filled($case['home_title'] ?? null)));
@endphp
<section class="section cases" id="{{ $sectionId ?? 'case-studies' }}" aria-labelledby="cases-title">
    <div class="container">
        <div class="section__head">
            <h2 id="cases-title">{{ Bidi::isolate($cases['heading'] ?? '') }}</h2>
            @if ($intro !== '')
                <p>{{ Bidi::isolate($intro) }}</p>
            @endif
        </div>

        @if ($previews !== [])
            <ul class="cases__grid" role="list" aria-labelledby="cases-title">
                @foreach ($previews as $case)
                    <li class="cases__item">
                        <x-d2.case.preview :case="$case" />
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="cases__foot">
            <x-d2.ui.button variant="secondary" :href="SiteUrl::page('P04')">{{ ui('cta.view_all_case_studies') }}</x-d2.ui.button>
        </div>
    </div>
</section>
