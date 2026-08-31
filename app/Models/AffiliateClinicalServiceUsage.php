<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClinicalServiceChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateClinicalServiceUsage extends Model
{
    public const STATUS_CONSUMED = 'CONSUMED';

    public const STATUS_REVERSED = 'REVERSED';

    protected $table = 'affiliate_clinical_service_usages';

    protected $fillable = [
        'telemedicine_patient_id',
        'nro_identificacion',
        'plan_id',
        'affiliation_id',
        'affiliation_corporate_id',
        'benefit_id',
        'channel',
        'telemedicine_service_list_id',
        'telemedicine_case_id',
        'telemedicine_consultation_patient_id',
        'operation_coordination_service_id',
        'status',
        'is_override',
        'override_challenge_id',
        'override_reason',
        'window_starts_at',
        'window_ends_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ClinicalServiceChannel::class,
            'is_override' => 'boolean',
            'window_starts_at' => 'datetime',
            'window_ends_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class, 'telemedicine_consultation_patient_id');
    }

    public function overrideChallenge(): BelongsTo
    {
        return $this->belongsTo(ClinicalServiceOverrideChallenge::class, 'override_challenge_id');
    }

    public function isConsumed(): bool
    {
        return $this->status === self::STATUS_CONSUMED;
    }
}
