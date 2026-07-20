<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledTaskRunReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientEmail,
        public string $taskTitle,
        public string $summaryBody,
        public ?string $attachmentFilename = null,
        public ?string $attachmentRelativePath = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, 'Control INTEGRACORP')],
            subject: 'Resumen programado: '.$this->taskTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.scheduled-task-run-report',
            with: [
                'taskTitle' => $this->taskTitle,
                'summaryBody' => $this->summaryBody,
                'attachmentFilename' => $this->attachmentFilename,
                'attachmentRelativePath' => $this->attachmentRelativePath,
            ],
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
