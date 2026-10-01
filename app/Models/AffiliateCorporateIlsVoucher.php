<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Voucher ILS de un afiliado corporativo para un beneficio con tope en USD de
 * su cobertura. Uno por (afiliado, beneficio, cobertura).
 *
 * Se escribe solo por `CorporateAffiliateIlsVoucherManager`, que valida que el
 * beneficio y la cobertura correspondan al plan del afiliado.
 */
class AffiliateCorporateIlsVoucher extends Model
{
    protected $fillable = [
        'affiliate_corporate_id',
        'affiliation_corporate_id',
        'benefit_coverage_id',
        'plan_id',
        'benefit_id',
        'coverage_id',
        'limit',
        'voucher_code',
        'date_init',
        'date_end',
        'number_days',
        'document_path',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'affiliate_corporate_id' => 'integer',
            'affiliation_corporate_id' => 'integer',
            'benefit_coverage_id' => 'integer',
            'plan_id' => 'integer',
            'benefit_id' => 'integer',
            'coverage_id' => 'integer',
            'limit' => 'decimal:2',
            'date_init' => 'date',
            'date_end' => 'date',
            'number_days' => 'integer',
        ];
    }

    public function affiliateCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliateCorporate::class);
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class);
    }
}
