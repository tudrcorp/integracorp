<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paso en la vida de una inconsistencia de fechas (bitácora inmutable).
 */
class AffiliationDateInconsistencyEvent extends Model
{
    public const DETECTED = 'DETECTADA';

    public const REPAIRED = 'REPARADA';

    public const REVERTED = 'REVERTIDA';

    public const DISMISSED = 'NO_ES_INCONSISTENCIA';

    public const REOPENED = 'REABIERTA';

    public const RESOLVED = 'RESUELTA_SOLA';

    protected $table = 'affiliation_date_inconsistency_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliation_date_inconsistency_id',
        'action',
        'note',
        'payload',
        'user_id',
        'user_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            self::DETECTED => 'Detectada',
            self::REPAIRED => 'Reparada',
            self::REVERTED => 'Reparación revertida',
            self::DISMISSED => 'Marcada como no inconsistencia',
            self::REOPENED => 'Reabierta',
            self::RESOLVED => 'Dejó de detectarse',
        ];
    }

    /**
     * @return BelongsTo<AffiliationDateInconsistency, $this>
     */
    public function inconsistency(): BelongsTo
    {
        return $this->belongsTo(AffiliationDateInconsistency::class, 'affiliation_date_inconsistency_id');
    }
}
