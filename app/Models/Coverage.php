<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coverage extends Model
{
    protected $table = 'coverages';

    protected $fillable = [
        'price',
        'plan_id',
        'status',
        'created_by',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    /**
     * Rangos de edad configurados para esta cobertura (y el plan asociado).
     *
     * @return HasMany<AgeRange, $this>
     */
    public function ageRanges(): HasMany
    {
        return $this->hasMany(AgeRange::class, 'coverage_id', 'id')
            ->orderBy('age_init')
            ->orderBy('id');
    }

    public function benefits()
    {
        return $this->belongsToMany(Benefit::class, 'benefit_coverages', 'coverage_id', 'benefit_id')
            ->withPivot('benefit_description', 'coverage_price');
    }

    public function benefitCoverages()
    {
        return $this->hasMany(BenefitCoverage::class);
    }

    public function getBenefitCoveragesAttribute()
    {
        return $this->benefitCoverages()->get();
    }
}
