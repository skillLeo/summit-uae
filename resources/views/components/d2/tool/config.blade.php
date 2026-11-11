@props([
    'data' => [],      // strings and settings for the tool script (resources/js/d2/tools/*-view.js)
    'plurals' => [],   // [name => 'tools.common.days']: proposed-d2 keys with trans_choice() forms
])
{{--
    <x-d2.tool.config :data="[...]" :plurals="['days' => 'tools.common.days']" />
    The tools never hard-code text in JavaScript: every string they print comes from this JSON block,
    rendered in the page language. A data block (type="application/json") is never executed, so the CSP
    needs no nonce for it; JSON_HEX_TAG keeps "</script>" out of it.

    Plurals: for each key, one template per CLDR category of the locale, chosen on the server with
    trans_choice() (every form of the language: Arabic six, Russian three), with ":count" kept for the
    browser to fill. The script picks the category with Intl.PluralRules (format.js plural()); for whole
    numbers the CLDR categories and Laravel's plural indexes agree.
--}}
@php
    // A sample count per CLDR plural category of the current locale (integers only).
    $samples = [
        'ar' => ['zero' => 0, 'one' => 1, 'two' => 2, 'few' => 3, 'many' => 11, 'other' => 100],
        'ru' => ['one' => 1, 'few' => 2, 'many' => 5],
    ][app()->getLocale()] ?? ['one' => 1, 'other' => 2];
    foreach ($plurals as $name => $key) {
        $forms = [];
        foreach ($samples as $category => $count) {
            $forms[$category] = trans_choice('proposed.d2.'.$key, $count, ['count' => ':count']);
        }
        $data['plurals'][$name] = $forms;
    }
    $data['locale'] = app()->getLocale();
@endphp
<script type="application/json" data-tool-config>{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
