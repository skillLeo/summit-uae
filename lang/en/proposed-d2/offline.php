<?php

/*
| Design 2 offline page (owner: system-pages; d2::pages.offline, /xx/offline/): PROPOSED copy, not in the
| approved DOCX files. Read as d2p('offline.…'), shown with @proposed, listed in docs/proposed-copy.md for
| approval. The heading is Design 1's proposed offline.title (lang/{locale}/proposed.php, already listed for
| approval, also the page title set by SiteController@offline); the contact details are approved (setting(),
| the P07 labels, TEAM 1's <x-d2.contact.card>).
*/

return [
    // Lead under the heading. Design 1's offline.text ends with a colon that points at its channel list;
    // in Design 2 the contact card is not directly under the sentence, so the sentence ends with a full stop.
    'lead' => 'This page is not available offline. You can still reach us by phone, WhatsApp or e-mail.',

    // The retry action: loads the address the visitor asked for again.
    'retry' => 'Try again',

    // Heading of what still works without a connection: the contact card and the three tips.
    'list_title' => 'What you can still do',
    'call_title' => 'Call us',
    'call_text' => 'A phone call uses your mobile network, not the internet.',
    'email_title' => 'Write to us',
    'email_text' => 'Your e-mail app keeps the message in its outbox and sends it once you are back online.',
    'docs_title' => 'Prepare your documents',
    'docs_text' => 'Gather the papers related to your matter, so they are ready when you contact us.',

    // What needs the network again.
    'later' => 'Other pages, the tools and the consultation form need a connection.',
];
