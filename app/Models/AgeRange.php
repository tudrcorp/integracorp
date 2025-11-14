<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgeRange extends Model
{
    protected $table = 'age_ranges';

    protected $fillable = [
        'plan_id',
        'coverage_id',
        'fee',
        'code',
        'range',
        'status',
        'created_by',
        'age_init',
        'age_end',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class, 'age_range_id', 'id');
    }

    
}