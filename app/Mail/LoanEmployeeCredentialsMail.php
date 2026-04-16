<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanEmployeeCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $employeeName,
        public string $role,
        public string $email,
        public string $plainPassword,
        public string $loginUrl,
        public string $loanHomeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your loan module login credentials'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.loan-employee-credentials',
        );
    }
}

