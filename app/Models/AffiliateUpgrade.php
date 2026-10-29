<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Upgrade asignado a un afiliado individual: un monto anual en US$ que se suma a
 * su tarifa. El monto queda congelado; solo una renovación lo vuelve a cotizar.
 * Nunca se borra: al quitarlo, o al recotizarlo, pasa a INACTIVO.
 */
class AffiliateUpgrade extends Model
{
    public const STATUS_ACTIVE = 'ACTIVO';

    public const STATUS_INACTIVE = 'INACTIVO';

    protected $table = 'affiliate_upgrades';

    protected $fillable = [
        'affiliate_id',
        'upgrade_id',
        'upgrade_coverage_id',
        'upgrade_coverage_age_range_id',
        'name',
        'coverage_amount',
        'age_at_assignment',
        'amount',
        'ils_voucher_code',
        'ils_date_init',
        'ils_date_end',
        'ils_document_path',
        'status',
        'replaces_id',
        'created_by',
        'updated_by',
        'deactivated_at',
        'deactivated_by',
        'deactivation_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'ils_date_init' => 'date',
            'ils_date_end' => 'date',
            'coverage_amount' => 'decimal:2',
            'age_at_assignment' => 'integer',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<Upgrade, $this>
     */
    public function upgrade(): BelongsTo
    {
        return $this->belongsTo(Upgrade::class);
    }

    /**
     * @return BelongsTo<UpgradeCoverage, $this>
     */
    public function upgradeCoverage(): BelongsTo
    {
        return $this->belongsTo(UpgradeCoverage::class);
    }

    /**
     * @return BelongsTo<UpgradeCoverageAgeRange, $this>
     */
    public function upgradeCoverageAgeRange(): BelongsTo
    {
        return $this->belongsTo(UpgradeCoverageAgeRange::class);
    }

    /**
     * @param  Builder<AffiliateUpgrade>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }
}
