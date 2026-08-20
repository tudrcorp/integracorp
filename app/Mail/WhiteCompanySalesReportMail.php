<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * No implementa ShouldQueue a propósito: siempre se envía desde
 * SendWhiteCompanySalesReportJob, que ya corre en la cola de documentos.
 *
 * Con ShouldQueue, `Mail::send()` volvería a encolarlo en la cola `default`
 * (ver Mailer::sendMailable) y el envío dependería de un segundo worker.
 */
class WhiteCompanySalesReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /*
     * Las fechas se llaman `fromDate` / `toDate` y no `from` / `to` porque
     * Mailable ya declara `public $from = []` y `public $to = []` sin tipo para
     * los remitentes y destinatarios. Redeclararlas tipadas es un fatal de PHP
     * en tiempo de carga de la clase. Las claves que llegan a la vista siguen
     * siendo `from` y `to`.
     */

    /**
     * @param  array{sale_price: float, neta_tdg: float, neta_partner: float, affiliates: int}  $totals
     */
    public function __construct(
        public string $companyName,
        public string $fromDate,
        public string $toDate,
        public array $totals,
        public int $rowsCount,
        public string $securityKey,
        public string $attachmentPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Estado de cuenta y conciliación de afiliaciones — '.$this->fromDate.' al '.$this->toDate,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.whiteCompanySalesReport',
            with: [
                'companyName' => $this->companyName,
                'from' => $this->fromDate,
                'to' => $this->toDate,
                'totals' => $this->totals,
                'rowsCount' => $this->rowsCount,
                'securityKey' => $this->securityKey,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! is_file($this->attachmentPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->attachmentPath)->as(basename($this->attachmentPath)),
        ];
    }
}
