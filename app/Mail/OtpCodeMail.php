<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The sign-in code, by email.
 *
 * Email because it costs nothing: WhatsApp charges per authentication
 * template and SMS in India needs DLT registration first, neither of which is
 * worth it for one owner signing in on a handful of devices.
 */
class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $expiresInMinutes,
        public ?string $name = null,
    ) {}

    public function envelope(): Envelope
    {
        // The code is in the subject as well as the body: most phones show
        // enough of the subject in the notification to read it without
        // opening the mail at all.
        return new Envelope(
            subject: $this->code.' is your Invoice Generator sign-in code',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp');
    }
}
