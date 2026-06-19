<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationAccountsReceivable extends Model
{
    public const STATUS_PENDING_TDG = 'PENDIENTE_GESTION_TDG';

    public const STATUS_QUOTE_ASSIGNED = 'COTIZACION_ASIGNADA';

    public const STATUS_COMPLETED = 'GESTION_COMPLETADA';

    protected $table = 'operation_accounts_receivables';

    protected $fillable = [
        'operation_coordination_service_id',
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'operation_quote_generator_id',
        'operation_service_order_id',
        'quote_number',
        'service_order_number',
        'quote_amount_usd',
        'quote_amount_ves',
        'bcv_rate',
        'reassignment_reason',
        'reassignment_supplier_id',
        'reassignment_supplier_name',
        'reassigned_by_user_id',
        'reassigned_by_analyst_name',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quote_amount_usd' => 'decimal:2',
            'quote_amount_ves' => 'decimal:2',
            'bcv_rate' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<OperationCoordinationService, $this>
     */
    public function operationCoordinationService(): BelongsTo
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    /**
     * @return BelongsTo<TelemedicinePatient, $this>
     */
    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    /**
     * @return BelongsTo<TelemedicineCase, $this>
     */
    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    /**
     * @return BelongsTo<OperationQuoteGenerator, $this>
     */
    public function operationQuoteGenerator(): BelongsTo
    {
        return $this->belongsTo(OperationQuoteGenerator::class);
    }

    /**
     * @return BelongsTo<OperationServiceOrder, $this>
     */
    public function operationServiceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function reassignmentSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'reassignment_supplier_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reassignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_by_user_id');
    }
}
