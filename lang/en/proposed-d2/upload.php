<?php

/*
| Design 2 document upload (<x-d2.upload.dropzone>: the backend's one optional attachment, shown only
| while config('summit.features.attachments_enabled') is true). PROPOSED COPY, not in the approved DOCX
| files: needs client approval in EN/AR/RU (docs/proposed-copy.md). Read as d2p('upload.<key>').
| The approved label, help, "Remove Document" and the four error messages come from ui('form.attachment.*').
*/

return [
    // Inside the drop zone: the line next to the upload icon (pointer devices only) and the button-like
    // label of the native file input (the whole zone opens the file picker).
    'prompt' => 'Drag the document here',
    'choose' => 'Choose a file',
    // While a file is dragged over the zone.
    'drop' => 'Release to attach the document',
    // Announced to screen readers after a document is chosen or removed.
    'attached' => 'Document attached: :name (:size)',
    'removed' => 'Document removed.',
    // File size on the file chip (decimal units, as in the approved "up to 10 MB").
    'size_kb' => ':size KB',
    'size_mb' => ':size MB',
    // Format badge on the file chip (read from the file's first bytes, as the server does).
    'type' => [
        'pdf' => 'PDF',
        'jpeg' => 'JPG',
        'png' => 'PNG',
    ],
];
