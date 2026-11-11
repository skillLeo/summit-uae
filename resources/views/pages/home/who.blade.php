{{-- 03 Who We Help: five text columns, no icons; a segmented scroller on phones. --}}
<section class="section section--ruled" id="who-we-help" aria-labelledby="who-title">
    <div class="container" x-data="whoScroller">
        <header class="section-head">
            <h2 id="who-title">{{ $c['who_we_help']['heading'] ?? '' }}</h2>
            <p>{{ $c['who_we_help']['intro'] ?? '' }}</p>
        </header>

        <ul class="who-segments" aria-hidden="true">
            @foreach ($c['who_we_help']['items'] ?? [] as $i => $item)
                <li>
                    <button class="who-segment" type="button" tabindex="-1" data-who-segment="{{ $i }}" @if ($i === 0) aria-current="true" @endif>{{ $item['title'] }}</button>
                </li>
            @endforeach
        </ul>

        <ul class="who-list" data-who-list>
            @foreach ($c['who_we_help']['items'] ?? [] as $i => $item)
                <li class="who-item" id="who-{{ $item['key'] }}" data-who-item="{{ $i }}">
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
