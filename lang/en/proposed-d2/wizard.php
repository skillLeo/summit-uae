<?php

/*
| Design 2 consultation wizard (home #consultation-form; spec §12 C14, §5.5): PROPOSED copy, not in the
| approved DOCX files. Read as d2p('wizard.…'), shown with @proposed, listed in docs/proposed-copy.md for
| client approval. Field labels, help texts, errors, notices, the consent text, the submit label and every
| delivery state (busy, received, failed, uncertain, rate limited) are APPROVED copy (ui('form.*')).
*/

return [
    // Step list and step headings, in the Contact DOCX form order (Design 2.1, D38): name and contact details
    // first, then language, matter type and summary, the deadline, the documents, and the review (notices,
    // consent, submit).
    'steps' => [
        'you' => 'You',
        'matter' => 'Your matter',
        'deadline' => 'Deadline',
        'documents' => 'Documents',
        'review' => 'Review',
    ],

    // One line under each step heading.
    'intro' => [
        'you' => 'Your name and at least one way to reach you.',
        'matter' => 'Choose the type of matter if you know it, and describe what happened.',
        'deadline' => 'If a hearing or a response or appeal deadline applies, give its date and the authority. Both fields are optional.',
        'documents' => 'Tick the documents you already have. You can send a request without them.',
        'review' => 'Check your request before you send it. You can still change anything.',
    ],

    'next' => 'Next',
    'back' => 'Back',

    // Step 1: who is contacting us (optional). Same three audiences as the hero tool card.
    'audience' => [
        'legend' => 'You are contacting us as',
        'individual' => 'Individual',
        'individual_hint' => 'For yourself or your family',
        'business' => 'Business',
        'business_hint' => 'For a company you own or represent',
        'investor' => 'Investor',
        'investor_hint' => 'About an investment, property or digital assets',
    ],

    // Step 2: the Matter Navigator situations of the chosen service (approved text), optional.
    'situation' => [
        'label' => 'Closest situation',
        'placeholder' => 'Choose the closest situation',
    ],

    // Step 3 when there is no document list to show.
    'documents' => [
        'none' => 'Choose a type of matter in the previous step to see the documents that usually help. You can also continue without this step.',
        'generic' => 'There is no standard list for this type of matter. You can mention the documents you have in your summary.',
    ],

    // Step 5: the answers, each group with a Change button (its group name follows for screen readers).
    'review' => [
        'change' => 'Change',
        'not_provided' => 'Not provided',
        // Documents group, while attachments are enabled: the name of the chosen file (or "Not provided").
        'attachment' => 'Attached document',
    ],

    // Step 5: the matter summary exactly as it will be sent (visible and editable).
    'message' => [
        'label' => 'Message we will receive',
        'hint' => 'Your summary with the context and documents you chose. You can edit it before you submit.',
        'too_long' => 'This message is longer than 5,000 characters. Please shorten it.',
        'stale' => 'Your answers changed after you edited this message.',
        'update' => 'Update the message',
    ],

    // Lines added to the message (the backend has no fields for them, so they travel as visible text).
    'context' => [
        'audience' => 'Contacting us as: :value',
        'situation' => 'Closest situation: :value',
        'consultant' => 'Consultation requested with: :value',
        'tool' => 'Started from: :value',
        'documents' => 'Documents I have:',
        // Step 1 note when a team card or a calculator started the request.
        'note' => 'Your request will mention:',
        'remove' => 'Remove',
        'removed' => 'Removed from your request.',
    ],

    // Names of the home calculators, used in the "Started from" line.
    'tool' => [
        'gratuity' => 'Gratuity estimator',
        'rent' => 'Rent increase check',
    ],

    // Announced when another section (hero, Navigator, tools, team) hands its choices to the wizard.
    'prefill_applied' => 'Your choices were added to the request.',

    // Autosave in this browser only (localStorage), never the consent box, the security check or a document.
    // The visitor sees where it is kept and can delete it: the toast, the restore prompt and the choice next
    // to Next ("Keep a draft on this device", ticked by default; unticking deletes the draft at once).
    // keep_hint: :count is the number of days the draft is kept (plural forms: English 2, Russian 3, Arabic 6).
    'draft' => [
        'saved' => 'Draft saved on this device',
        'keep' => 'Keep a draft on this device',
        'keep_hint' => 'Kept for up to :count day so that you can finish later, and deleted when you send your request.|Kept for up to :count days so that you can finish later, and deleted when you send your request.',
        'found' => 'An unfinished request is saved on this device.',
        'restore' => 'Restore draft',
        'discard' => 'Delete draft',
        'restored' => 'Draft restored.',
        'discarded' => 'Draft deleted from this device.',
        'off' => 'Draft deleted. Your answers are no longer kept on this device.',
    ],

    // Cloudflare Turnstile (the security check the endpoint requires).
    'turnstile' => [
        'pending' => 'Completing a short security check. Your request will be sent when it finishes.',
        'incomplete' => 'Please complete the security check above the button, then submit your request again.',
        'unavailable' => 'The security check could not be loaded. Check your connection and try again, or contact us directly.',
    ],

    // Label before the SLC-… reference after a received request.
    'reference' => 'Reference',
];
