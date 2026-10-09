<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliationRenovationHistory extends Model
{
    protected $table = 'affiliation_renovation_histories';

    protected $fillable = [
        'affiliation_id',
        'affiliate_id',
        'source_renovation_id',
        'accepted_at',
        'accepted_by',
        'accepted_by_user_id',
        'previous_effective_date',
        'new_effective_date',
        'date_renewal',
        'remaining_days_at_accept',
        'status_at_accept',
        'is_early_acceptance',
        'days_before_renewal_at_accept',
        'early_acceptance_reason',
        'code_affiliation',
        'agent_id',
        'code_agency',
        'owner_code',
        'owner_agent',
        'plan_id',
        'coverage_id',
        'age_range_id',
        'birth_date',
        'age',
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
            'accepted_at' => 'datetime',
            'date_renewal' => 'date',
            'birth_date' => 'date',
            'age' => 'integer',
            'is_negotiation_candidate' => 'boolean',
            'is_early_acceptance' => 'boolean',
            'days_before_renewal_at_accept' => 'integer',
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

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
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
}
