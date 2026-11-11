@php use App\Support\SiteUrl; $about = $c['about'] ?? []; $paras = $about['paragraphs'] ?? []; @endphp
{{-- 06 About Summit Legal Consultancy: founding principle, entity, advocacy scope (stated plainly). --}}
<section class="section section--ruled" id="about" aria-labelledby="about-title">
    <div class="container grid about__grid">
        <div class="about__text">
            <h2 id="about-title">{{ $about['heading'] ?? '' }}</h2>
            @isset($paras[0])
                <p class="about__principle">{{ $paras[0] }}</p>
            @endisset
            @isset($paras[1])
                <p class="about__body">{{ $paras[1] }}</p>
            @endisset
            @isset($paras[2])
                <p class="about__scope">{{ $paras[2] }}</p>
            @endisset
            <div>
                <a class="text-link" href="{{ SiteUrl::page('P06') }}">{{ ui('cta.meet_team') }}</a>
            </div>
        </div>
        <div class="about__emblem" aria-hidden="true">
            <x-brand name="emblem" />
        </div>
    </div>
</section>
