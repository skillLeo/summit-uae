@props([
    'member',                 // content()->teamMembers() record
    'href' => null,           // whole-card link (home: the member's profile on the About page); null = static card
    'level' => 3,             // heading level of the name
    'cta' => true,            // "Request a consultation with {first name}" (wizard prefill, consultant)
    'focus' => 'chips',       // chips: at most three proposed focus chips (home) · line: the approved focus line verbatim (About)
    'languages' => true,      // the approved working-languages line on the card (About: false, it follows the areas)
    'sizes' => null,          // portrait sizes attribute (null: the portrait default)
    'eager' => false,         // portrait in the first viewport
])
{{--
    <x-d2.team.profile :member="$member" :href="SiteUrl::target($member['code'])" level="3" />
    Team profile card (upgrade spec §5.5, §12 C8), used by the About page and the home "Our Team" block:
      the 4:5 portrait (or the initials panel while the photo is pending: <x-d2.team.portrait>, D42, D43) ·
      approved name (heading; the card link when href is set) · approved title (role) · focus="chips": at most
      three focus chips (PROPOSED until approved; the list is named by the approved "Areas of Focus / Expertise"
      heading; D44), or focus="line": the member's approved focus line verbatim (the About DOCX's "role and
      expertise descriptor", right after the role; Phase 2, brief §5.3: approved copy is shown, never replaced by
      chips) · the approved working-languages line as plain text (<x-d2.team.languages>; :languages="false" leaves
      it to the page: the About page prints it after the member's areas, the DOCX order) · the request link.
    One chip style only (the one chip component, static), no language chips (D44).
    No credentials, years or memberships are added: the chips leave out the degree that opens Dr Mohamed's focus
    line; the About page prints that line as the DOCX writes it.
    Request link: a real link to the consultation form with ?consultant=team-… (Prefill::url, works without
    JavaScript); with JavaScript d2TeamCta calls prefill({consultant}) and scrolls to the wizard. Its visible label
    (and accessible name) is the member's own proposed "Request a consultation with {first name}", so the three
    links read apart; the generic "Request a consultation" only stands in for a member without a string of their own.
    It is a Text button (ink, underlined, no box) starting on the card's text column (sections/team.css,
    components/profile.css).
    Hover: the card's own (its edge turns ink, the name underlines; no lift, no zoom: D05).
    Styleguide-only: .is-hover / .is-focus (forced states).
--}}
@php
    use App\Design\D2\Bidi;
    use App\Design\D2\Prefill;
    use Illuminate\Support\Facades\Lang;

    $code = (string) ($member['code'] ?? '');
    $name = (string) ($member['name'] ?? '');
    $role = (string) ($member['role'] ?? '');
    // The approved focus line as written; the space before a middle dot ("Doctorate in Law · Civil, …") does not
    // break, so no line of a narrow card starts with the dot.
    $focusLine = $focus === 'line' ? (string) preg_replace('/ (?=·)/u', "\u{00A0}", trim((string) ($member['focus'] ?? ''))) : '';
    $chips = [];
    if ($focus !== 'line') {
        $chips = trans('proposed.d2.team.focus.'.$code);
        $chips = is_array($chips) ? array_slice(array_values(array_filter($chips, fn ($item) => is_string($item) && $item !== '')), 0, 3) : [];
    }
    $areasHeading = is_string($member['areas']['heading'] ?? null) ? $member['areas']['heading'] : null;
    $languageLine = $languages ? trim((string) ($member['languages'] ?? '')) : '';
    $ctaKey = 'team.cta.'.$code;
    $ctaLabel = Lang::has('proposed.d2.'.$ctaKey) ? d2p($ctaKey) : d2p('team.cta_generic');
    $proposed = app()->isProduction() ? null : 'proposed';
    $level = max(2, min(6, (int) $level));
@endphp
<div class="profile-frame">
<x-d2.ui.card {{ $attributes->class(['profile']) }} :href="$href" padding="none" :title="Bidi::isolate($name)" :title-level="$level" data-member="{{ $code }}">
    <x-slot:media class="profile__media">
        <x-d2.team.portrait :member="$member" :eager="$eager" :sizes="$sizes ?? '(min-width: 1280px) 410px, (min-width: 1024px) 31vw, (min-width: 768px) 240px, 85vw'" />
    </x-slot:media>

    @if ($role !== '')
        <p class="profile__role">{{ Bidi::isolate($role) }}</p>
    @endif

    @if ($focusLine !== '' || $chips !== [] || $languageLine !== '')
        <div class="profile__details">
            @if ($focusLine !== '')
                <p class="profile__focus">{{ Bidi::isolate($focusLine) }}</p>
            @endif
            @if ($chips !== [])
                <ul class="profile__list" role="list" @if ($areasHeading) aria-label="{{ $areasHeading }}" @endif @proposed>
                    @foreach ($chips as $item)
                        <li><x-d2.ui.chip as="span" class="profile__chip">{{ $item }}</x-d2.ui.chip></li>
                    @endforeach
                </ul>
            @endif
            @if ($languageLine !== '')
                <x-d2.team.languages :text="$languageLine" />
            @endif
        </div>
    @endif

    @if ($cta && $code !== '')
        <x-slot:footer class="profile__footer">
            <x-d2.ui.button variant="text" :href="Prefill::url(['consultant' => $code])"
                x-data="d2TeamCta" x-on:click="request" data-consultant="{{ $code }}" :data-copy-status="$proposed"
>{{ $ctaLabel }}</x-d2.ui.button>
        </x-slot:footer>
    @endif
</x-d2.ui.card>
</div>
