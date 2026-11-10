<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial de asignaciones directas de plan de capitado. Solo se agregan filas.
 */
class AffiliateCapitationAssignmentLog extends Model
{
    public const ACTION_ASSIGN = 'ASIGNACION';

    public const ACTION_REMOVE = 'RETIRO';

    public const ACTION_REVERT = 'REVERSION';

    public const UPDATED_AT = null;

    protected $table = 'affiliate_capitation_assignment_logs';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'action',
        'previous_capitation_plan_id',
        'new_capitation_plan_id',
        'previous_reason',
        'reason',
        'batch_uuid',
        'reverts_log_id',
        'reverted_at',
        'reverted_by_name',
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
            'previous_capitation_plan_id' => 'integer',
            'new_capitation_plan_id' => 'integer',
            'reverts_log_id' => 'integer',
            'reverted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
