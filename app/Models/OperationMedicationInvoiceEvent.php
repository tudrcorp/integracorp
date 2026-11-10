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
