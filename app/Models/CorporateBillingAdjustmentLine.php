<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una persona dentro de un ajuste por población: su tarifa, los días que se le cobran del
 * período ya facturado (solo altas) y cuánto suma o resta a cada cuota futura.
 */
class CorporateBillingAdjustmentLine extends Model
{
    public const KIND_ADD = 'ALTA';

    public const KIND_REMOVE = 'BAJA';

    protected $table = 'corporate_billing_adjustment_lines';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'corporate_billing_adjustment_id',
        'corporate_population_update_item_id',
        'affiliate_corporate_id',
        'kind',
        'movement',
        'full_name',
        'document',
        'annual_fee',
        'period_fee',
        'covered_from',
        'covered_until',
        'days',
        'period_days',
        'prorated_amount',
        'installment_delta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'annual_fee' => 'decimal:2',
            'period_fee' => 'decimal:2',
            'covered_from' => 'date',
            'covered_until' => 'date',
            'days' => 'integer',
            'period_days' => 'integer',
            'prorated_amount' => 'decimal:2',
            'installment_delta' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CorporateBillingAdjustment, $this>
     */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(CorporateBillingAdjustment::class, 'corporate_billing_adjustment_id');
    }
}
