{{--
    Design 2 About page (P06; upgrade spec §7.4 "About/Team: profile cards, method, languages", §5.5 team
    profile cards, §12 Phase D). Rendered by PagePreviewController ($h1, $crumbs). Approved P06 content and
    the three approved team members (content()->teamMembers()), in the DOCX order:

      head          breadcrumb, H1, the approved lead and both hero paragraphs, the hero links "Meet Our Team"
                    (#team) and "Discuss Your Matter with Us" (P07: the home form until contact_page_released).
                    No aside (Phase 2, brief §5.3): the team menu sat there as portrait tiles, read before the
                    story; the DOCX puts it under "Our Team".
      #story        "Why Summit Was Founded" and its three paragraphs.
      #approach     "What Defines Our Approach": the six approved principles as plain columns under a hairline
                    (static text, not cards: brief §3.5 keeps cards for interactive containers and listings).
      #process      "How We Work on Your Matter": the four approved steps (their approved numbering is
                    printed as it stands, at the start of the step's own H3, in its role and colour; plain
                    columns under a hairline) and the
                    two approved links as link rows (the treatment of every related-page link).
      #team         "Our Team" + intro, the approved team menu (names → #team-ahmed, #team-mohamed, #team-sarah: a
                    compact jump menu of link rows, the DOCX's place for it), then one profile per member
                    (#team-…), in the DOCX order: <x-d2.team.profile focus="line" :languages="false"> (the 4:5
                    portrait on the shared eye line, or the initials panel while the photo is pending: D42, D43;
                    name, role and the approved focus line verbatim, the DOCX's "expertise descriptor"; the
                    proposed "Request a consultation with {first name}" → wizard prefill consultant=team-…), the
                    approved biography in one body size (D45), "Areas of Focus / Expertise", the approved
                    working-languages line as plain text (D44) and the member's approved links.
                    Phase 2 (brief §5.3): the approved focus line is shown, never replaced; the proposed focus
                    chips (short forms of that line) are not shown on P06, where they would only repeat it (the
                    home's team cards keep them). No credentials, years or memberships are added.
                    Phones with JavaScript (final pass, home-mobile; the P03 guide pattern): the card, the first
                    biography paragraph, the languages and the links show, the rest of the biography and the
                    areas open with the
                    proposed "Read the full profile" disclosure (d2AboutTeam; a link to #team-… opens it, the
                    inline script after the members does so for an address with a #fragment). Without
                    JavaScript, in print and from 768 px everything shows (pages/about.css).
      #scope        "Clear Roles in Legal Work" and #confidentiality "A Clear Engagement and Confidential
                    Information" (+ the Legal Notice link as a link row), two band panels side by side from 1280 px.
      #about-cta    "Understand Your Matter Before Making a Decision": paragraphs, consultation, WhatsApp
                    (approved per-language draft), telephone, e-mail, then the two approved links.
    Styles: resources/css/d2/pages/about.css (also the shared pp-* blocks of the FAQ and Contact pages).
    Design 2.1 (brief §3.6 rules 3 and 5): no arrows or chevrons on buttons and link rows, no decorative glyphs
    on the principles, panels and areas (plain bullets), no timeline dots. Arabic digits (the approved step
    numbers "١." …) follow the site setting at render time (App\Design\D2\Digits, brief §3.7, D11).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Support\SiteUrl;

    $c = content()->page('P06')['content'] ?? [];
    $texts = fn ($value) => array_values(array_filter((array) $value, fn ($p) => is_string($p) && trim($p) !== ''));

    $hero = $c['hero'] ?? [];
    $lead = is_string($hero['lead'] ?? null) ? trim($hero['lead']) : '';
    $heroLinks = array_values(array_filter((array) ($hero['links'] ?? []), fn ($l) => is_array($l) && trim((string) ($l['label'] ?? '')) !== ''));
    $href = fn (string $target) => in_array($target, ['team', 'story', 'approach', 'process', 'scope'], true) ? '#'.$target : SiteUrl::target($target);

    $story = $c['story'] ?? [];
    $approach = $c['approach'] ?? [];
    $principles = array_values(array_filter((array) ($approach['principles'] ?? []), fn ($p) => is_array($p) && trim((string) ($p['title'] ?? '')) !== ''));

    $process = $c['process'] ?? [];
    $steps = array_values(array_filter((array) ($process['steps'] ?? []), fn ($s) => is_array($s) && trim((string) ($s['title'] ?? '')) !== ''));
    $processLinks = array_values(array_filter((array) ($process['links'] ?? []), fn ($l) => is_array($l) && trim((string) ($l['label'] ?? '')) !== ''));
    // The approved step titles carry their own numbering ("1. We Understand …", "١. …"): split it off only
    // to draw it as the marker; the heading still reads the approved title in full.
    $splitStep = function (string $title): array {
        if (preg_match('/^\s*([0-9٠-٩۰-۹]+[.)]?)\s+(.+)$/u', $title, $m) === 1) {
            return [$m[1], $m[2]];
        }

        return [null, $title];
    };

    $team = $c['team'] ?? [];
    $teamAnchor = is_string($team['anchor'] ?? null) && $team['anchor'] !== '' ? $team['anchor'] : 'team';
    $members = content()->teamMembers();
    // The approved team menu: its own labels ("Ms Sarah Zaki" / RU «Сара Заки»), each to a member's anchor.
    $anchors = array_map(fn ($m) => (string) ($m['anchor'] ?? $m['code'] ?? ''), $members);
    $menu = array_values(array_filter(array_map(fn ($item) => [
        'label' => trim((string) ($item['label'] ?? '')),
        'target' => ltrim(trim((string) ($item['target'] ?? '')), '#'),
    ], array_filter((array) ($team['menu'] ?? []), 'is_array')), fn ($item) => $item['label'] !== '' && in_array($item['target'], $anchors, true)));
    $memberLink = fn (array $link): array => [
        'label' => (string) ($link['label'] ?? ''),
        'href' => SiteUrl::target((string) ($link['target'] ?? '')),
    ];

    $scope = $c['scope'] ?? [];
    $confidentiality = $c['confidentiality'] ?? [];
    $cta = $c['cta'] ?? [];
    $ctaLinks = array_values(array_filter((array) ($cta['links'] ?? []), fn ($l) => is_array($l) && trim((string) ($l['label'] ?? '')) !== ''));
@endphp

<x-d2.inner.head :title="$h1" :crumbs="$crumbs" :intro="$texts($hero['paragraphs'] ?? [])" class="pp-head about-head">
    @if ($lead !== '')
        <x-slot:meta><p class="pp-lead">{{ Bidi::isolate($lead) }}</p></x-slot:meta>
    @endif
    @if ($heroLinks !== [])
        <x-slot:actions>
            @foreach ($heroLinks as $i => $link)
                @php $target = (string) ($link['target'] ?? ''); @endphp
                <x-d2.ui.button :variant="$i === 0 ? 'ink' : 'secondary'" size="52" :href="$href($target)">{{ $link['label'] }}</x-d2.ui.button>
            @endforeach
        </x-slot:actions>
    @endif
</x-d2.inner.head>

@if (! empty($story['heading']))
    <section class="section about-story" id="story" aria-labelledby="story-title">
        <div class="container about-split">
            <h2 class="about-split__title" id="story-title">{{ Bidi::isolate($story['heading']) }}</h2>
            <div class="about-split__body about-story__body">
                @foreach ($texts($story['paragraphs'] ?? []) as $paragraph)
                    <p>{{ Bidi::isolate($paragraph) }}</p>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($principles !== [])
    <section class="section section--surface about-approach" id="approach" aria-labelledby="approach-title">
        <div class="container">
            <div class="section__head">
                <h2 id="approach-title">{{ Bidi::isolate($approach['heading'] ?? '') }}</h2>
            </div>
            {{-- Static text, not a listing: plain columns under a hairline, never a card (cards are for interactive
                 containers and clickable listings, brief §3.5). --}}
            <ul class="about-principles" role="list">
                @foreach ($principles as $principle)
                    <li class="about-principle">
                        <h3 class="about-principle__title">{{ Bidi::isolate((string) $principle['title']) }}</h3>
                        <p class="about-principle__text">{{ Bidi::isolate((string) ($principle['text'] ?? '')) }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif

@if ($steps !== [])
    <section class="section about-process" id="process" aria-labelledby="process-title">
        <div class="container">
            <div class="section__head">
                <h2 id="process-title">{{ Bidi::isolate($process['heading'] ?? '') }}</h2>
            </div>
            <ol class="pp-steps pp-steps--row" role="list" aria-labelledby="process-title">
                @foreach ($steps as $step)
                    @php [$number, $title] = $splitStep((string) $step['title']); @endphp
                    <li class="pp-step">
                        <div class="pp-step__card">
                            <h3 class="pp-step__title">@if ($number)<span class="pp-step__num">{{ $number }}</span> @endif<span class="pp-step__name">{{ Bidi::isolate($title) }}</span></h3>
                            <p class="pp-step__text">{{ Bidi::isolate((string) ($step['text'] ?? '')) }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            @if ($processLinks !== [])
                {{-- Links to related pages: the kit's link rows, as every related-page link on the inner pages. --}}
                <ul class="pp-links pp-links--grid about-process__links" role="list">
                    @foreach ($processLinks as $link)
                        <li>
                            <a class="link-row link-row--page" href="{{ SiteUrl::target((string) ($link['target'] ?? '')) }}">
                                <span class="link-row__body"><span class="link-row__label">{{ Bidi::isolate($link['label']) }}</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif

<section class="section section--surface about-team" id="{{ $teamAnchor }}" aria-labelledby="team-title" tabindex="-1" x-data="d2AboutTeam">
    <div class="container">
        <div class="section__head">
            <h2 id="team-title">{{ Bidi::isolate($team['heading'] ?? '') }}</h2>
            @if (! empty($team['intro']))
                <p>{{ Bidi::isolate($team['intro']) }}</p>
            @endif
        </div>

        @if ($menu !== [])
            {{-- The approved team menu (DOCX P06-L006 … L008): a compact jump menu to the three profiles below. --}}
            <nav class="about-people" aria-labelledby="team-title">
                <ul class="pp-links pp-links--grid about-people__list" role="list">
                    @foreach ($menu as $item)
                        <li>
                            <a class="link-row link-row--page about-person" href="#{{ $item['target'] }}">
                                <span class="link-row__body"><span class="link-row__label about-person__name">{{ Bidi::isolate($item['label']) }}</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="about-members">
            @foreach ($members as $member)
                @php
                    $anchor = (string) ($member['anchor'] ?? $member['code']);
                    $areas = $member['areas'] ?? [];
                    $areaItems = $texts($areas['items'] ?? []);
                    $links = array_map($memberLink, array_values(array_filter((array) ($member['links'] ?? []), fn ($l) => is_array($l) && trim((string) ($l['label'] ?? '')) !== '')));
                    $bio = $texts($member['bio'] ?? []);
                    $languages = trim((string) ($member['languages'] ?? ''));
                    $foldable = count($bio) > 1 || $areaItems !== [];
                @endphp
                <article class="about-member" id="{{ $anchor }}" tabindex="-1" data-about-member>
                    <div class="about-member__card">
                        <x-d2.team.profile :member="$member" level="3" focus="line" :languages="false" sizes="(min-width: 1280px) 360px, (min-width: 768px) 280px, 85vw" />
                    </div>
                    <div class="about-member__body">
                        <div class="about-member__bio">
                            @isset($bio[0])
                                <p>{{ Bidi::isolate($bio[0]) }}</p>
                            @endisset
                            @if ($foldable)
                                {{-- Phones with JavaScript only (pages/about.css); described by the member's name. --}}
                                <span id="{{ $anchor }}-who" hidden>{{ Bidi::isolate((string) ($member['name'] ?? '')) }}</span>
                                <x-d2.ui.button variant="secondary" size="48" icon-end="chevron-down" :full="true" class="about-member__toggle"
                                    aria-expanded="false" aria-controls="{{ $anchor }}-more" aria-describedby="{{ $anchor }}-who" data-am-toggle>
                                    <span class="about-member__toggle-open" @proposed>{{ d2p('about_page.profile_open') }}</span>
                                    <span class="about-member__toggle-close" @proposed>{{ d2p('about_page.profile_close') }}</span>
                                </x-d2.ui.button>
                                {{-- display: contents (pages/about.css): its paragraphs and the areas keep the biography's rhythm. --}}
                                <div class="about-member__more" id="{{ $anchor }}-more" data-am-more>
                                    @foreach (array_slice($bio, 1) as $paragraph)
                                        <p>{{ Bidi::isolate($paragraph) }}</p>
                                    @endforeach
                                    @if ($areaItems !== [])
                                        <div class="about-member__areas">
                                            <h4 class="about-member__heading" id="{{ $anchor }}-areas">{{ Bidi::isolate((string) ($areas['heading'] ?? '')) }}</h4>
                                            <ul class="pp-checklist" role="list" aria-labelledby="{{ $anchor }}-areas">
                                                @foreach ($areaItems as $item)
                                                    <li class="pp-checklist__item">
                                                        <span class="pp-checklist__mark" aria-hidden="true"></span>
                                                        <span>{{ Bidi::isolate($item) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        @if ($languages !== '')
                            {{-- The approved working-languages line, verbatim, after the areas (the DOCX order). --}}
                            <x-d2.team.languages :text="$languages" class="about-member__languages" />
                        @endif
                        @if ($links !== [])
                            <ul class="pp-links about-member__links" role="list">
                                @foreach ($links as $link)
                                    <li>
                                        <a class="link-row link-row--page" href="{{ $link['href'] }}">
                                            <span class="link-row__body"><span class="link-row__label">{{ Bidi::isolate($link['label']) }}</span></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        {{-- Phones with JavaScript fold every profile (pages/about.css): an address naming a member or an anchor
             inside the profile opens it before the browser's own fragment scroll. d2AboutTeam takes over. --}}
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function () {
                try {
                    var id = decodeURIComponent(window.location.hash.slice(1));
                    var target = id ? document.getElementById(id) : null;
                    var member = target ? target.closest('[data-about-member]') : null;
                    if (!member) return;
                    member.classList.add('is-open');
                    var toggle = member.querySelector('[data-am-toggle]');
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                } catch (e) {}
            })();
        </script>
    </div>
</section>

@if (! empty($scope['heading']) || ! empty($confidentiality['heading']))
    <div class="section about-terms">
        <div class="container about-terms__grid">
            @foreach ([['scope', $scope], ['confidentiality', $confidentiality]] as [$key, $block])
                @if (! empty($block['heading']))
                    <section class="pp-panel about-terms__panel" id="{{ $key }}" aria-labelledby="{{ $key }}-title">
                        <h2 class="pp-panel__title" id="{{ $key }}-title">{{ Bidi::isolate($block['heading']) }}</h2>
                        @foreach ($texts($block['paragraphs'] ?? []) as $paragraph)
                            <p>{{ Bidi::isolate($paragraph) }}</p>
                        @endforeach
                        @php $panelLinks = array_values(array_filter((array) ($block['links'] ?? []), fn ($l) => is_array($l) && trim((string) ($l['label'] ?? '')) !== '')); @endphp
                        @if ($panelLinks !== [])
                            {{-- "Legal Notice and Scope of Services": a link row, as on P02, S01–S11, P03 and P04. --}}
                            <ul class="pp-links pp-panel__links" role="list">
                                @foreach ($panelLinks as $link)
                                    <li>
                                        <a class="link-row link-row--page" href="{{ SiteUrl::target((string) ($link['target'] ?? '')) }}">
                                            <span class="link-row__body"><span class="link-row__label">{{ Bidi::isolate($link['label']) }}</span></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endif
            @endforeach
        </div>
    </div>
@endif

@if (! empty($cta['heading']))
    <section class="section pp-band" id="about-cta" aria-labelledby="about-cta-title">
        <div class="container">
            <div class="pp-cta">
                <div class="pp-cta__text">
                    <h2 class="pp-cta__title" id="about-cta-title">{{ Bidi::isolate($cta['heading']) }}</h2>
                    @foreach ($texts($cta['paragraphs'] ?? []) as $paragraph)
                        <p>{{ Bidi::isolate($paragraph) }}</p>
                    @endforeach
                </div>
                <div class="pp-cta__actions">
                    <div class="pp-cta__buttons">
                        @if (! empty($cta['consultation']['label']))
                            <x-d2.ui.button variant="ink" size="48" :full="true" :href="SiteUrl::target((string) ($cta['consultation']['target'] ?? 'P07'))">{{ $cta['consultation']['label'] }}</x-d2.ui.button>
                        @endif
                        @if (! empty($cta['whatsapp']['label']))
                            <x-d2.ui.button variant="whatsapp" size="48" :full="true" :href="setting('whatsapp_url')">{{ $cta['whatsapp']['label'] }}</x-d2.ui.button>
                        @endif
                    </div>
                    {{-- The approved telephone and e-mail labels (the number and the address) as the kit's contact channels. --}}
                    <x-d2.inner.channels class="pp-cta__channels" :items="[
                        ['kind' => 'phone', 'href' => (string) setting('phone_href'), 'text' => (string) ($cta['phone']['label'] ?? '')],
                        ['kind' => 'email', 'href' => (string) setting('email_href'), 'text' => (string) ($cta['email']['label'] ?? '')],
                    ]" />
                </div>
            </div>
            @if ($ctaLinks !== [])
                <ul class="pp-links pp-links--grid pp-band__links" role="list">
                    @foreach ($ctaLinks as $link)
                        @php $target = (string) ($link['target'] ?? ''); @endphp
                        <li>
                            <a class="link-row link-row--page" href="{{ SiteUrl::target($target) }}">
                                <span class="link-row__body"><span class="link-row__label">{{ Bidi::isolate($link['label']) }}</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
