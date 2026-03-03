<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BenefitCoverage extends Pivot
{
    protected $table = 'benefit_coverages';

    protected $fillable = [
        'benefit_id',
        'coverage_id',
        'benefit_description',
        'coverage_price',
        'price',
    ];

    /**
     * Summary of benefit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Benefit, BenefitCoverage>
     */
    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }

    /**
     * Summary of coverage
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Coverage, BenefitCoverage>
     */
    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }
}
