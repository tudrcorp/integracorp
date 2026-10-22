<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Punto de restauración de una unificación de grupo familiar individual.
 *
 * Ciclo: APPLIED → ROLLED_BACK. Las fotos `snapshot_before` / `snapshot_after`
 * guardan, por tabla e id, solo las columnas que la unificación modifica.
 *
 * @see \App\Support\Affiliations\MergeIndividualAffiliationsService
 * @see \App\Support\Affiliations\FamilyMergeRollback
 */
class AffiliationFamilyMerge extends Model
{
    public const STATUS_APPLIED = 'APPLIED';

    public const STATUS_ROLLED_BACK = 'ROLLED_BACK';

    protected $table = 'affiliation_family_merges';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'target_affiliation_id',
        'target_code',
        'source_affiliation_ids',
        'source_codes',
        'titular_affiliate_id',
        'reason',
        'status',
        'snapshot_before',
        'snapshot_after',
        'summary',
        'merged_by',
        'merged_by_name',
        'merged_at',
        'rolled_back_by',
        'rolled_back_by_name',
        'rolled_back_at',
        'rollback_note',
        'rollback_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_affiliation_ids' => 'array',
            'source_codes' => 'array',
            'snapshot_before' => 'array',
            'snapshot_after' => 'array',
            'summary' => 'array',
            'rollback_summary' => 'array',
            'merged_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Affiliation, $this>
     */
    public function targetAffiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class, 'target_affiliation_id');
    }

    /**
     * Unificaciones aplicadas en las que participó la afiliación (como destino o como origen).
     *
     * @param  Builder<AffiliationFamilyMerge>  $query
     * @return Builder<AffiliationFamilyMerge>
     */
    public function scopeAppliedInvolving(Builder $query, int $affiliationId): Builder
    {
        return $query
            ->where('status', self::STATUS_APPLIED)
            ->where(static function (Builder $inner) use ($affiliationId): void {
                $inner->where('target_affiliation_id', $affiliationId)
                    ->orWhereJsonContains('source_affiliation_ids', $affiliationId);
            });
    }

    /**
     * @return list<int>
     */
    public function involvedAffiliationIds(): array
    {
        return array_values(array_unique([
            (int) $this->target_affiliation_id,
            ...array_map(static fn (mixed $id): int => (int) $id, (array) $this->source_affiliation_ids),
        ]));
    }
}
