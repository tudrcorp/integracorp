<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\MedicationInvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Factura de medicamentos adjunta. Se envía desde {@see \App\Jobs\SendMedicationInvoicePdfJob}, ya en cola.
 */
class MedicationInvoicePdfMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $invoiceNumber,
        public string $patientName,
        public string $totalLabel,
        public string $statusLabel,
        public string $storagePath,
        public string $attachmentName,
        public ?string $senderName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Factura '.$this->invoiceNumber.' — Tu Doctor en Casa');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.medication-invoice-pdf',
            with: [
                'invoiceNumber' => $this->invoiceNumber,
                'patientName' => $this->patientName,
                'totalLabel' => $this->totalLabel,
                'statusLabel' => $this->statusLabel,
                'senderName' => $this->senderName,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(MedicationInvoicePdfService::DISK, $this->storagePath)
                ->as($this->attachmentName)
                ->withMime('application/pdf'),
        ];
    }
}
