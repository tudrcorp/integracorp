<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpSelfAuthorizationNoticeMail extends Mailable
{
    /**
     * @param  array{operation: string, where: string, user: string, subject: string, reason: string, at: string}  $notice
     */
    public function __construct(public array $notice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Clave OTP autoautorizada · '.$this->notice['operation'],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mails.otp-self-authorization-notice', with: ['notice' => $this->notice]);
    }
}
