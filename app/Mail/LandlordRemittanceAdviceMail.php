<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandlordRemittanceAdviceMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{payout_id:int, amount:string, property:string, period:string, paid_at:string, mpesa_txn:?string, phone:?string}  $advice
     */
    public function __construct(
        public string $landlordName,
        public array $advice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Remittance advice — payout #:id', ['id' => $this->advice['payout_id'] ?? '']),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.landlord-remittance-advice',
        );
    }
}
