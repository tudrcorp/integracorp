<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ajuste de facturación por un cambio de población corporativa: lo que se cobra aparte por las
 * altas en el período ya facturado y cuánto cambian las cuotas futuras. Ciclo: APPLIED → REVERTED.
 *
 * @see \App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingAdjuster
 */
class CorporateBillingAdjustment extends Model
{
    public const STATUS_APPLIED = 'APPLIED';

    public const STATUS_REVERTED = 'REVERTED';

    protected $table = 'corporate_billing_adjustments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'affiliation_corporate_id',
        'affiliation_code',
        'corporate_population_update_id',
        'status',
        'effective_date',
        'payment_frequency',
        'cycle_start',
        'cycle_end',
        'persons_added',
        'persons_removed',
        'charge_amount',
        'installment_delta',
        'installments_adjusted',
        'charge_collection_id',
        'fingerprint',
        'summary',
        'reason',
        'created_by_id',
        'created_by_name',
        'pdf_path',
        'reverted_at',
        'reverted_by_id',
        'reverted_by_name',
        'revert_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'cycle_start' => 'date',
            'cycle_end' => 'date',
            'persons_added' => 'integer',
            'persons_removed' => 'integer',
            'charge_amount' => 'decimal:2',
            'installment_delta' => 'decimal:2',
            'installments_adjusted' => 'integer',
            'summary' => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPLIED => 'Aplicado',
            self::STATUS_REVERTED => 'Revertido',
            default => (string) $this->status,
        };
    }

    /**
     * @return BelongsTo<AffiliationCorporate, $this>
     */
    public function affiliationCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    /**
     * @return BelongsTo<CorporatePopulationUpdate, $this>
     */
    public function populationUpdate(): BelongsTo
    {
        return $this->belongsTo(CorporatePopulationUpdate::class, 'corporate_population_update_id');
    }

    /**
     * @return BelongsTo<Collection, $this>
     */
    public function chargeCollection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'charge_collection_id');
    }

    /**
     * @return HasMany<CorporateBillingAdjustmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CorporateBillingAdjustmentLine::class);
    }
}
