<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationServiceStatistic extends Model
{
    public const SOURCE_LAB = 'lab';

    public const SOURCE_MEDICATION = 'medication';

    public const SOURCE_STUDY = 'study';

    public const SOURCE_SPECIALTY = 'specialty';

    public const SOURCE_AMBULANCE = 'ambulance';

    public const SOURCE_CLINIC_ADMISSION = 'clinic_admission';

    public const SOURCE_TPA_RETAIL = 'tpa_retail';

    protected $table = 'operation_service_statistics';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'telemedicine_case_id',
        'telemedicine_consultation_patient_id',
        'operation_coordination_service_id',
        'operation_service_order_id',
        'source_type',
        'source_id',
        'started_on',
        'started_at_time',
        'service_on',
        'business_line',
        'case_code',
        'case_status',
        'service_status',
        'case_created_by',
        'case_last_touched_by',
        'plan_holder_name',
        'plan_holder_document',
        'patient_name',
        'patient_document',
        'patient_birth_date',
        'patient_relationship',
        'patient_age',
        'contractor',
        'agency_name',
        'agent_name',
        'region',
        'state',
        'city',
        'address',
        'patient_phone',
        'patient_email',
        'consultation_reason',
        'initial_diagnosis',
        'final_diagnosis',
        'service',
        'specific_service',
        'service_type',
        'coverage',
        'management_provider',
        'service_provider',
        'medical_provider',
        'farmadoc_derived',
        'farmadoc_detail',
        'negotiation_type',
        'negotiation_status',
        'net_price',
        'tdec_profit_percent',
        'quoted_amount',
        'discount_negotiation',
        'discount_percent',
        'discount_amount',
        'quote_number',
        'approval_number',
        'service_order_number',
        'invoice_number',
        'invoiced_amount',
        'invoice_issued_on',
        'incidence',
        'case_denied',
        'qc_received',
        'observations',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'service_on' => 'date',
            'patient_birth_date' => 'date',
            'invoice_issued_on' => 'date',
            'patient_age' => 'integer',
            'net_price' => 'decimal:2',
            'tdec_profit_percent' => 'decimal:2',
            'quoted_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'invoiced_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<TelemedicineCase, $this>
     */
    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    /**
     * @return BelongsTo<TelemedicineConsultationPatient, $this>
     */
    public function telemedicineConsultationPatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class);
    }

    /**
     * @return BelongsTo<OperationCoordinationService, $this>
     */
    public function operationCoordinationService(): BelongsTo
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    /**
     * @return BelongsTo<OperationServiceOrder, $this>
     */
    public function operationServiceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }
}
