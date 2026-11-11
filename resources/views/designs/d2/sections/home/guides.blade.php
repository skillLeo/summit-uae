{{--
    Design 2 home section 07: legal guides (#guides; brief §5.1 item 8; Design 2.1 D27).
    The approved heading and introduction ($c['guides']), then the nine approved guide cards (the homepage DOCX:
    "Show nine guide cards") in the DOCX order G01–G09 (the order of the three Knowledge Centre categories), each a
    TEXT card (<x-d2.guide.card>): approved category, title, summary and "Read the Guide", linking to the
    complete guide on the Knowledge Centre (SiteUrl::guide: the registered P03 anchors). No generated covers, no
    reading time (an image area appears only for a real cover, Cover::image()). Then "Visit the Legal Resources
    Centre". Design 1's ids are kept: guide-g0N-title / guide-g0N-cta on each card and gc-gc-0N (category) on
    the first card of each category.
    Layout (sections/guides.css): on phones a swipe rail (<x-d2.ui.carousel until="768">, its pager for nine
    cards; titles start level, labels rest on the bottom); two equal columns from 768 px, three from 1280 px, where
    the cards of a row share their row tracks (subgrid): categories, titles, summaries and labels line up (brief
    §7.1 rule 12).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $head = $c['guides'] ?? [];
    $groups = $guideCategories ?? content()->guideCategories();
    $cards = [];
    foreach ($groups as $group) {
        $first = true;
        foreach ((array) ($group['guides'] ?? []) as $code) {
            $guide = content()->guide((string) $code);
            if ($guide !== null) {
                // Design 1's category anchor (#gc-gc-01 …) lands on the first card of its category.
                $cards[] = [$guide, $group['name'] ?? null, $first ? 'gc-'.($group['code'] ?? '') : null];
                $first = false;
            }
        }
    }
@endphp
<section class="section guides" id="{{ $sectionId ?? 'guides' }}" aria-labelledby="guides-title">
    <div class="container">
        <div class="section__head">
            <h2 id="guides-title">{{ Bidi::isolate($head['heading'] ?? '') }}</h2>
            @if (! empty($head['intro']))
                <p>{{ Bidi::isolate($head['intro']) }}</p>
            @endif
        </div>

        <x-d2.ui.carousel class="guides__rail" labelledby="guides-title" until="768" list-class="guides__grid">
            @foreach ($cards as [$guide, $category, $anchor])
                <li class="guides__item" @if ($anchor) id="{{ $anchor }}" @endif>
                    <x-d2.guide.card :guide="$guide" :category="$category" />
                </li>
            @endforeach
        </x-d2.ui.carousel>

        <div class="guides__foot">
            <x-d2.ui.button variant="secondary" :href="SiteUrl::page('P03')">{{ ui('cta.visit_resources') }}</x-d2.ui.button>
        </div>
    </div>
</section>
