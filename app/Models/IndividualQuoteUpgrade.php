<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Upgrade cotizado en una cotización individual, con su copia congelada del catálogo.
 *
 * @see \App\Support\Quotes\IndividualQuoteUpgrades
 */
class IndividualQuoteUpgrade extends Model
{
    protected $table = 'individual_quote_upgrades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'individual_quote_id',
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
        'rows',
        'persons',
        'annual_amount',
        'sort_order',
        'created_by',
    ];

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
     * @return BelongsTo<IndividualQuote, $this>
     */
    public function individualQuote(): BelongsTo
    {
        return $this->belongsTo(IndividualQuote::class);
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
