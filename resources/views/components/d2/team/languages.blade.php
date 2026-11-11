@props([
    'text' => '',  // the member's approved working-languages line ("Working Languages: Arabic · English · Russian")
])
{{--
    <x-d2.team.languages :text="$member['languages']" />
    A team member's approved working-languages line as plain text (D44), exactly as the About DOCX writes it (its
    middle dots are approved copy, client question 9). The label (with its colon) and the items are two inline
    spans; the items are one inline block, so a narrow column breaks the line after the label ("Working
    Languages:") and never puts a middle dot at the start of a line. Used by <x-d2.team.profile> (the home's team
    cards) and by the About page after each member's areas (components/profile.css .profile__languages).
--}}
@php
    use App\Design\D2\Bidi;

    $text = trim((string) $text);
    $label = null;
    $items = '';
    if (preg_match('/^(.+?:)\s*(\S.*)$/u', $text, $m) === 1) {
        [$label, $items] = [$m[1], $m[2]];
    }
@endphp
@if ($text !== '')
    <p {{ $attributes->class(['profile__languages']) }}>@if ($label !== null)<span class="profile__languages-label">{{ Bidi::isolate($label) }}</span> <span class="profile__languages-items">{{ Bidi::isolate($items) }}</span>@else{{ Bidi::isolate($text) }}@endif</p>
@endif
