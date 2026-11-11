{{-- Server-rendered starting file (no-JS path). Mirrors the Alpine template in navigator.blade.php. --}}
<article class="file-card" aria-labelledby="navigator-file-title-ssr">
    <header class="file-card__head">
        <p class="file-card__kicker"><span>{{ proposed('navigator.step_file') }}</span></p>
        <h3 class="file-card__title" id="navigator-file-title-ssr">{{ $s['title'] }}</h3>
        <p class="file-card__situation">{{ $situationIntro }} {{ $situation }}</p>
    </header>
    <section class="file-card__section">
        <h4>{{ $s['headings']['documents'] }}</h4>
        <ul class="doc-list">
            @foreach ($s['documents'] as $doc)
                <li class="doc-row">
                    <label class="check">
                        <input class="check__input" type="checkbox">
                        <span class="check__text">{{ $doc }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
        <p class="file-card__note">{{ $s['documents_note'] }}</p>
    </section>
    <div class="file-card__cols">
        <section class="file-card__section">
            <h4>{{ $s['headings']['first_steps'] }}</h4>
            <ul class="bullet-list">
                @foreach ($s['first_steps'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </section>
        <section class="file-card__section">
            <h4>{{ $s['headings']['mistakes'] }}</h4>
            <ul class="bullet-list bullet-list--mistakes">
                @foreach ($s['mistakes'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </section>
    </div>
    <div class="file-card__links">
        @foreach ($s['guides'] as $guide)
            <a class="text-link" href="{{ $guide['url'] }}">{{ $guide['label'] }}</a>
        @endforeach
        <a class="text-link" href="{{ $s['url'] }}">{{ ui('cta.explore_service') }}</a>
    </div>
    <div class="file-card__actions">
        <a class="btn btn--gold" href="{{ \App\Support\SiteUrl::home().'?'.http_build_query(['matter' => $s['code']]).'#consultation-form' }}">{{ ui('cta.request_consultation') }}</a>
    </div>
</article>
