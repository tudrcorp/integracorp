<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OperationInventoryLowStockMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{
     *     threshold: int,
     *     generated_at: string,
     *     products: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         category: string|null,
     *         unit: string|null,
     *         total_existence: int,
     *         warehouses: list<array{name: string, existence: int}>
     *     }>
     * }  $report
     */
    public function __construct(
        public array $report,
        public string $recipientEmail,
        public bool $immediate = false,
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->report['products']);
        $threshold = $this->report['threshold'];
        $prefix = $this->immediate ? 'Alerta inmediata de stock bajo' : 'Alerta de stock bajo';

        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            to: [new Address($this->recipientEmail, 'Inventario INTEGRACORP')],
            subject: "{$prefix} ({$count}) · umbral ≤ {$threshold} · INTEGRACORP",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.operation-inventory-low-stock',
            with: [
                'report' => $this->report,
                'generatedAt' => $this->report['generated_at'],
                'threshold' => $this->report['threshold'],
                'products' => $this->report['products'],
                'immediate' => $this->immediate,
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
