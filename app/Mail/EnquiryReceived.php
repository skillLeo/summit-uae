<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Notification to the firm's inbox (config summit.enquiries.inbox) for an accepted enquiry.
 * Queued after the enquiry row is committed. The queued payload holds only the model's ID
 * (SerializesModels); the subject carries the reference and matter code, never personal data.
 */
#[Tries(5)]
#[DeleteWhenMissingModels]
final class EnquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry) {}

    /** "SLC-261109-7KQ2MX · S04" (or just the reference when no matter type was chosen). */
    public static function subjectFor(Enquiry $enquiry): string
    {
        return $enquiry->reference.($enquiry->matter_type !== null ? ' · '.$enquiry->matter_type : '');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: self::subjectFor($this->enquiry),
            replyTo: $this->enquiry->email !== null ? [new Address($this->enquiry->email)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.enquiry-received',
            with: ['matter' => $this->matterLabel()],
        );
    }

    /** @return list<int> seconds between delivery attempts */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    /** "S04 Corporate and Commercial Law": the approved English service title, when available. */
    private function matterLabel(): string
    {
        $code = $this->enquiry->matter_type;
        if ($code === null) {
            return '';
        }
        try {
            $title = content()->service($code, 'en')['title'] ?? null;
        } catch (Throwable) {
            $title = null;
        }

        return is_string($title) && $title !== '' ? $code.' '.$title : $code;
    }
}
