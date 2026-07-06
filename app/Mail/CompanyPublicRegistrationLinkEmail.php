<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyPublicRegistrationLinkEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{link: string, company_name: string, sent_at: \Illuminate\Support\Carbon}  $content
     */
    public function __construct(public array $content) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Enlace de registro de asociados — '.$this->content['company_name'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.company-public-registration-link',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}
