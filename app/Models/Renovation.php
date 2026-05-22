<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renovation extends Model
{
    protected $table = 'renovations';

    protected $fillable = [
        'affiliation_id',
        'date_renewal',
        'remaining_days',
        'status',
        'created_by',
        'updated_by',
        'code_affiliation',
        'agent_id',
        'code_agency',
        'owner_code',
        'owner_agent',
        'plan_id',
        'coverage_id',
        'age_range_id',
        'fee',
        'subtotal_anual',
        'subtotal_quarterly',
        'subtotal_biannual',
        'subtotal_monthly',
        'total_persons',
        'payment_frequency',
        'is_negotiation_candidate',
        'negotiation_notes',
        'previous_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'date_renewal' => 'date',
            'is_negotiation_candidate' => 'boolean',
            'fee' => 'decimal:2',
            'subtotal_anual' => 'decimal:2',
            'subtotal_quarterly' => 'decimal:2',
            'subtotal_biannual' => 'decimal:2',
            'subtotal_monthly' => 'decimal:2',
        ];
    }

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'previous_plan_id');
    }

    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class);
    }

    public function ageRange(): BelongsTo
    {
        return $this->belongsTo(AgeRange::class);
    }

    public function renovationCorporate(): BelongsTo
    {
        return $this->belongsTo(RenovationCorporate::class);
    }
}
