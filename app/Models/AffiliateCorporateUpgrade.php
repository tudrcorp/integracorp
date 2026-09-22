<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Upgrade cargado a un afiliado corporativo: un monto anual en US$ que se suma
 * a su tarifa. Nunca se borra; al quitarlo pasa a INACTIVO para no perder la traza.
 */
class AffiliateCorporateUpgrade extends Model
{
    public const STATUS_ACTIVE = 'ACTIVO';

    public const STATUS_INACTIVE = 'INACTIVO';

    protected $table = 'affiliate_corporate_upgrades';

    protected $fillable = [
        'affiliate_corporate_id',
        'affiliation_corporate_id',
        'upgrade_benefit_id',
        'name',
        'amount',
        'status',
        'created_by',
        'updated_by',
        'deactivated_at',
        'deactivated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deactivated_at' => 'datetime',
        ];
    }

    public function affiliateCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliateCorporate::class);
    }

    public function affiliationCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    public function upgradeBenefit(): BelongsTo
    {
        return $this->belongsTo(UpgradeBenefit::class);
    }

    /**
     * @param  Builder<AffiliateCorporateUpgrade>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }
}
