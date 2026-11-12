<?php

namespace App\Mail;

use App\Models\Enquiry;
use App\Requests\ReviewTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;

/**
 * Overdue alert (docs/briefs/design-2.1-and-admin-cms.md §10.5): a request has waited longer than the internal review
 * target in business hours. Sent once per request (enquiries.overdue_notified_at). Reference and link only.
 */
#[Tries(5)]
#[DeleteWhenMissingModels]
final class RequestOverdue extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Overdue: '.$this->enquiry->reference);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.request-overdue', with: [
            'target' => ReviewTarget::hours(),
            'waited' => round(ReviewTarget::waited($this->enquiry), 1),
            'url' => EnquiryReceived::panelUrl($this->enquiry),
        ]);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }
}
