<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Benefit extends Model
{
    protected $table = 'benefits';

    protected $fillable = [
        'plan_id',
        'limit_id',
        'code',
        'description',
        'status',
        'created_by',
        'price',
        'neto',
        'porcentaje_incremento',
        'pvp',
        'updated_by',
        'porcen_comision',
        'porcen_utilidad',
        'porcen_acu_adi',
    ];

    public function limit(): BelongsTo
    {
        return $this->belongsTo(Limit::class);
    }

    /**
     * Get all of the comments for the Benefit
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function limits()
    {
        return $this->belongsToMany(Limit::class, 'benefit_limit', 'benefit_id', 'limit_id')
            ->withPivot('benefit_description', 'benefit_pvp', 'limit_cuota');
    }

    public function coverages()
    {
        return $this->belongsToMany(Coverage::class, 'benefit_coverages', 'benefit_id', 'coverage_id')
            ->withPivot('benefit_description', 'coverage_price', 'price');
    }

    public function benefitLimits()
    {
        return $this->hasMany(BenefitLimit::class);
    }

    public function benefitCoverages()
    {
        return $this->hasMany(BenefitCoverage::class);
    }

    public function getBenefitLimitsAttribute()
    {
        return $this->benefitLimits()->get();
    }

    public function getBenefitCoveragesAttribute()
    {
        return $this->benefitCoverages()->get();
    }
}
