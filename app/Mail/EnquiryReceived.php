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
 * Notification to the on-duty list (App\Requests\Notifier::recipients(): the Notifications setting, else
 * config summit.enquiries.inbox) for an accepted enquiry. Queued after the enquiry row is committed. The queued
 * payload holds only the model's ID (SerializesModels); the subject carries the reference and matter code, never
 * personal data. The body links to the request in the panel; it lists the visitor's details only when $details is
 * true (App\Requests\Notifier::includeDetails(): the setting is on and the mailer is not `log`), so personal data
 * never reaches a log.
 */
#[Tries(5)]
#[DeleteWhenMissingModels]
final class EnquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry, public bool $details = false) {}

    /** "SLC-261109-7KQ2MX · S04" (or just the reference when no matter type was chosen). */
    public static function subjectFor(Enquiry $enquiry): string
    {
        return $enquiry->reference.($enquiry->matter_type !== null ? ' · '.$enquiry->matter_type : '');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: self::subjectFor($this->enquiry),
            replyTo: $this->details && $this->enquiry->email !== null ? [new Address($this->enquiry->email)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.enquiry-received',
            with: ['matter' => $this->matterLabel(), 'details' => $this->details, 'url' => self::panelUrl($this->enquiry)],
        );
    }

    /** The request in the admin panel (staff sign in to read it). */
    public static function panelUrl(Enquiry $enquiry): string
    {
        return (string) rescue(fn () => route('filament.admin.resources.requests.view', ['record' => $enquiry]), url('/admin'), report: false);
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
