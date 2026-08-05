<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationMedicalAppointment extends Model
{
    public const STATUS_SCHEDULED = 'SCHEDULED';

    public const STATUS_RESCHEDULED = 'RESCHEDULED';

    protected $table = 'operation_medical_appointments';

    protected $fillable = [
        'operation_service_order_id',
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'operation_coordination_service_id',
        'supplier_id',
        'supplier_external',
        'supplier_notify_email',
        'supplier_notify_phone',
        'appointment_at',
        'status',
        'previous_appointment_at',
        'last_change_reason',
        'last_changed_at',
        'last_changed_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'previous_appointment_at' => 'datetime',
            'last_changed_at' => 'datetime',
        ];
    }

    public function operationServiceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function operationCoordinationService(): BelongsTo
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
