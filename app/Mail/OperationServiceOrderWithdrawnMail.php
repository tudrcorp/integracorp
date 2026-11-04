<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\OperationServiceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al proveedor de que una orden de servicio ya no está a su cargo (volvió al
 * proveedor original al restaurarlo).
 */
class OperationServiceOrderWithdrawnMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OperationServiceOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Orden de servicio '.$this->order->order_number.' retirada — Tu Doctor en Casa',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.operation-service-order-withdrawn',
            with: [
                'orderNumber' => $this->order->order_number,
            ],
        );
    }
}
