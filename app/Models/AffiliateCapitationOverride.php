<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Monto de capitación negociado para un afiliado. Reemplaza al del plan de capitado solo
 * mientras el afiliado esté activo y ese plan le aplique (ver AffiliateCapitationCost).
 */
class AffiliateCapitationOverride extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'amount_usd',
        'reason',
        'capitation_plan_id',
        'plan_amount_usd',
        'created_by_user_id',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'subject_id' => 'integer',
            'amount_usd' => 'decimal:2',
            'plan_amount_usd' => 'decimal:2',
            'capitation_plan_id' => 'integer',
        ];
    }
}
