<?php

// Internal annotations for the Design 2 styleguide: document upload (<x-d2.upload.dropzone>). Not public
// website copy: Arabic and Russian pages fall back to these English labels. Demo content inside the
// specimens is approved copy (ui('form.attachment.*')) or proposed copy (d2p('upload.*')).
return [
    'title' => 'Document upload',
    'intro' => 'The consultation form’s one optional attachment: one PDF, JPEG or PNG file of at most 10,000,000 bytes, checked in the browser with the approved messages and again by the server. Design 2.1: a field-like zone (1 px --field-border, radius 10, solid), the functional upload icon, a secondary "Choose a file" button, the accepted file as a plain row (no icon tile, no dot separator).',
    'flag_on' => 'ON (attachments enabled and design.d2.attachment_scanned): the real control renders inside the consultation form.',
    'flag_off' => 'OFF: the backend checks the type and size from the content and stores privately, but does not scan uploads, so Design 2 shows no attachment field and no hint (brief §5.2; the Word files\' attachment notes), whatever SUMMIT_ATTACHMENTS_ENABLED says. <x-d2.upload.dropzone> stays dormant for when a scanner exists. The specimens below render with specimen=…, never carry name="attachment" and post nothing.',
    'live' => 'Live specimen',
    'live_note' => 'Try it: choose or drop a file. A GIF, an empty file, two files or a file over 10 MB shows the matching approved error; an accepted file becomes a chip with Remove Document. Nothing leaves the page.',
    'states' => 'States',
    'state_idle' => 'Idle',
    'state_hover' => 'Hover',
    'state_focus' => 'Keyboard focus (the input lies over the zone)',
    'state_over' => 'File dragged over the zone',
    'state_file' => 'Accepted file',
    'state_error_type' => 'Error: format (read from the content)',
    'state_error_size' => 'Error: larger than 10 MB',
    'state_error_count' => 'Error: more than one file',
    'state_error_rejected' => 'Error: empty or unreadable file',
    'state_disabled' => 'Disabled (while the request is sending)',
    'state_plain' => 'Without JavaScript: the native input, posted with the form',
    'sample_file' => 'employment-contract-sample.pdf',
    'in_wizard' => 'In the wizard’s Documents step',
    'in_wizard_note' => 'Where the control would be in <x-d2.wizard.form> with a scanning backend: step 4 (Documents), after the checklist of documents usually needed (the form then posts multipart/form-data; without JavaScript the step shows the native input only). The wizard appends the file to its FormData, names it on the review, counts it in the request fingerprint, sends a 422 “attachment” back to this field and empties it on “Send a New Enquiry” (d2-upload-reset). Rendered here with the wizard’s own <x-d2.wizard.step>.',
    'contract' => 'Contract',
    'contract_note' => 'Input #{prefix}-attachment (name="attachment"), error #{prefix}-attachment-error with .field__error-text, root [data-field="{prefix}-attachment"]: the same contract as every wizard field. Events: d2-upload-change {file} bubbles; d2-upload-reset (or the form’s reset) empties it. attachmentOf(form) in components/upload-checks.js returns the file for a FormData built by hand.',
];
