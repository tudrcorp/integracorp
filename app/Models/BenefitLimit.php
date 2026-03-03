<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class BenefitLimit extends Pivot
{
    protected $table = 'benefit_limit';

    protected $fillable = [
        'benefit_id',
        'limit_id',
        'benefit_description',
        'benefit_pvp',
        'limit_cuota',
    ];

    /**
     * Summary of benefit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Benefit, BenefitLimit>
     */
    public function benefit()
    {
        return $this->belongsTo(Benefit::class);
    }

    /**
     * Summary of limit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Limit, BenefitLimit>
     */
    public function limit()
    {
        return $this->belongsTo(Limit::class);
    }
}
