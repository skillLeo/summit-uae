{{-- Internal demo tool (SUMMIT_DESIGN_SWITCHER). Injected by App\Http\Middleware\DesignSwitcher; not part of either design. --}}
<nav class="sds" aria-label="{{ __('switcher.label') }}" data-design-switch>
    @foreach ($links as $number => $href)
        <a class="sds__opt" href="{{ $href }}" data-design-option="{{ $number }}" lang="{{ app()->getLocale() }}"
           @if ($number === $current) aria-current="true" @endif
           title="{{ __('switcher.shortcut', ['key' => $number]) }}">{{ __('switcher.option', ['number' => $number]) }}</a>
    @endforeach
</nav>
<script nonce="{{ $nonce }}">{!! $script !!}</script>
