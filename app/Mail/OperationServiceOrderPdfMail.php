<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperationServiceOrderPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OperationServiceOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Orden de servicio '.$this->order->order_number.' — Tu Doctor en Casa',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.operation-service-order-pdf',
            with: [
                'orderNumber' => $this->order->order_number,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => OperationServiceOrderPdfService::make($this->order)->output(),
                OperationServiceOrderPdfService::filename($this->order)
            )->withMime('application/pdf'),
        ];
    }
}
