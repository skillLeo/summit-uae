<?php

// Internal annotations for the Design 2 styleguide area "Consultation wizard" (noindex utility page).
// Not public website copy: Arabic and Russian pages fall back to these English labels. The wizard itself
// shows approved copy (ui('form.*'), P01 / P07 content) and its proposed microcopy (d2p('wizard.*')).
return [
    'title' => 'Consultation wizard',
    'intro' => 'The home contact section (#consultation-form) and P07: the five-step wizard on the existing enquiry endpoint (one component; each page prints its own Word file\'s form copy: copy="home" or copy="contact") and the contact card. The specimens here use the Contact file\'s copy. The steps follow the Contact file\'s form layout (D38) under one progress indicator (D37: a step list, "Step 1 of 5: You" in a narrow card or on a phone); "You are contacting us as" and "Keep a draft on this device" are pending client approval (D39). The attachment field is not shown: the backend does not scan uploads (App\Design\D2\Attachment). The controller is a lazy chunk (wizard/controller.js, about 11 KB gzip): it loads when the wizard comes within about one viewport, on focus or a pointer press, on d2:prefill and for #consultation-form; until then the server-rendered markup already shows the stepped layout (keyed on html.js), and a click, a submit or a prefill that arrives early is queued. The live specimens run offline and load at once: nothing is saved or sent from this page.',

    'steps' => 'Steps (live, offline)',
    'steps_note' => 'Each specimen is a real wizard started at one step with sample answers. Next checks the step; Back and the finished steps of the list go back without checks and never lose data. Autosave, prefill, the security check and sending are off on this page.',
    's1' => 'Step 1, You: Context handed over by a team card and a calculator (Remove clears it), the chosen audience card (pending), then Next with an empty name and an invalid phone and e-mail: inline errors, the linked error summary in the DOCX order (name, then the contact method), focus on the first field. Both contact methods are checked when both are given; with neither, the group error appears on the fieldset and the summary links the phone input. Then the preferred language.',
    's2' => 'Step 2, Your matter: The approved matter types, the Navigator situations of the chosen service (optional), the summary.',
    's3' => 'Step 3, Deadline: The nearest date (the date picker) and the deadline details side by side (subgrid keeps labels, hints and inputs aligned).',
    's4' => 'Step 4, Documents: The documents usually needed for the chosen service (Navigator data), ticked per service, with the service page’s note. The optional document would follow the checklist only with a scanning backend; today the step is the checklist only.',
    's5' => 'Step 5, Review: The answers with Change buttons, and the message exactly as it will be sent (summary, context lines, ticked documents). Here the visitor edited it and then changed an answer, so the update prompt shows.',

    'plain' => 'Without JavaScript',
    'plain_note' => 'The same markup as one plain form: only the endpoint’s own fields, POST /{locale}/enquiries, and every outcome comes back through the no-JS redirect (flash). The checklist, the review, autosave and the security check need JavaScript and are not shown (while attachments are enabled the Documents group holds the native file input, posted as multipart); phone, WhatsApp and e-mail stay next to the form. The same plain form comes back if the controller chunk cannot load.',

    'states' => 'Delivery states',
    'states_note' => 'One at a time, at the top of the wizard; each takes focus when it appears. Every failure keeps the values and shows the direct channels. Success only on status received.',
    'state_errors' => 'error summary: browser checks or a 422, in form order; links go to the field (contact → phone, consent → checkbox)',
    'state_failed' => 'failed: status failed, 419, 413, maintenance 503, or the browser was offline',
    'state_rate_limited' => 'rate limited: 429',
    'state_uncertain' => 'uncertain: timeout, network error or unknown answer. A retry reuses the key; an edited request gets a new one',
    'state_turnstile_incomplete' => 'the security check needs the visitor (Turnstile interaction)',
    'state_turnstile_unavailable' => 'the security check could not load',
    'state_received' => 'received, with the reference',
    'state_restore' => 'restore prompt: this device holds a draft (decided at start-up, before the controller loads); Restore, Delete draft, or typing dismisses it',

    'buttons' => 'Actions',
    'buttons_note' => 'Back, Next and Submit (Back becomes a square arrow under 768px), with “Keep a draft on this device” between them: ticked by default, it says how long the draft is kept; unticking deletes the stored draft at once and nothing is saved again during the visit (the toast reads “Draft saved on this device”). While sending, Submit is busy (aria-disabled, the spinner before the approved busy label) and the steps are inert. Every button is Ink, Secondary or Text (Submit is Ink, never gold); only Back and Next carry arrows (form-back, form-next), as the brief allows.',
    'btn_default' => 'default',
    'btn_hover' => 'hover',
    'btn_focus' => 'focus-visible',
    'btn_sending' => 'sending',

    'docs_empty' => 'Documents step without a list',
    'docs_none' => 'no type of matter chosen yet',
    'docs_generic' => 'Other matter / Not sure which category applies',

    'card' => 'Contact card',
    'card_note' => 'Telephone, WhatsApp (the approved per-language draft, new tab, never sent; the mark is <x-d2.ui.brand-mark>: Meta\'s official file, or the marked placeholder until it is supplied), e-mail, the page\'s own approved languages line and hours sentence with the live status (modules/hours.js). Every icon on one column (D40); plain 20 px icons in ink-2, no tiles. No address, no map. Rows: hover veil, focus ring.',
    'card_default' => 'P07 (variant contact): Contact Details, the Contact file\'s labels, languages, office hours and the after-hours paragraph',
    'card_home' => 'home (variant home): section 09 of the homepage file, values only (no heading, no labels), languages then business hours',
    'card_states' => 'forced hover (telephone) and focus-visible (e-mail)',

    'rtl' => 'Arabic (RTL)',
    'rtl_note' => 'Step 5 and the contact card in Arabic: the step list runs right to left, arrows mirror, phone and e-mail keep their own left-to-right direction, and every line of mixed-script text takes its own direction. Its WhatsApp controls are shown without their link: a page only links the approved WhatsApp draft of its own language.',

    // Sample answers for the specimens (internal demo values, never site copy).
    'sample_name' => 'A. Example',
    'sample_phone' => '+971 50 000 0000',
    'sample_email' => 'a.example@example.com',
    'sample_summary' => 'My employment ended last month and the final settlement looks lower than expected. I would like to understand my options.',
    'sample_edit' => 'Edited by the visitor before submitting.',
    'sample_deadline' => 'Labour complaint, response due',
    'sample_reference' => 'SLC-000000-000000',
];
