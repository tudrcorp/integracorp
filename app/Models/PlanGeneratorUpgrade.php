<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Upgrade incluido en una cotización del generador, con su precio congelado.
 * La escritura pasa por `PlanGeneratorUpgrades`.
 */
class PlanGeneratorUpgrade extends Model
{
    protected $fillable = [
        'plan_generator_id',
        'line_key',
        'upgrade_id',
        'upgrade_coverage_id',
        'upgrade_code',
        'upgrade_name',
        'coverage_amount',
        'age_prices',
        'services',
        'excluded_ranges',
        'manual_ranges',
        'sort_order',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'upgrade_id' => 'integer',
            'upgrade_coverage_id' => 'integer',
            'coverage_amount' => 'decimal:2',
            'age_prices' => 'array',
            'services' => 'array',
            'excluded_ranges' => 'array',
            'manual_ranges' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PlanGenerator, $this>
     */
    public function planGenerator(): BelongsTo
    {
        return $this->belongsTo(PlanGenerator::class);
    }
}
