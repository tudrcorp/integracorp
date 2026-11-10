<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Falla de medicamento: un medicamento recetado que el inventario Diagnomóvil no pudo
 * cubrir y que la empresa debe adquirir.
 */
class OperationMedicationFailure extends Model
{
    /** @use HasFactory<\Database\Factories\OperationMedicationFailureFactory> */
    use HasFactory;

    public const STATUS_PENDING_PURCHASE = 'PENDIENTE_DE_COMPRA';

    public const STATUS_ACQUIRED = 'ADQUIRIDO';

    public const STATUS_DISCARDED = 'DESCARTADO';

    public const REASON_NOT_IN_INVENTORY = 'NO_ESTA_EN_INVENTARIO';

    public const REASON_DOES_NOT_MATCH = 'NO_CORRESPONDE';

    public const REASON_INSUFFICIENT_STOCK = 'SIN_EXISTENCIA';

    protected $table = 'operation_medication_failures';

    protected $fillable = [
        'telemedicine_patient_medication_id',
        'operation_coordination_service_id',
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'medicine_name',
        'indications',
        'patient_name',
        'case_code',
        'reason',
        'notes',
        'status',
        'reported_by_user_id',
        'reported_by_name',
        'resolved_at',
        'resolved_by_name',
        'resolution_notes',
        'operation_inventory_product_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function reasonOptions(): array
    {
        return [
            self::REASON_NOT_IN_INVENTORY => 'No está en el inventario',
            self::REASON_DOES_NOT_MATCH => 'Lo encontrado no corresponde al medicamento recetado',
            self::REASON_INSUFFICIENT_STOCK => 'No hay existencia suficiente',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING_PURCHASE => 'Pendiente de compra',
            self::STATUS_ACQUIRED => 'Adquirido',
            self::STATUS_DISCARDED => 'Descartado',
        ];
    }

    public static function reasonLabel(?string $reason): string
    {
        return self::reasonOptions()[(string) $reason] ?? (filled($reason) ? (string) $reason : '—');
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[(string) $status] ?? (filled($status) ? (string) $status : '—');
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING_PURCHASE => 'warning',
            self::STATUS_ACQUIRED => 'success',
            self::STATUS_DISCARDED => 'gray',
            default => 'gray',
        };
    }

    public function isPendingPurchase(): bool
    {
        return $this->status === self::STATUS_PENDING_PURCHASE;
    }

    /**
     * @return BelongsTo<TelemedicinePatientMedications, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatientMedications::class, 'telemedicine_patient_medication_id');
    }

    /**
     * @return BelongsTo<OperationCoordinationService, $this>
     */
    public function operationCoordinationService(): BelongsTo
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    /**
     * @return BelongsTo<TelemedicineCase, $this>
     */
    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    /**
     * @return BelongsTo<OperationInventoryProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryProduct::class, 'operation_inventory_product_id');
    }
}
