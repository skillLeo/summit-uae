{{--
    Design 2 home block "Our Team" (Design 2.1 brief §5.1, D24): the team grid, a switchable block after section 05
    About, OFF by default (its home block in the CMS; SUMMIT_HOME_TEAM=1 for one environment; the team stays
    on the About page unless the client approves it on the home: client question 7).
    The approved "Our Team" heading of the About page, then the three profile cards (content()->teamMembers(), the
    approved order: Ahmed, Mohamed, Sarah). Each card opens the member's profile on the About page (P06#team-…) and
    carries the proposed "Request a consultation with {first name}" link (wizard prefill). The approved "Meet Summit
    and the Team" link is section 05's, directly above: the block does not repeat it.
    Phones: the cards are a swipe rail with position markers (<x-d2.ui.carousel until="768">); see sections/team.css.
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $members = content()->teamMembers();
    $teamHeading = content()->page('P06')['content']['team']['heading'] ?? null;
@endphp
@if ($members !== [])
    <section class="section team" id="{{ $sectionId ?? 'our-team' }}" @if ($teamHeading) aria-labelledby="home-team-title" @endif>
        <div class="container">
            <div class="team__people">
                @if ($teamHeading)
                    <div class="section__head team__head">
                        <h2 id="home-team-title">{{ Bidi::isolate($teamHeading) }}</h2>
                    </div>
                @endif
                <x-d2.ui.carousel class="team__rail" :labelledby="$teamHeading ? 'home-team-title' : null" until="768" list-class="team__list"
                    :list-attributes="$teamHeading ? ['aria-labelledby' => 'home-team-title'] : []">
                    @foreach ($members as $member)
                        <li class="team__item">
                            <x-d2.team.profile :member="$member" :href="SiteUrl::target((string) $member['code'])" level="3" />
                        </li>
                    @endforeach
                </x-d2.ui.carousel>
            </div>
        </div>
    </section>
@endif
