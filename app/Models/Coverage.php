<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
