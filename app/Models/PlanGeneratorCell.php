<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanGeneratorCell extends Model
{
    protected $fillable = [
        'plan_generator_row_id',
        'plan_generator_column_id',
        'is_selected',
        'coverage_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'coverage_amount' => 'decimal:2',
        ];
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(PlanGeneratorRow::class, 'plan_generator_row_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(PlanGeneratorColumn::class, 'plan_generator_column_id');
    }
}
