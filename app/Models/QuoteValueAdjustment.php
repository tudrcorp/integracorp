<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ajuste de valores de una cotización (tarifa y personas por línea) y lo que cambió en la
 * afiliación enlazada y en su cobranza. Se revierte con {@see \App\Support\Quotes\Adjustments\QuoteValueAdjustments::revert()}.
 *
 * `lines`: list<array{detail_id, label, plan_id, coverage_id, age_range_id, fee_before, fee_after, persons_before, persons_after}>
 */
class QuoteValueAdjustment extends Model
{
    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';

    public const TYPE_CORPORATE = 'CORPORATIVA';

    public const STATUS_APPLIED = 'APLICADO';

    public const STATUS_REVERTED = 'REVERTIDO';

    protected $table = 'quote_value_adjustments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id', 'quote_type', 'quote_id', 'quote_code', 'status', 'reason', 'lines', 'effects',
        'created_by_id', 'created_by_name', 'reverted_at', 'reverted_by_id', 'reverted_by_name', 'revert_reason', 'revert_effects',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'effects' => 'array',
            'revert_effects' => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<QuoteValueAdjustmentAffiliation, $this>
     */
    public function affiliations(): HasMany
    {
        return $this->hasMany(QuoteValueAdjustmentAffiliation::class, 'quote_value_adjustment_id');
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function isCorporate(): bool
    {
        return $this->quote_type === self::TYPE_CORPORATE;
    }
}
