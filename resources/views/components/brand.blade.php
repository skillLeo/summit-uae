@props(['name' => 'emblem'])
@php
    // Official mark, vectorised from package/brand (resources/brand/*.svg, fill="currentColor").
    // Decorative here: the surrounding link or heading carries the accessible name.
    static $cache = [];
    $path = resource_path("brand/{$name}.svg");
    if (! isset($cache[$name])) {
        $svg = is_file($path) ? (string) file_get_contents($path) : '';
        $svg = preg_replace('/<\?xml.*?\?>|<!--.*?-->/s', '', $svg) ?? '';
        $cache[$name] = trim($svg);
    }
    $svg = $cache[$name];
    $class = trim('brand-mark brand-mark--'.$name.' '.($attributes->get('class') ?? ''));
@endphp
@if ($svg !== '')
    {!! preg_replace('/<svg\b/', '<svg class="'.e($class).'" aria-hidden="true" focusable="false"', $svg, 1) !!}
@endif
