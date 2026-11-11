<?php

// Internal styleguide annotations for the Design 2.1 "Controls" area (noindex utility page). Not public
// website copy: Arabic and Russian pages fall back to these English labels. Demo content on the page
// itself is approved copy (ui(), setting(), content()) or proposed copy (marked); 'sample_name' is the
// fictional sample name. Contract: docs/d2.1/phase-1/components.md.
return [
    'title' => 'Controls',
    'intro' => 'Buttons, links, icon buttons, chips, the segmented control, fields, checkbox, radio, stepper, badges, keys, progress and wizard steps in every state, on light and on dark (brief §3.5). Hover, press and focus are forced with styleguide-only classes (.is-hover, .is-active, .is-focus); everything else is live.',

    // states
    'state_default' => 'default',
    'state_hover' => 'hover',
    'state_active' => 'press',
    'state_focus' => 'keyboard focus',
    'state_disabled' => 'disabled',
    'state_loading' => 'loading',
    'state_error' => 'error',
    'state_error_focus' => 'error with focus',
    'state_success' => 'valid',
    'state_pressed' => 'selected',
    'state_pressed_hover' => 'selected, hover',
    'state_filled' => 'filled',
    'state_empty' => 'empty',
    'state_checked' => 'checked',
    'state_checked_focus' => 'checked, then keyboard focus',
    'state_unchecked' => 'unchecked',
    'state_indeterminate' => 'indeterminate',
    'state_readonly' => 'read-only',
    'state_min' => 'at the minimum',
    'state_max' => 'at the maximum',

    // buttons
    'buttons' => 'Buttons',
    'buttons_note' => '44 px tall (hero 52), padding 20 (hero 24), label 15/500 (hero 16/500), radius 6. No gradient, glow, shadow, hover lift or arrow. Hover: the fill 8% lighter or darker; press: scale .98 for 120 ms; keyboard focus: a 2 px ring at a 2 px offset; disabled: aria-disabled at 40 % (focus stays); loading: a 16 px spinner, same width.',
    'v_ink' => 'ink, the default everywhere (the header CTA too): ink fill, white label. Design 2.0 name: primary',
    'v_gold' => 'gold, the hero primary ONLY (one per page): flat #C9A96B, ink label, never a gradient. Design 2.0 name: signature',
    'v_secondary' => 'secondary: white, 1 px --line-strong, ink label',
    'v_text' => 'text: an underlined label (1 px at a 3 px offset, 2 px on hover), no fill, no side padding. Design 2.0 name: ghost',
    'v_whatsapp' => 'whatsapp: the white secondary button with an ink label and the official WhatsApp logo (a placeholder until Meta\'s files are in resources/brand/whatsapp/)',
    'sizes' => 'Sizes: 44 px for every button, 52 px for the two hero buttons',
    'size_default' => 'size 40, 44 or 48: the 44 px button (the header CTA too)',
    'size_hero' => 'size 52 or 56: the hero pair, label 16/500',
    'slots' => 'Icons, links and states in context',
    'slot_leading' => 'a functional leading icon (search, phone)',
    'slot_external' => 'a link to another website: icon-end="external" draws the ↗ mark as an SVG (opens in a new tab)',
    'slot_form' => 'the allowed arrows of the form: icon="form-back" and icon-end="form-next" (proposed Back / Next labels)',
    'slot_disclosure' => 'a disclosure toggle keeps its chevron-down (aria-expanded)',
    'slot_no_arrow' => 'icon-end="arrow" renders nothing (D03): no arrows in buttons, links or card corners',
    'slot_loading' => 'loading with an icon: the spinner takes the icon slot, the width stays',
    'full' => 'Full width (:full="true"), the hero pair on a phone',
    'aliases' => 'Design 2.0 names keep working (the class follows the name you pass)',
    'aliases_note' => 'primary renders as ink, signature as gold, ghost as text; size 56 as the 52 px hero button and 40 as the 44 px button. Areas change only the places that must not be gold.',

    // links
    'links' => 'Links',
    'links_note' => '<x-d2.ui.link>: body links are ink, underlined 1 px at a 3 px offset, 2 px on hover; nav links are ink-2 and ink on hover and when current. A link to another website gets the ↗ icon (SVG) and opens in a new tab.',
    'link_body' => 'body link (hover: 2 px underline)',
    'link_nav' => 'nav links, the first one current (aria-current="page")',
    'link_external' => 'external link: new tab, ↗ icon, hidden "(opens in a new tab)"',

    // icon buttons
    'icon_buttons' => 'Icon buttons',
    'icon_buttons_note' => 'Square, radius 6, 40, 44 or 48 px (a 40 px one keeps a 44 px hit area). The icon is the visible label; aria-label names it and the tooltip (an overlay) repeats it after 120 ms. No shadow, no lift.',
    'ib_ghost' => 'ghost: transparent, icon in ink-2, ink on hover',
    'ib_secondary' => 'secondary: white, 1 px --line-strong',
    'ib_live' => 'live: hover or keyboard focus shows the tooltip; the carousel arrows are the allowed carousel-prev / carousel-next; the WhatsApp link opens in a new tab',

    // chips
    'chips' => 'Chips',
    'chips_note' => 'One chip: 32 px, a pill, a 44 px hit area (48 on touch). Unselected: white with 1 px --line-strong; selected: ink with a white label; the optional count in ink-3. No dots, no icons.',
    'c_live' => 'live: FAQ categories with their question counts, the first one selected',
    'c_static' => 'static labels (as="span"; icon="contract" is ignored) and link chips (aria-current on the current language)',

    // segmented
    'segmented' => 'Segmented control',
    'segmented_note' => '2 to 4 options of the same level. A --band track, a white thumb with a 1 px edge that slides on the snappy spring (200 ms). Native radios: arrow keys move inside the group; without JavaScript the checked segment itself is the white thumb.',
    'seg_live' => 'live, three options',
    'seg_40' => 'two options, size 40',
    'seg_disabled' => 'one option disabled, visible legend',
    'seg_full' => 'full width',

    // fields
    'fields' => 'Fields',
    'fields_note' => '48 px, 1 px --field-border (3.4:1), radius 6, no shadow. The label above (15/500), the help text under it (Small, ink-3), the error under the field (danger, the error icon and the text). aria-describedby lists the help, and the error only while it shows.',
    'input' => 'Input: label, help, required (*)',
    'f_optional' => 'optional marker, left-to-right value',
    'f_icon' => 'a functional leading icon (search), hidden label',
    'f_wrap' => 'the long approved placeholder wraps inside the field instead of being cut (D09): while the field is empty it grows one line height per extra line, and it is 48 px again once a word is typed',
    'columns' => 'Steppers, selects and date pickers fill the same column width as text inputs (D33)',
    'select' => 'Select (the native picker)',
    'select_groups' => 'Option groups (Navigator situations by service)',
    'textarea' => 'Textarea (grows with its text)',
    'date' => 'Date (native Gregorian picker; D36 replaces it in Phase 2)',
    'stepper' => 'Stepper',
    'stepper_note' => 'Stepper: the number input is the source of truth; the minus and plus buttons step it, repeat while held and switch off at the limits (0 to 5 here). It fills its column.',
    'stepper_label' => 'Number (specimen field)',
    'sample_name' => 'A. Example',

    // checkbox + radio
    'checks' => 'Checkbox and radio',
    'checks_note' => '20 px box (radius 6) with a tick that draws in; 20 px ring with a dot on the snappy spring. Each row is at least 44 px tall (48 on touch); the label is the target too.',
    'check_list' => 'checklist (live)',
    'radio_group' => 'radio group (live: fieldset, legend, arrow keys)',

    // badges, keys, progress, steps
    'badges_keys_progress' => 'Badges, keys and progress',
    'badges_note' => 'Badges: 24 px, radius 6, 13/500, the brief\'s badge pairs. A status badge always shows an icon and text.',
    'tone_info' => 'Information',
    'tone_success' => 'Done',
    'tone_warning' => 'Check this',
    'tone_danger' => 'Error',
    'tone_neutral' => 'Neutral',
    'tone_brand' => 'Brand',
    'tone_bronze' => 'bronze = brand',
    'tone_night' => 'On dark',
    'badges_status' => 'status tones: info, success, warning, danger (their icon is added)',
    'badges_plain' => 'neutral and brand labels (no status, no icon)',
    'kbd_note' => 'keys: the interface sans, 13/500, white with a 1 px edge (only inside the command palette: the header shows no key cap, D14)',
    'progress_note' => 'progress: a 4 px ink bar on a --line track; value changes grow it on --spring-bar',
    'steps' => 'Wizard steps',
    'steps_note' => 'The wizard\'s one progress indicator (D37): a step list (done with a check, current in ink, upcoming quiet; form steps may be numbered) joined by hairlines; below 768 px (or in a narrow wizard) the single line "Step n of N: Name". No chips, no progress bar.',
    'steps_first' => 'first step',
    'steps_middle' => 'step 3 of 5',
    'steps_done' => 'all done',
    'steps_interactive' => 'interactive: finished steps are buttons',

    // dark
    'dark' => 'On dark',
    'dark_note' => 'Inside .on-dark (the dark band, the footer, dark panels) the focus ring is light gold, ink buttons invert to white with an ink label, secondary buttons take the night look, chips and the segmented thumb select in white, fields sit on --dark-2 with a --field-border-on-dark edge, checked boxes are white with an ink tick.',
    'v_ink_dark' => 'ink on a dark surface: white fill, ink label (the strongest action on dark)',
    'v_night' => 'night: ink on dark (--dark-2, a hairline edge, white label); here with the dark-surface WhatsApp logo',
    'v_secondary_dark' => 'secondary on a dark surface: the night look',
    'v_whatsapp_dark' => 'whatsapp stays the white button with the light-surface logo',
    'progress_badge_kbd' => 'progress, badge and keys on dark',
    'dark_light_card' => 'a white card inside a dark section (.on-light): light controls, the ink focus ring and an ink button again',
];
