<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beneficio o servicio de un plan de capitado: del catálogo (`benefit_id`) o texto libre.
 */
class CapitationPlanBenefit extends Model
{
    protected $fillable = [
        'capitation_plan_id',
        'benefit_id',
        'name',
        'note',
        'sort',
    ];

    /**
     * @return BelongsTo<CapitationPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(CapitationPlan::class, 'capitation_plan_id');
    }

    /**
     * @return BelongsTo<Benefit, $this>
     */
    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }
}
