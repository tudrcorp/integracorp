<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Certificado de afiliación (o solo carnets) enviado desde el Generador de
 * Certificado: adjunto, o enlace de descarga cuando el PDF es muy pesado.
 */
class AffiliationCertificateDeliveryMail extends Mailable
{
    public function __construct(
        public int $deliveryId,
        public string $affiliationCode,
        public ?string $planName,
        public bool $carnetsOnly,
        public int $carnetsCount,
        public ?string $analystMessage,
        public ?string $attachmentPath,
        public string $attachmentName,
        public ?string $downloadUrl,
        public ?string $linkExpiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->carnetsOnly
                ? ($this->carnetsCount === 1 ? 'Carnet de afiliado' : 'Carnets de afiliado')
                : 'Certificado de afiliación').' '.$this->affiliationCode.' — Tu Doctor en Casa',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mails.affiliation-certificate-delivery');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->attachmentPath === null || ! is_file($this->attachmentPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->attachmentPath)->as($this->attachmentName)->withMime('application/pdf'),
        ];
    }
}
