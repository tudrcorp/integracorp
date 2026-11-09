<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Affiliations\Certificates\CertificateDeliveryParts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un envío por correo de un certificado del generador.
 */
class AffiliationCertificateDelivery extends Model
{
    /** El certificado completo con todos sus carnets. */
    public const CONTENT_FULL = 'full';

    /** Solo los carnets elegidos, en un PDF aparte. */
    public const CONTENT_CARNETS = 'carnets';

    /** Solo el certificado (portada, beneficios y relación), sin carnets, en un PDF aparte. */
    public const CONTENT_CERTIFICATE = 'certificate';

    /** Las partes elegidas en `parts`, en un PDF aparte (no el documento completo de la emisión). */
    public const CONTENT_CUSTOM = 'custom';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const METHOD_ATTACHMENT = 'attachment';

    public const METHOD_LINK = 'link';

    protected $fillable = [
        'affiliation_certificate_issue_id',
        'content',
        'parts',
        'carnet_affiliate_ids',
        'recipients',
        'cc_recipients',
        'bcc_recipients',
        'delivered_to',
        'copies_sent_at',
        'message',
        'status',
        'delivery_method',
        'file_path',
        'file_size',
        'link_expires_at',
        'error',
        'sent_at',
        'sent_by',
        'sent_by_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parts' => 'array',
            'carnet_affiliate_ids' => 'array',
            'recipients' => 'array',
            'cc_recipients' => 'array',
            'bcc_recipients' => 'array',
            'delivered_to' => 'array',
            'copies_sent_at' => 'datetime',
            'file_size' => 'integer',
            'link_expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AffiliationCertificateIssue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(AffiliationCertificateIssue::class, 'affiliation_certificate_issue_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Partes del documento que lleva el envío. Los envíos anteriores a la
     * elección por partes se leen desde `content`.
     *
     * @return list<string>
     */
    public function documentParts(): array
    {
        if (is_array($this->parts) && $this->parts !== []) {
            return array_values(array_intersect(CertificateDeliveryParts::ORDER, $this->parts));
        }

        return match ($this->content) {
            self::CONTENT_CARNETS => [CertificateDeliveryParts::CARNETS],
            self::CONTENT_CERTIFICATE => [CertificateDeliveryParts::CERTIFICATE, CertificateDeliveryParts::AFFILIATES],
            default => CertificateDeliveryParts::ORDER,
        };
    }

    public function includesCarnets(): bool
    {
        return in_array(CertificateDeliveryParts::CARNETS, $this->documentParts(), true);
    }

    public function isCarnetsOnly(): bool
    {
        return $this->documentParts() === [CertificateDeliveryParts::CARNETS];
    }

    public function isCertificateOnly(): bool
    {
        return ! $this->includesCarnets();
    }

    /** Si el envío lleva un PDF propio (no el documento completo de la emisión). */
    public function usesOwnFile(): bool
    {
        return $this->content !== self::CONTENT_FULL;
    }

    public function linkIsActive(): bool
    {
        return $this->delivery_method === self::METHOD_LINK
            && $this->link_expires_at !== null
            && $this->link_expires_at->isFuture();
    }
}
