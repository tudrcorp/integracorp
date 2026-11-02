<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\IsFrozenQuoteUpgrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Upgrade cotizado en una cotización corporativa, con su copia congelada del catálogo.
 *
 * @see \App\Support\Quotes\IndividualQuoteUpgrades
 */
class CorporateQuoteUpgrade extends Model
{
    use IsFrozenQuoteUpgrade;

    protected $table = 'corporate_quote_upgrades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'corporate_quote_id',
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
     * @return BelongsTo<CorporateQuote, $this>
     */
    public function corporateQuote(): BelongsTo
    {
        return $this->belongsTo(CorporateQuote::class);
    }
}
