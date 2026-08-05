<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentRescheduleMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public string $orderLabel,
        public array $details,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cambio de fecha de cita — '.$this->orderLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.appointment-reschedule',
            with: [
                'orderLabel' => $this->orderLabel,
                'details' => $this->details,
            ],
        );
    }
}
