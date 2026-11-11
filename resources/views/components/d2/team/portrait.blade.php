@props([
    'member',                 // content()->teamMembers() record (code, name, alt, portrait …)
    'sizes' => '(min-width: 1280px) 410px, (min-width: 1024px) 31vw, (min-width: 768px) 240px, 85vw',
    'eager' => false,         // true only when the portrait is in the first viewport
])
{{--
    <x-d2.team.portrait :member="$member" />
    The 4:5 portrait of a team member (Design 2.1 D42, D43; the About DOCX: "Use a 4:5 portrait crop with the face
    intact", "Keep styling and image proportions consistent"). Every frame is 4:5 wherever it is drawn (cards, rows,
    the About head tiles). AVIF and WebP at 480 and 800 px from public/images/d2/team/ (scripts/d2/portraits.mjs),
    explicit width/height (no layout shift), lazy, object-fit cover. The alt text is the member's approved `alt`.
    One eye line (D42): config('design.d2.team') holds each photo's focal point (the midpoint between the pupils);
    App\Design\D2\Portraits turns it into custom properties (printed once per page in a nonce'd <style>) that place
    the photo so the eyes of every portrait sit on the same line (components/profile.css). The admin sets the focal
    point per photo in Phase 4.
    Photo pending (D43): while the member's photo_pending flag is on (Ms Sarah Zaki) or a file is missing, a neutral
    panel of the same 4:5 size on the band shows the initials of the last two words of the approved name in the
    interface sans (no serif, no ring), decorative (aria-hidden): never a placeholder photo, avatar or icon.
--}}
@php
    use App\Design\D2\Portraits;
    use Illuminate\Support\Facades\Vite;

    $code = (string) ($member['code'] ?? '');
    $slug = is_string($member['portrait'] ?? null) ? basename($member['portrait']) : '';
    $file = fn (int $width, string $ext) => "images/d2/team/{$slug}-{$width}.{$ext}";
    $hasFiles = $slug !== '' && is_file(public_path($file(480, 'webp'))) && is_file(public_path($file(800, 'webp')));
    $hasPortrait = $hasFiles && ! Portraits::pending($code);
    $name = trim((string) ($member['name'] ?? ''));
    $alt = trim((string) ($member['alt'] ?? '')) ?: $name;
    // Initials: first letter of each of the last two words of the approved name ("Ms Sarah Zaki" → S Z,
    // "الأستاذة سارة زكي" → س ز, "Сара Заки" → С З). Each letter is its own element, so Arabic letters never join.
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, -2));
    $css = $hasPortrait ? Portraits::css() : '';
@endphp
@if ($css !== '')
    @once
        <style nonce="{{ Vite::cspNonce() }}" data-portraits>{!! $css !!}</style>
    @endonce
@endif
<div {{ $attributes->class(['portrait', 'portrait--photo' => $hasPortrait, 'portrait--pending' => ! $hasPortrait]) }} data-member="{{ $code }}"
    @unless ($hasPortrait) data-photo-pending="true" @endunless>
    @if ($hasPortrait)
        <picture>
            <source type="image/avif" srcset="/{{ $file(480, 'avif') }} 480w, /{{ $file(800, 'avif') }} 800w" sizes="{{ $sizes }}">
            <img class="portrait__img" src="/{{ $file(480, 'webp') }}" srcset="/{{ $file(480, 'webp') }} 480w, /{{ $file(800, 'webp') }} 800w"
                sizes="{{ $sizes }}" width="480" height="600" alt="{{ $alt }}"
                @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async">
        </picture>
    @else
        <div class="portrait__panel" aria-hidden="true">
            <span class="portrait__initials">
                @foreach ($initials as $letter)
                    <span class="portrait__initial">{{ $letter }}</span>
                @endforeach
            </span>
        </div>
    @endif
</div>
