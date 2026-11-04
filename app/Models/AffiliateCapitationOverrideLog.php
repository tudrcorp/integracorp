<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historial de ajustes de capitación: cada monto negociado, cada retiro y cada reversión,
 * con razón. Una fila solo cambia para marcarse como revertida.
 */
class AffiliateCapitationOverrideLog extends Model
{
    public const ACTION_SET = 'AJUSTE';

    public const ACTION_REMOVE = 'RETIRO';

    public const ACTION_REVERT = 'REVERSION';

    public const UPDATED_AT = null;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'action',
        'previous_amount_usd',
        'new_amount_usd',
        'previous_reason',
        'previous_plan_amount_usd',
        'plan_amount_usd',
        'capitation_plan_id',
        'reason',
        'batch_uuid',
        'reverts_log_id',
        'reverted_at',
        'reverted_by_name',
        'created_by_user_id',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'previous_amount_usd' => 'decimal:2',
            'new_amount_usd' => 'decimal:2',
            'previous_plan_amount_usd' => 'decimal:2',
            'plan_amount_usd' => 'decimal:2',
            'capitation_plan_id' => 'integer',
            'reverts_log_id' => 'integer',
            'reverted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
