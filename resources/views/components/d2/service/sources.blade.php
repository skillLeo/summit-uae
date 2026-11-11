@props([
    'id' => 'sources',    // section anchor
    'heading' => null,    // approved sections.sources.heading ("Official Sources for Reference")
    'links' => [],        // approved official sources [['label' => …, 'url' => https://…], …]
    'specialist' => null, // approved sections.specialist {intro, label, domain} or null
    'site' => null,       // the related specialist site record (content()->record('specialist_sites', X0n)) or null
])
{{--
    "Official Sources for Reference" with the related Summit specialist site (DOCX links S0n-REFn and X0n:
    "Open a new tab with rel="noopener noreferrer""). Drawn with the inner kit's link rows (.link-row,
    pages/inner.css) so it matches every other link list of the reading column, plus one thing the kit's
    rows do not carry: the destination host under each label (left to right, <bdi>), because these links leave
    the site. The specialist block keeps its approved intro and its approved link text (label + domain, split
    by the importer). The hidden "(opens in a new tab)" is the shared proposed controls.new_tab.
    Design 2.1 (brief §3.6 rule 5): no scales or globe glyph at the start; each row ends with the ↗ glyph of
    a link to another website.
--}}
@php
    use App\Design\D2\Bidi;

    $rows = [];
    foreach ((array) $links as $link) {
        $url = (string) ($link['url'] ?? '');
        $label = trim((string) ($link['label'] ?? ''));
        if ($label === '' || ! preg_match('#^https://#', $url)) {
            continue;
        }
        $rows[] = ['label' => $label, 'url' => $url, 'host' => preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST))];
    }
    $siteUrl = is_array($site) ? (string) ($site['url'] ?? '') : '';
    $hasSpecialist = is_array($specialist) && trim((string) ($specialist['label'] ?? '')) !== '' && preg_match('#^https://#', $siteUrl) === 1;
    $hasHeading = is_string($heading) && trim($heading) !== '';
@endphp
@if ($rows !== [] || $hasSpecialist)
    <section {{ $attributes->class(['svc-sec', 'svc-sources']) }} id="{{ $id }}" @if ($hasHeading) aria-labelledby="{{ $id }}-title" @endif>
        @if ($hasHeading)
            <h2 class="prose__heading svc-sec__title" id="{{ $id }}-title">{{ Bidi::isolate($heading) }}</h2>
        @endif
        @if ($rows !== [])
            <ul class="svc-sources__list" role="list">
                @foreach ($rows as $row)
                    <li>
                        <a class="link-row link-row--external svc-source" href="{{ $row['url'] }}" target="_blank" rel="noopener noreferrer">
                            <span class="link-row__body">
                                <span class="link-row__label">{{ Bidi::isolate($row['label']) }}</span>
                                <bdi class="link-row__text svc-source__host" dir="ltr">{{ $row['host'] }}</bdi>
                            </span>
                            <x-d2.ui.icon name="external" size="20" class="link-row__end" />
                            <span class="sr-only" @proposed>{{ d2p('controls.new_tab') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($hasSpecialist)
            <div class="svc-specialist" data-specialist="{{ $site['code'] ?? '' }}">
                @if (! empty($specialist['intro']))
                    <p class="svc-specialist__intro">{{ Bidi::isolate($specialist['intro']) }}</p>
                @endif
                <a class="link-row link-row--external svc-specialist__link" href="{{ $siteUrl }}" target="_blank" rel="noopener noreferrer">
                    <span class="link-row__body">
                        <span class="link-row__label">{{ Bidi::isolate($specialist['label']) }}</span>
                        @if (! empty($specialist['domain']))
                            <bdi class="link-row__text svc-specialist__domain" dir="ltr">{{ $specialist['domain'] }}</bdi>
                        @endif
                    </span>
                    <x-d2.ui.icon name="external" size="20" class="link-row__end" />
                    <span class="sr-only" @proposed>{{ d2p('controls.new_tab') }}</span>
                </a>
            </div>
        @endif
    </section>
@endif
