<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmtpTestMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?string $fromEmail = null,
        public ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: 'NCCAA HRMS — SMTP Test Connection Successful',
        );

        if ($this->fromEmail) {
            $envelope->from = new Address($this->fromEmail, $this->fromName ?? 'NCCAA HRMS');
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<h1>NCCAA HRMS SMTP Test</h1><p>This is a test email sent from NCCAA HRMS to verify your SMTP mail configuration.</p><p>Sent at: '.now()->toDateTimeString().'</p>',
        );
    }
}
