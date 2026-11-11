<?php

/*
| Design 2 controls (<x-d2.ui.button>, field, stepper, steps): interface text the components render
| themselves. PROPOSED COPY, not in the approved DOCX files: needs client approval in EN/AR/RU
| (docs/proposed-copy.md). Read as d2p('controls.<key>').
*/

return [
    // Screen-reader text inside a busy button (the visible label stays).
    'loading' => 'Loading',
    // Screen-reader text after a link that opens in a new tab (WhatsApp).
    'new_tab' => '(opens in a new tab)',
    // Visible marker next to the label of an optional field.
    'optional' => 'Optional',
    // Names of the stepper's minus and plus buttons.
    'decrease' => 'Decrease',
    'increase' => 'Increase',
    'decrease_named' => 'Decrease: :label',
    'increase_named' => 'Increase: :label',
    // Wizard step chips: the progress label and the screen-reader state of a finished step.
    'step_of' => 'Step :current of :total',
    'step_done' => 'Completed',
    // The date field (<x-d2.ui.date>, D36): the typing pattern shown while it is empty, the calendar button and the
    // calendar's controls (month and weekday names come from the browser's Intl in the page language).
    'date' => [
        'placeholder' => 'DD/MM/YYYY',
        'open' => 'Choose a date',
        'open_named' => 'Choose a date: :label',
        'previous' => 'Previous month',
        'next' => 'Next month',
        'month' => 'Month',
        'year' => 'Year',
    ],
];
