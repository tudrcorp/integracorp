<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Traza de un registro directo de servicios médicos (sin consulta médica).
 *
 * @property array<int, array<string, mixed>> $items
 * @property list<int> $coordination_ids
 * @property list<int>|null $clinical_usage_ids
 */
class OperationDirectServiceRegistration extends Model
{
    protected $table = 'operation_direct_service_registrations';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'case_created',
        'registered_by_user_id',
        'registered_by_name',
        'service_line',
        'diagnosis',
        'request_date',
        'service_date',
        'observations',
        'prescribing_doctor_id',
        'items',
        'coordination_ids',
        'clinical_usage_ids',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'case_created' => 'boolean',
            'request_date' => 'date',
            'service_date' => 'date',
            'items' => 'array',
            'coordination_ids' => 'array',
            'clinical_usage_ids' => 'array',
        ];
    }

    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class)->withoutGlobalScopes();
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function prescribingDoctor(): BelongsTo
    {
        return $this->belongsTo(TelemedicineDoctor::class, 'prescribing_doctor_id');
    }

    public function coordinationServices(): HasMany
    {
        return $this->hasMany(OperationCoordinationService::class, 'direct_service_registration_id');
    }
}
