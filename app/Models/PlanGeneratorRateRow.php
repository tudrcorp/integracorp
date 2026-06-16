<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanGeneratorRateRow extends Model
{
    protected $fillable = [
        'plan_generator_id',
        'age_range_label',
        'population',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'population' => 'integer',
        ];
    }

    public function planGenerator(): BelongsTo
    {
        return $this->belongsTo(PlanGenerator::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(PlanGeneratorRateCell::class, 'plan_generator_rate_row_id');
    }
}
