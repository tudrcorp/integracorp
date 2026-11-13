<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OperationNonCoveredMedicationAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aviso a los analistas TDG de Operaciones: el médico recetó medicamentos NO
 * CUBIERTOS a un afiliado con Plan Especial de 30K, 40K o 50K. Uno por
 * coordinación de medicamentos. Ver {@see \App\Support\Operations\NonCoveredMedicationAlerts}.
 */
class OperationNonCoveredMedicationAlert extends Model
{
    /** @use HasFactory<OperationNonCoveredMedicationAlertFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'PENDIENTE';

    public const STATUS_MANAGED = 'GESTIONADA';

    protected $table = 'operation_non_covered_medication_alerts';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coverage_amount' => 'decimal:2',
            'medication_ids' => 'array',
            'managed_medication_ids' => 'array',
            'managed_at' => 'datetime',
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
     * @return HasMany<OperationNonCoveredMedicationAlertDismissal, $this>
     */
    public function dismissals(): HasMany
    {
        return $this->hasMany(OperationNonCoveredMedicationAlertDismissal::class);
    }

    /**
     * @return list<int>
     */
    public function medicationIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) ($this->medication_ids ?? []))));
    }
}
