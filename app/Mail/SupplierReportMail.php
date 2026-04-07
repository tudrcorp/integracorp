<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\SupplierReportPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reporte de proveedores — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.suppliers-report',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => SupplierReportPdfService::make()->output(),
                SupplierReportPdfService::FILENAME
            )->withMime('application/pdf'),
        ];
    }
}
