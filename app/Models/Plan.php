<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    /**
     * Get all of the comments for the Plan
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class, 'plan_id', 'id');
    }

    /**
     * The servicios that belong to the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function benefitPlans(): BelongsToMany
    {
        return $this->belongsToMany(Benefit::class, 'benefit_plans')
            ->using(BenefitPlan::class)
            ->withPivot(['description']);
    }

    /**
     * The servicios that belong to the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function coveragePlans(): BelongsToMany
    {
        return $this->belongsToMany(Coverage::class, 'coverage_plans')
            ->using(CoveragePlan::class)
            ->withPivot(['price']);
    }

    /**
     * The servicios that belong to the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function feePlans(): BelongsToMany
    {
        return $this->belongsToMany(Fee::class, 'fee_plans')
            ->using(FeePlan::class)
            ->withPivot(['range','price']);
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class, 'plan_id', 'id');
    }


    public function businessLine()
    {
        return $this->belongsTo(BusinessLine::class, 'business_line_id', 'id');
    }


    public function businessUnit()
    {
        return $this->hasOne(BusinessUnit::class, 'id', 'business_unit_id');
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