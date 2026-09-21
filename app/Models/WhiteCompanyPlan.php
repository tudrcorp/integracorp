<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plan habilitado para una empresa aliada. Paso previo a la matriz de
 * negociación: primero se asigna el plan, después se pactan venta y neta de sus
 * tarifas en `WhiteCompanyFee`.
 */
class WhiteCompanyPlan extends Model
{
    protected $table = 'white_company_plans';

    protected $fillable = [
        'white_company_id',
        'plan_id',
        'status',
        'created_by',
    ];

    /**
     * @return BelongsTo<WhiteCompany, $this>
     */
    public function whiteCompany(): BelongsTo
    {
        return $this->belongsTo(WhiteCompany::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
