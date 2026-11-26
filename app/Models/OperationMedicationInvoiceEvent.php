<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de una factura de medicamentos (emisión, pago, anulación). Solo se agregan filas.
 */
class OperationMedicationInvoiceEvent extends Model
{
    public const EVENT_ISSUED = 'EMITIDA';

    public const EVENT_PAID = 'PAGO_REGISTRADO';

    public const EVENT_ANNULLED = 'ANULADA';

    public const EVENT_SENT = 'PDF_ENVIADO';

    /** El analista registró o corrigió el número de factura. */
    public const EVENT_NUMBER_SET = 'NUMERO_REGISTRADO';

    /** Se completó el N.º de siniestro desde el caso (la factura se emitió sin él). */
    public const EVENT_CLAIM_SET = 'SINIESTRO_REGISTRADO';

    /** El analista cambió a nombre de quién va la factura (paciente o grupo corporativo). */
    public const EVENT_PAYER_CHANGED = 'RESPONSABLE_CAMBIADO';

    public const UPDATED_AT = null;

    protected $table = 'operation_medication_invoice_events';

    protected $fillable = [
        'operation_medication_invoice_id',
        'event',
        'description',
        'payload',
        'user_id',
        'user_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperationMedicationInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OperationMedicationInvoice::class, 'operation_medication_invoice_id');
    }
}
