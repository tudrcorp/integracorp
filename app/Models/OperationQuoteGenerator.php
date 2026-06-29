<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationQuoteGenerator extends Model
{
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
        return $this->belongsTo(Supplier::class);
    }

    public function operationServiceOrder()
    {
        return $this->belongsTo(OperationServiceOrder::class);
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
