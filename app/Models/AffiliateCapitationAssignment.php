<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan de capitado asignado directamente a un afiliado. Una fila por afiliado (`tipo:id`).
 */
class AffiliateCapitationAssignment extends Model
{
    protected $table = 'affiliate_capitation_assignments';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'capitation_plan_id',
        'reason',
        'created_by_user_id',
        'created_by_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'capitation_plan_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CapitationPlan, $this>
     */
    public function capitationPlan(): BelongsTo
    {
        return $this->belongsTo(CapitationPlan::class)->withTrashed();
    }
}
