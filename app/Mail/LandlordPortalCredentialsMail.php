<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandlordPortalCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $landlordName,
        public string $email,
        public string $plainPassword,
        public string $loginUrl,
        public string $landlordHomeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your landlord portal login'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.landlord-portal-credentials',
        );
    }
}

