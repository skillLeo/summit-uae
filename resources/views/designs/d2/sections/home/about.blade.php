{{--
    Design 2 home section 05 "About Summit Legal Consultancy" (#about; Design 2.1 brief §5.1 item 6, D24, D45).
    The approved heading, its three paragraphs ($c['about']: the founding principle, the entity, the advocacy and
    coordination scope) and the approved link "Meet Summit and the Team" to the About page (P06). No counts, years,
    credentials or memberships (DOCX note 0134).
    Layout (sections/about.css): the H2 on the start side, the three paragraphs in ONE body size and colour on the end
    side with the link under them (D45: the first paragraph is no longer set larger than the others), the P06 story
    pattern ("Why Summit Was Founded"). The team grid is a separate block (sections/home/team.blade.php), off by
    default: the team stays on About unless the client approves it on the home (client question 7).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $about = $c['about'] ?? [];
    $paras = array_values(array_filter((array) ($about['paragraphs'] ?? []), fn ($p) => is_string($p) && $p !== ''));
@endphp
<section class="section about" id="about" aria-labelledby="about-title">
    <div class="container about__grid">
        <div class="about__head">
            <h2 id="about-title">{{ Bidi::isolate($about['heading'] ?? '') }}</h2>
        </div>
        <div class="about__body">
            @foreach ($paras as $paragraph)
                <p>{{ Bidi::isolate($paragraph) }}</p>
            @endforeach
            <div class="about__actions">
                <x-d2.ui.button variant="secondary" :href="SiteUrl::page('P06')">{{ ui('cta.meet_team') }}</x-d2.ui.button>
            </div>
        </div>
    </div>
</section>
