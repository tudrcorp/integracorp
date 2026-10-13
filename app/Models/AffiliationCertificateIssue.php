<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un certificado emitido por el «Generador de Certificado», con la clave que
 * imprime el QR para la verificación pública.
 */
class AffiliationCertificateIssue extends Model
{
    /** @use HasFactory<\Database\Factories\AffiliationCertificateIssueFactory> */
    use HasFactory;

    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';

    public const TYPE_CORPORATE = 'CORPORATIVA';

    /** El PDF se está dibujando en cola. */
    public const PDF_PROCESSING = 'processing';

    public const PDF_READY = 'ready';

    public const PDF_FAILED = 'failed';

    protected $fillable = [
        'verification_key',
        'affiliation_type',
        'affiliation_id',
        'affiliation_code',
        'plan_name',
        'valid_from',
        'valid_until',
        'paid_until',
        'current_period_paid',
        'affiliates_count',
        'carnets_count',
        'carnet_affiliate_ids',
        'pdf_status',
        'pdf_path',
        'pdf_error',
        'pdf_generated_at',
        'pdf_batch_id',
        'pdf_chunks_total',
        'pdf_chunks_done',
        'pdf_pages',
        'pdf_progress_at',
        'issued_by',
        'issued_by_name',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'paid_until' => 'date',
            'current_period_paid' => 'boolean',
            'affiliates_count' => 'integer',
            'carnets_count' => 'integer',
            'carnet_affiliate_ids' => 'array',
            'pdf_generated_at' => 'datetime',
            'pdf_chunks_total' => 'integer',
            'pdf_chunks_done' => 'integer',
            'pdf_pages' => 'integer',
            'pdf_progress_at' => 'datetime',
        ];
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Si el PDF se dibuja en cola (documentos grandes). Los demás se dibujan al vuelo.
     */
    public function isQueued(): bool
    {
        return $this->pdf_status !== null;
    }

    /** Minutos sin avance tras los cuales un dibujo en cola se considera detenido. */
    public const STALLED_AFTER_MINUTES = 10;

    /**
     * Avance del dibujo por tramos, de 0 a 100. La unión final cuenta como el
     * último 5 %: con todos los tramos listos todavía falta armar el PDF.
     */
    public function progressPercent(): int
    {
        if ($this->pdf_status === self::PDF_READY) {
            return 100;
        }

        $total = (int) $this->pdf_chunks_total;

        if ($total <= 0) {
            return 0;
        }

        return (int) min(95, floor(min($total, (int) $this->pdf_chunks_done) * 95 / $total));
    }

    /**
     * En cola sin avanzar hace más de diez minutos: worker caído o cola sin
     * escuchar. El panel ofrece reintentar en lugar de esperar para siempre.
     */
    public function isStalled(): bool
    {
        if ($this->pdf_status !== self::PDF_PROCESSING) {
            return false;
        }

        $since = $this->pdf_progress_at ?? $this->created_at;

        return $since !== null && $since->lt(now()->subMinutes(self::STALLED_AFTER_MINUTES));
    }

    public function isIndividual(): bool
    {
        return $this->affiliation_type === self::TYPE_INDIVIDUAL;
    }
}
