<?php

/*
| Internal admin panel (Filament, /admin). Staff-facing interface labels, not public copy: never shown
| on the public site, so they are not proposed keys. Approved content (service titles, language and
| matter options, the brand name) is read from the DOCX import through content()->ui() / service().
| Keep the keys identical in lang/{en,ar,ru}/admin.php.
*/

return [

    'brand' => [
        'panel' => 'Admin',
    ],

    'locale' => [
        'label' => 'Interface language',
        'switch' => 'Language',
        'en' => 'English',
        'ar' => 'العربية',
        'ru' => 'Русский',
        'saved' => 'Interface language changed.',
    ],

    'nav' => [
        'requests' => 'Consultation requests',
        'requests_short' => 'Requests',
        'request' => 'Consultation request',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'subheading' => 'Consultation requests sent through the website form. Spam is excluded from every count.',
    ],

    'stats' => [
        'week' => 'Received, last 7 days',
        'week_previous' => 'Previous 7 days: :count',
        'month' => 'Received, last 30 days',
        'month_previous' => 'Previous 30 days: :count',
        'awaiting' => 'Awaiting review',
        'awaiting_hint' => 'Status “New”. Open the list',
        'ratio' => 'Draft-to-submitted ratio',
        'ratio_value' => 'Not tracked',
        'ratio_hint' => 'Drafts stay on the visitor’s device by design: nothing is sent or counted before they submit.',
    ],

    'by_service' => [
        'heading' => 'Requests by service',
        'description' => 'The matter type the visitor chose on the form.',
        'service' => 'Service',
        'share' => 'Share of all time',
    ],

    'by_language' => [
        'heading' => 'Requests by language',
        'description' => 'Site language: the version of the site the form was sent from. Preferred: the language the visitor asked to be contacted in.',
        'language' => 'Language',
        'site' => 'Site language',
        'preferred' => 'Preferred',
        'total' => 'All languages',
    ],

    'latest' => [
        'heading' => 'Latest requests',
        'all' => 'All requests',
    ],

    'period' => [
        'week' => '7 days',
        'month' => '30 days',
        'all' => 'All time',
    ],

    'status' => [
        'new' => 'New',
        'in_review' => 'In review',
        'closed' => 'Closed',
        'spam' => 'Spam',
    ],

    'field' => [
        'reference' => 'Reference',
        'received' => 'Received',
        'status' => 'Status',
        'status_changed_at' => 'Status changed',
        'name' => 'Name',
        'contact' => 'Contact',
        'phone' => 'Phone',
        'email' => 'Email',
        'service' => 'Service',
        'site_language' => 'Site language',
        'preferred_language' => 'Preferred language',
        'summary' => 'Summary',
        'deadline_date' => 'Deadline or hearing date',
        'deadline_details' => 'Deadline details',
        'consent_at' => 'Consent given',
        'consent_text' => 'Consent wording shown',
        'attachment' => 'Attachment',
        'internal_note' => 'Internal note',
        'internal_note_hint' => 'Visible only in this panel. Stored encrypted.',
        'updated_at' => 'Last updated',
    ],

    'value' => [
        'not_chosen' => 'Not chosen',
        'none' => '—',
        'not_given' => 'Not given',
    ],

    // PHP date characters (Carbon translatedFormat), Dubai time: App\Filament\Support\AdminFormat
    'format' => [
        'date' => 'j F Y',
        'date_short' => 'j M Y',
        'datetime' => 'j F Y, H:i',
        'time' => 'H:i',
    ],

    // decimal units, like the public form's "up to 10 MB" and its upload chip (AdminFormat::fileSize)
    'size' => [
        'kb' => ':size KB',
        'mb' => ':size MB',
    ],

    'section' => [
        'request' => 'Request',
        'request_hint' => 'Decrypted for this page only. Keep personal data inside the panel.',
        'visitor' => 'Visitor',
        'review' => 'Review',
        'record' => 'Consent and record',
        'attachment' => 'Attachment',
    ],

    'filter' => [
        'status' => 'Status',
        'service' => 'Service',
        'language' => 'Site language',
        'received_from' => 'Received from',
        'received_until' => 'Received until',
        'from_indicator' => 'Received from :date',
        'until_indicator' => 'Received until :date',
        'any_date' => 'Any date',
    ],

    'action' => [
        'menu' => 'Actions',
        'change_status' => 'Change status',
        'mark_in_review' => 'Mark in review',
        'close' => 'Close request',
        'reopen' => 'Reopen',
        'download' => 'Download attachment',
        'open' => 'Open',
    ],

    'notify' => [
        'status_changed' => 'Status changed to “:status”.',
        'status_changed_many' => '{1} Status changed for 1 request.|[2,*] Status changed for :count requests.',
        'saved' => 'Request updated.',
        'file_missing' => 'The file is no longer in storage.',
    ],

    'attachment' => [
        'disabled' => 'Attachments are switched off on the public form (SUMMIT_ATTACHMENTS_ENABLED=false), so no file can arrive with a request.',
        'none' => 'No file was attached.',
        'meta' => ':type · :size',
        'download_hint' => 'Downloads inside your signed-in session only: there is no shareable link.',
    ],

    'spam' => [
        'note' => 'Stored as spam automatically because the hidden honeypot field was filled. No notification email was sent.',
    ],

    'empty' => [
        'heading' => 'No consultation requests yet',
        'description' => 'Requests sent through the website consultation form appear here.',
        'filtered' => 'No requests match these filters.',
    ],

    'login' => [
        'languages' => 'Interface language',
    ],

];
