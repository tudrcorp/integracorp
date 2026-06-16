<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanGeneratorRateCell extends Model
{
    protected $fillable = [
        'plan_generator_rate_row_id',
        'plan_generator_column_id',
        'rate_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_amount' => 'decimal:2',
        ];
    }

    public function rateRow(): BelongsTo
    {
        return $this->belongsTo(PlanGeneratorRateRow::class, 'plan_generator_rate_row_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(PlanGeneratorColumn::class, 'plan_generator_column_id');
    }
}
