<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
        'coverage',
    ];

    public function ageRange(): BelongsTo
    {
        return $this->belongsTo(AgeRange::class, 'age_range_id', 'id');
    }

    public function plan(): HasOneThrough
    {
        return $this->hasOneThrough(
            Plan::class,
            AgeRange::class,
            'id',
            'id',
            'age_range_id',
            'plan_id',
        );
    }

    /**
     * Named coverageRecord to avoid colliding with the denormalized `coverage` column,
     * which Filament treats as an attribute and would block Select::relationship('coverage').
     */
    public function coverageRecord(): BelongsTo
    {
        return $this->belongsTo(Coverage::class, 'coverage_id', 'id');
    }
}
