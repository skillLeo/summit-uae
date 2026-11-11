{{--
    Styleguide: Design 2.1 icons (brief §3.5, §3.6 rules 3 and 5, §3.8; docs/d2.1/phase-1/components.md §3 and §4):
    the functional set at 20 px, the explicit arrow names, one pen at every size, the RTL rule, icons in context and
    the official WhatsApp logo (Meta's files, or the marked placeholder until they are dropped in). Decorative glyphs
    that are still in the set are listed by name only (never drawn here). Annotations: lang/en/styleguide_d2/icons.php.
    Demo text is approved copy only (ui(), setting(), content()) or proposed copy marked @proposed.
    data-lint-specimen (qa/README.md, lint rule 13) marks every gallery that shows icons out of their context: the set,
    the arrows, the sizes, the direction rows and the clock beside the hours sentence (the clock belongs to the contact
    block). The lint still checks each name there, but not its place; the buttons and the external link of "In context"
    stay unmarked, so their places are checked like on any page.
--}}
@php
    use App\Design\D2\Icons;
    use App\Design\D2\WhatsAppLogo;
    use App\Support\SiteUrl;

    $t = fn (string $key, array $replace = []) => __('styleguide_d2/icons.'.$key, $replace);
    // Annotations are English on every page: lang="en" on the Arabic and Russian pages (WCAG 3.1.2).
    $en = app()->isLocale('en') ? '' : 'lang=en';
    $functional = array_values(array_filter(Icons::ALLOWED, fn ($name) => ! array_key_exists($name, Icons::ALIASES)));
    $decorative = array_values(array_diff(Icons::names(), Icons::ALLOWED, Icons::ARROWS));
    $flips = ['chevron', 'chevron-start', 'external', 'carousel-next', 'form-next'];
    $fixed = ['phone', 'check', 'clock', 'search'];
    $files = ['light' => WhatsAppLogo::variant('light'), 'dark' => WhatsAppLogo::variant('dark')];
    $site = content()->specialistSites()[0] ?? null;
@endphp
<section class="sg-section" id="sg-icons">
    <h2 {{ $en }}>{{ $t('title') }}</h2>
    <p {{ $en }}>{{ $t('intro') }}</p>

    {{-- ------------------------------------------------------------------ the functional set --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('set') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('set_note', ['count' => count($functional)]) }}</p>
    <div class="sg-grid sg-grid--narrow" data-sg="icon-set" data-lint-specimen>
        @foreach ($functional as $name)
            <div class="sg-specimen">
                <x-d2.ui.icon :name="$name" class="icon--muted" />
                <code class="sg-code">{{ $name }}</code>
                @if (Icons::mirrors($name))
                    <span class="sg-label" {{ $en }}>{{ $t('mirrors') }}</span>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ the allowed arrows --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('arrows') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('arrows_note') }}</p>
    <div class="sg-grid sg-grid--narrow" data-sg="icon-arrows" data-lint-specimen>
        @foreach (Icons::ALIASES as $alias => $glyph)
            @continue(! in_array($alias, ['carousel-prev', 'carousel-next', 'form-back', 'form-next'], true))
            <div class="sg-specimen">
                <x-d2.ui.icon :name="$alias" class="icon--muted" />
                <code class="sg-code">{{ $alias }}</code>
                <span class="sg-label" {{ $en }}>{{ $t('alias_of', ['glyph' => $glyph]) }}</span>
            </div>
        @endforeach
    </div>
    <p class="sg-label" {{ $en }}>{{ $t('decorative', ['names' => implode(', ', $decorative)]) }}</p>

    {{-- ------------------------------------------------------------------ sizes --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('sizes') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('sizes_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="icon-sizes" data-lint-specimen>
        @foreach (['search', 'phone', 'info'] as $name)
            <div class="sg-specimen">
                <div class="sg-row">
                    @foreach ([16, 20, 24, 48] as $size)
                        <x-d2.ui.icon :name="$name" :size="$size" class="icon--muted" />
                    @endforeach
                </div>
                <span class="sg-label" {{ $en }}>{{ $t('size_row', ['name' => $name]) }}</span>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ direction --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('rtl') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('rtl_note') }}</p>
    <div class="sg-grid sg-grid--full">
        @foreach (['rtl_page' => null, 'rtl_forced' => 'rtl', 'rtl_ltr' => 'ltr'] as $label => $dir)
            <div class="sg-specimen" data-sg="icon-dir-{{ $dir ?? 'page' }}">
                <div class="sg-states" data-lint-specimen @if ($dir) dir="{{ $dir }}" @endif>
                    @foreach ($flips as $name)
                        <div>
                            <x-d2.ui.icon :name="$name" class="icon--muted" />
                            <code class="sg-code">{{ $name }}</code>
                        </div>
                    @endforeach
                    @foreach ($fixed as $name)
                        <div>
                            <x-d2.ui.icon :name="$name" class="icon--muted" />
                            <code class="sg-code">{{ $name }}</code>
                        </div>
                    @endforeach
                </div>
                <span class="sg-label" {{ $en }}>{{ $t($label) }}</span>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------ in context --}}
    <h3 class="sg-sub" {{ $en }}>{{ $t('context') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('context_note') }}</p>
    <div class="sg-grid sg-grid--wide" data-sg="icon-context">
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.button variant="secondary" icon="phone" :href="setting('phone_href')"><bdi dir="ltr">{{ setting('phone_display') }}</bdi></x-d2.ui.button>
                <x-d2.ui.button variant="text" icon="mail" :href="setting('email_href')"><bdi dir="ltr">{{ setting('email') }}</bdi></x-d2.ui.button>
            </div>
            <span class="sg-label" {{ $en }}>{{ $t('in_buttons') }}</span>
        </div>
        @if ($site)
            <div class="sg-specimen">
                <p class="t-body"><x-d2.ui.link :href="$site['url']" :external="true">{{ $site['label'] }}</x-d2.ui.link></p>
                <span class="sg-label" {{ $en }}>{{ $t('in_links') }}</span>
            </div>
        @endif
        <div class="sg-specimen">
            <p class="t-ui" data-lint-specimen><x-d2.ui.icon name="clock" size="20" class="icon--inline icon--muted" /> {{ content()->page('P01')['content']['contact']['hours'] ?? '' }}</p>
            <span class="sg-label" {{ $en }}>{{ $t('inline') }}</span>
        </div>
    </div>

    {{-- ------------------------------------------------------------------ the WhatsApp logo --}}
    <h3 class="sg-sub" id="sg-icons-whatsapp" {{ $en }}>{{ $t('whatsapp') }}</h3>
    <p class="sg-label" {{ $en }}>{{ $t('whatsapp_note') }}</p>
    <p class="sg-label" {{ $en }} data-sg="whatsapp-state">
        @foreach ($files as $surface => $file)
            {{ $t('whatsapp_'.$surface) }}: {{ $file ? $t($file['published'] ? 'whatsapp_published' : 'whatsapp_inline', ['file' => basename($file['file'])]) : $t('whatsapp_missing') }}<br>
        @endforeach
    </p>
    <div class="sg-grid sg-grid--wide" data-sg="whatsapp-light">
        <div class="sg-specimen">
            <div class="sg-row">
                <x-d2.ui.icon name="whatsapp" size="20" />
                <x-d2.ui.icon name="whatsapp" size="24" />
                <x-d2.ui.icon name="whatsapp" size="48" />
            </div>
            <span class="sg-label" {{ $en }}>{{ $t('wa_light') }}</span>
        </div>
        <div class="sg-specimen">
            <div class="cluster">
                <x-d2.ui.button variant="whatsapp" :href="setting('whatsapp_url')">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
                <x-d2.ui.icon-button icon="whatsapp" :label="ui('cta.whatsapp')" variant="secondary" size="48" :href="setting('whatsapp_url')" target="_blank" tip="top-end" />
            </div>
            <span class="sg-label" {{ $en }}>{{ $t('wa_buttons') }}</span>
        </div>
    </div>
    <div class="sg-night on-dark on-night" data-sg="whatsapp-dark">
        <div class="sg-grid sg-grid--wide">
            <div>
                <div class="sg-row">
                    <x-d2.ui.icon name="whatsapp" size="20" />
                    <x-d2.ui.icon name="whatsapp" size="24" />
                    <x-d2.ui.icon name="whatsapp" size="48" />
                </div>
                <span class="sg-label" {{ $en }}>{{ $t('wa_dark') }}</span>
            </div>
            <div>
                <div class="cluster">
                    <x-d2.ui.button variant="night" icon="whatsapp" :href="setting('whatsapp_url')" :external="true">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
                    <x-d2.ui.button variant="whatsapp" :href="setting('whatsapp_url')">{{ ui('cta.whatsapp') }}</x-d2.ui.button>
                </div>
                <span class="sg-label" {{ $en }}>{{ $t('wa_dark_buttons') }}</span>
            </div>
        </div>
    </div>
    {{-- A caption like the other annotations of this group: 16 px below the dark band (the frame's rhythm). --}}
    <p class="sg-label" {{ $en }}>{{ $t('whatsapp_drop') }}</p>
</section>
