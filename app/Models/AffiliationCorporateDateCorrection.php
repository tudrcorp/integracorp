<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Corrección de fecha de emisión y vigencia de una afiliación corporativa, con su
 * punto de restauración. Ciclo: APPLIED → ROLLED_BACK.
 *
 * @see \App\Support\AffiliationCorporates\DateCorrection\CorporateDateCorrector
 * @see \App\Support\AffiliationCorporates\DateCorrection\CorporateDateCorrectionRollback
 */
class AffiliationCorporateDateCorrection extends Model
{
    public const STATUS_APPLIED = 'APPLIED';

    public const STATUS_ROLLED_BACK = 'ROLLED_BACK';

    protected $table = 'affiliation_corporate_date_corrections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'affiliation_corporate_id',
        'affiliation_code',
        'status',
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

    /**
     * @return BelongsTo<AffiliationCorporate, $this>
     */
    public function affiliationCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }
}
