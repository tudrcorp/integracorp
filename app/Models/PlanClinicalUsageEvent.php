<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un hecho de la traza del uso clínico de un plan. Solo se inserta.
 *
 * @see \App\Support\ClinicalEntitlements\PlanClinicalUsageTrace
 */
class PlanClinicalUsageEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'plan_clinical_usage_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'source_plan_id',
        'event',
        'description',
        'changes',
        'actor_id',
        'actor_name',
        'ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function sourcePlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'source_plan_id');
    }
}
