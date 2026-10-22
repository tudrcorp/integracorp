<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AffiliationDateInconsistencyKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inconsistencia de fechas detectada en una afiliación.
 *
 * Estados: ABIERTA → REPARADA (con punto de restauración) | NO_ES_INCONSISTENCIA (con nota) |
 * RESUELTA (dejó de detectarse sin repararla aquí). Reparar se revierte y «no es una
 * inconsistencia» se reabre, siempre con nota.
 *
 * @see \App\Support\Affiliations\DateInconsistencies\DateInconsistencyDetector
 * @see \App\Support\Affiliations\DateInconsistencies\DateInconsistencyResolver
 */
class AffiliationDateInconsistency extends Model
{
    public const STATUS_OPEN = 'ABIERTA';

    public const STATUS_REPAIRED = 'REPARADA';

    public const STATUS_DISMISSED = 'NO_ES_INCONSISTENCIA';

    public const STATUS_RESOLVED = 'RESUELTA';

    protected $table = 'affiliation_date_inconsistencies';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliation_type',
        'affiliation_id',
        'affiliation_code',
        'kind',
        'severity',
        'status',
        'details',
        'fingerprint',
        'dismissed_fingerprint',
        'correction_id',
        'first_detected_at',
        'last_detected_at',
        'resolved_at',
        'resolved_by',
        'resolved_by_name',
        'resolution_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Abierta',
            self::STATUS_REPAIRED => 'Reparada',
            self::STATUS_DISMISSED => 'No es una inconsistencia',
            self::STATUS_RESOLVED => 'Resuelta',
        ];
    }

    public function kindEnum(): ?AffiliationDateInconsistencyKind
    {
        return AffiliationDateInconsistencyKind::tryFrom((string) $this->kind);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? (string) $this->status;
    }

    /**
     * @return HasMany<AffiliationDateInconsistencyEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AffiliationDateInconsistencyEvent::class)->orderByDesc('id');
    }

    /**
     * @return BelongsTo<AffiliationDateCorrection, $this>
     */
    public function correction(): BelongsTo
    {
        return $this->belongsTo(AffiliationDateCorrection::class, 'correction_id');
    }
}
