<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantPortalCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $email,
        public string $plainPassword,
        public string $loginUrl,
        public string $tenantHomeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your tenant portal login'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tenant-portal-credentials',
        );
    }
}
