<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un analista cerró el aviso con «OK»: no se le vuelve a mostrar como modal.
 */
class OperationNonCoveredMedicationAlertDismissal extends Model
{
    public $timestamps = false;

    protected $table = 'operation_non_covered_medication_alert_dismissals';

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
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperationNonCoveredMedicationAlert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(OperationNonCoveredMedicationAlert::class, 'operation_non_covered_medication_alert_id');
    }
}
