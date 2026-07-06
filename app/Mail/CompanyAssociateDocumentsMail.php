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
     */
    public function __construct(
        public CompanyAssociate $associate,
        public string $recipientEmail,
        public string $recipientName,
        public array $attachmentPaths,
        public string $subjectLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, $this->recipientName)],
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
