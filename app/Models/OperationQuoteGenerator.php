<?php

namespace App\Models;

use App\Observers\OperationQuoteGeneratorFlowTraceObserver;
use App\Support\Telemedicine\Concerns\HidesDeletedTelemedicineCaseTraces;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([OperationQuoteGeneratorFlowTraceObserver::class])]
class OperationQuoteGenerator extends Model
{
    use HidesDeletedTelemedicineCaseTraces;

    public const STATUS_PENDING = 'PENDIENTE POR APROBAR';

    public const STATUS_APPROVED = 'APROBADA';

    public const STATUS_REJECTED = 'RECHAZADA';

    public const STATUS_PRIVATE_CARE = 'ATENCION PARTICULAR';

    protected $table = 'operation_quote_generators';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'operation_coordination_service_id',
        'operation_service_order_id',
        'type_service',
        'is_cash',
        'is_courtesy',
        'generates_invoice',
        'invoice_payer_type',
        'invoice_payer_name',
        'invoice_payer_identification',
        'invoice_payer_address',
        'operation_medication_invoice_id',
        'supplier_id',
        'supplier_address',
        'observations',
        'status',
        'items',
        'costo_dolares',
        'costo_bolivares',
        'porcentaje_ganancia',
        'subtotal',
        'total',
        'quote_pdf_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'items' => 'array',
        'is_cash' => 'boolean',
        'is_courtesy' => 'boolean',
        'generates_invoice' => 'boolean',
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineCase()
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function operationCoordinationService()
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function operationServiceOrder()
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    /**
     * Factura al paciente emitida al aprobar la cotización (si el analista indicó que genera factura).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<OperationMedicationInvoice, $this>
     */
    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OperationMedicationInvoice::class, 'operation_medication_invoice_id');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente por aprobar',
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_REJECTED => 'Rechazada',
            self::STATUS_PRIVATE_CARE => 'Atención Particular',
        ];
    }

    public static function isTerminalStatus(?string $status): bool
    {
        $normalized = mb_strtoupper(trim((string) $status));

        return in_array($normalized, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_PRIVATE_CARE,
        ], true);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
