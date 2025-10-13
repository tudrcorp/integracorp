<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fee extends Model
{
    protected $table = 'fees';

    protected $fillable = [
        'code',
        'age_range_id',
        'coverage_id',
        'price',
        'status',
        'created_by',
        'range',
        'coverage'
    ];

    public function ageRange(): BelongsTo
    {
        return $this->belongsTo(AgeRange::class, 'age_range_id', 'id');
    }

    /**
     * Get the user associated with the Fee
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    // public function coverage(): HasOne
    // {
    //     return $this->hasOne(Coverage::class, 'id', 'coverage_id');
    // }

    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class, 'coverage_id', 'id');
    }

}