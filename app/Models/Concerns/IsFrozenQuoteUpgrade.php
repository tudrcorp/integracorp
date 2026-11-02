<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\PlanGenerators\PlanGeneratorUpgrades;

/**
 * Línea de upgrade cotizado con su copia congelada del catálogo: la comparten las cotizaciones
 * individuales y las corporativas.
 */
trait IsFrozenQuoteUpgrade
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coverage_amount' => 'decimal:2',
            'age_prices' => 'array',
            'services' => 'array',
            'excluded_ranges' => 'array',
            'manual_ranges' => 'array',
            'rows' => 'array',
            'persons' => 'integer',
            'annual_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * La línea con la forma que usan {@see PlanGeneratorUpgrades::resolveLinesFrom()} y
     * {@see PlanGeneratorUpgrades::quote()}.
     *
     * @return array<string, mixed>
     */
    public function toLine(): array
    {
        return [
            'line_key' => (string) $this->line_key,
            'upgrade_id' => $this->upgrade_id,
            'upgrade_coverage_id' => $this->upgrade_coverage_id,
            'upgrade_code' => $this->upgrade_code,
            'upgrade_name' => (string) $this->upgrade_name,
            'coverage_amount' => $this->coverage_amount !== null ? round((float) $this->coverage_amount, 2) : null,
            'age_prices' => array_values((array) $this->age_prices),
            'services' => array_values((array) $this->services),
            'excluded_ranges' => PlanGeneratorUpgrades::normalizeExcluded($this->excluded_ranges),
            'manual_ranges' => PlanGeneratorUpgrades::normalizeManual($this->manual_ranges),
            'created_by' => $this->created_by,
            'frozen' => true,
        ];
    }
}
