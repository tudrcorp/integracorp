<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanGeneratorColumn extends Model
{
    protected $fillable = [
        'plan_generator_id',
        'column_key',
        'header_label',
        'rate_adjustment_percent',
        'coverage_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_adjustment_percent' => 'decimal:2',
            'coverage_id' => 'integer',
        ];
    }

    public function planGenerator(): BelongsTo
    {
        return $this->belongsTo(PlanGenerator::class);
    }
}
