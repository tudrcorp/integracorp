<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Fee extends Model
{
    protected $table = 'fees';

    protected $fillable = [
        'code',
        'age_range_id',
        'coverage_id',
        'price',
        'neta',
        'status',
        'created_by',
        'range',
        'coverage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'neta' => 'decimal:2',
        ];
    }

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

    /**
     * @return HasMany<WhiteCompanyFee, $this>
     */
    public function whiteCompanyFees(): HasMany
    {
        return $this->hasMany(WhiteCompanyFee::class);
    }
}
