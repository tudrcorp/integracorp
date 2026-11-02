<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\IsFrozenQuoteUpgrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Upgrade cotizado en una cotización individual, con su copia congelada del catálogo.
 *
 * @see \App\Support\Quotes\IndividualQuoteUpgrades
 */
class IndividualQuoteUpgrade extends Model
{
    use IsFrozenQuoteUpgrade;

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
     * @return BelongsTo<IndividualQuote, $this>
     */
    public function individualQuote(): BelongsTo
    {
        return $this->belongsTo(IndividualQuote::class);
    }
}
