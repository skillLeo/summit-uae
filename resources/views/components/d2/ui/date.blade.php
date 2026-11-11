@props([
    'size' => 48,            // 48 | 40
    'invalid' => null,
    'valid' => false,
    'wrapClass' => null,
    'label' => null,         // what the calendar button names ("Choose a date: …"); default: the field label
])
{{--
    <x-d2.ui.date name="deadline_date" :value="old('deadline_date')" min="2026-01-01" />

    Design 2.1 date field (D36: "a localised, keyboard-accessible, RTL-aware date picker that still accepts typed
    dates; never the browser's native dd/mm/yyyy"). Pass-through attributes (name, value, min, max, data-*, aria-*)
    go to the <input>; inside <x-d2.ui.field> it is wired to the label, hint and error like the input. It fills its
    column (D33). Styles: resources/css/d2/components/date.css.

    - Without JavaScript it is the platform's own <input type="date"> (the server renders it; the value is ISO).
    - Alpine d2Date (resources/js/d2/components/date.js) turns it into a text field that takes the date typed in
      the page language's order (day, month, year; any separator, Arabic-Indic digits too) and shows it in the
      language's short form ("01/03/2018", Russian "01.03.2018"), with the pattern as its placeholder. The field
      keeps the native contract: the element with the field id reports `.value` as ISO ('' while empty or not a
      date) and `.validity.badInput` while the text is not a date; setting `.value` to an ISO date shows it; the
      `name` moves to a hidden input that always holds the ISO value, so a form posts exactly what it did before.
    - The calendar button opens the calendar (resources/js/d2/date/calendar.js, a lazy chunk), also Alt+ArrowDown
      in the field: a dialog (data-overlay) with previous / next month, month and year selects and the month grid
      (role="grid", the week from the locale's first day, month and weekday names from Intl in the page language,
      mirrored in RTL). Keys: arrows by day and week (left and right swap in RTL), Home / End the week, Page Up /
      Page Down the month, with Shift the year, Enter or Space picks, Escape closes; focus returns to the button.
      The <template> below holds its markup, so every text and icon comes from the server. The root carries
      data-datepicker (the design lint's mark of a date picker: its calendar icon is functional there).
--}}
@php
    // The <x-d2.ui.field> around this control, if any (the same lookup as <x-d2.ui.input>).
    $field = (function (): array {
        foreach (array_reverse(array_keys($this->componentStack)) as $i) {
            $view = $this->componentStack[$i];
            if (is_string($view) && str_ends_with($view, 'd2.ui.field')) {
                return $this->componentData[$i] ?? [];
            }
        }

        return [];
    })->call($__env);
    $name = $label ?? ($field['label'] ?? null);
    $name = is_string($name) ? trim(strip_tags($name)) : null;
    $open = filled($name) ? d2p('controls.date.open_named', ['label' => $name]) : d2p('controls.date.open');
    $proposed = app()->isProduction() ? null : 'proposed';
@endphp
<span @class(['datefield', 'datefield--40' => (int) $size === 40]) x-data="d2Date" data-date data-datepicker data-date-placeholder="{{ d2p('controls.date.placeholder') }}">
    <x-d2.ui.input type="date" icon-end="calendar" :size="$size" :invalid="$invalid" :valid="$valid" :wrap-class="$wrapClass"
        data-date-input :data-copy-status="$proposed" {{ $attributes }} />
    <button type="button" class="datefield__open" data-date-open aria-label="{{ $open }}" aria-haspopup="dialog" aria-expanded="false" @proposed></button>
    <template data-date-template>
        <div class="datepicker" role="dialog" aria-modal="true" data-overlay data-datepicker-dialog @proposed>
            <p class="sr-only" aria-live="polite" data-cal-title></p>
            <div class="datepicker__head">
                <button type="button" class="datepicker__nav" data-cal-step="-1" aria-label="{{ d2p('controls.date.previous') }}"><x-d2.ui.icon name="carousel-prev" size="20" /></button>
                <span class="datepicker__selects">
                    <span class="control control--select control--40 datepicker__control">
                        <select class="select datepicker__select" data-cal-month aria-label="{{ d2p('controls.date.month') }}"></select>
                        <span class="control__icon control__icon--end" aria-hidden="true"><x-d2.ui.icon name="chevron-down" size="20" /></span>
                    </span>
                    <span class="control control--select control--40 datepicker__control">
                        <select class="select datepicker__select" data-cal-year aria-label="{{ d2p('controls.date.year') }}"></select>
                        <span class="control__icon control__icon--end" aria-hidden="true"><x-d2.ui.icon name="chevron-down" size="20" /></span>
                    </span>
                </span>
                <button type="button" class="datepicker__nav" data-cal-step="1" aria-label="{{ d2p('controls.date.next') }}"><x-d2.ui.icon name="carousel-next" size="20" /></button>
            </div>
            <table class="datepicker__grid" role="grid">
                <thead><tr data-cal-weekdays></tr></thead>
                <tbody data-cal-days></tbody>
            </table>
        </div>
    </template>
</span>
