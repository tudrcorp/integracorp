<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Corrección de la fecha de inicio del ciclo de cobro de una afiliación (individual o
 * corporativa), con su punto de restauración. Ciclo: APPLIED → ROLLED_BACK.
 *
 * @see \App\Support\Affiliations\DateCorrection\AffiliationDateCorrector
 * @see \App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionRollback
 */
class AffiliationDateCorrection extends Model
{
    public const STATUS_APPLIED = 'APPLIED';

    public const STATUS_ROLLED_BACK = 'ROLLED_BACK';

    protected $table = 'affiliation_date_corrections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'affiliation_type',
        'affiliation_id',
        'affiliation_code',
        'status',
        'scope',
        'activated_at_before',
        'effective_date_before',
        'new_date',
        'reason',
        'snapshot_before',
        'snapshot_after',
        'summary',
        'applied_by',
        'applied_by_name',
        'applied_at',
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
            'snapshot_before' => 'array',
            'snapshot_after' => 'array',
            'summary' => 'array',
            'rollback_summary' => 'array',
            'applied_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }
}
