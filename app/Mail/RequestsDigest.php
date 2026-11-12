<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Tries;

/**
 * The daily digest at the digest time, Dubai (docs/briefs/design-2.1-and-admin-cms.md §10.5): counts and the references
 * still waiting, with a link to the inbox. No names, no contact details, no summaries.
 */
#[Tries(5)]
final class RequestsDigest extends Mailable implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, mixed>  $figures  App\Requests\Digest::figures() */
    public function __construct(public array $figures) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Requests digest '.$this->figures['date']);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.requests-digest', with: ['f' => $this->figures]);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }
}
