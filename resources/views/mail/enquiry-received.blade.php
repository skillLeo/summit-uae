{{--
    Plain-text notification for the firm (App\Mail\EnquiryReceived). Labels are the form's own
    field names, so no new copy is introduced. Raw echoes are deliberate: this is a text/plain
    body, and HTML escaping would corrupt names such as O'Brien.
    The visitor's details are printed only when $details is true (App\Requests\Notifier::includeDetails()):
    with the `log` mailer a mail body is written to the log, which never holds personal data.
--}}
reference: {!! $enquiry->reference !!}
received_at: {!! ($enquiry->received_at ?? $enquiry->created_at)->copy()->timezone((string) config('summit.contact.timezone', 'UTC'))->format('Y-m-d H:i e') !!}
status: {!! $enquiry->status !!}
locale: {!! $enquiry->locale !!}
matter_type: {!! $matter !!}
open: {!! $url !!}
@if ($details)

name: {!! $enquiry->name !!}
phone: {!! $enquiry->phone !!}
email: {!! $enquiry->email !!}
preferred_language: {!! $enquiry->preferred_language !!}
deadline_date: {!! $enquiry->deadline_date?->format('Y-m-d') !!}
deadline_details: {!! $enquiry->deadline_details !!}
@if ($enquiry->attachment_path !== null)
attachment_mime: {!! $enquiry->attachment_mime !!}
attachment_size: {!! $enquiry->attachment_size !!}
@endif
consent_at: {!! $enquiry->consent_at->toIso8601String() !!}

summary:
{!! $enquiry->summary !!}
@endif
