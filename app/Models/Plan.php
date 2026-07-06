<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'business_unit_id',
        'code',
        'description',
        'status',
        'created_by',
        'type',
        'agencies',
    ];

    /**
     * Get all of the comments for the Plan
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class, 'plan_id', 'id');
    }

    /**
     * The servicios that belong to the User
     */
    public function agencyPlans(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'agency_plans')
            ->using(AgencyPlan::class)
            ->withPivot(['description']);
    }

    /**
     * The servicios that belong to the User
     */
    public function benefitPlans(): BelongsToMany
    {
        return $this->belongsToMany(Benefit::class, 'benefit_plans')
            ->using(BenefitPlan::class)
            ->withPivot(['description']);
    }

    /**
     * The servicios that belong to the User
     */
    public function coveragePlans(): BelongsToMany
    {
        return $this->belongsToMany(Coverage::class, 'coverage_plans')
            ->using(CoveragePlan::class)
            ->withPivot(['price']);
    }

    /**
     * The servicios that belong to the User
     */
    public function feePlans(): BelongsToMany
    {
        return $this->belongsToMany(Fee::class, 'fee_plans')
            ->using(FeePlan::class)
            ->withPivot(['range', 'price']);
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class, 'plan_id', 'id');
    }

    public function businessLine()
    {
        return $this->belongsTo(BusinessLine::class, 'business_line_id', 'id');
    }

    public function businessUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }

    public function ageRanges(): HasMany
    {
        return $this->hasMany(AgeRange::class, 'plan_id', 'id');
    }

    public function affiliationCorporates(): BelongsToMany
    {
        return $this->belongsToMany(AffiliationCorporate::class);
    }
}
