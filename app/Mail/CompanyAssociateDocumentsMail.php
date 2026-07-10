<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CompanyAssociate;
use App\Support\Companies\CompanyAssociateCarnetGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyAssociateDocumentsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $attachmentPaths
     * @param  array<string, string>  $ccRecipients
     */
    public function __construct(
        public CompanyAssociate $associate,
        public string $recipientEmail,
        public string $recipientName,
        public array $attachmentPaths,
        public string $subjectLine,
        public array $ccRecipients = [],
    ) {}

    public function envelope(): Envelope
    {
        $cc = [];

        foreach ($this->ccRecipients as $email => $name) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $cc[] = new Address($email, $name);
            }
        }

        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, $this->recipientName)],
            cc: $cc,
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.company-associate-documents',
            with: [
                'associate' => $this->associate,
                'recipientName' => $this->recipientName,
                'validity' => CompanyAssociateCarnetGenerator::cardValidityDates($this->associate),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->attachmentPaths as $path) {
            if (is_file($path)) {
                $attachments[] = Attachment::fromPath($path)->as(basename($path));
            }
        }

        return $attachments;
    }
}
