<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Upgrade del catálogo: servicios que incluye y coberturas, cada una con sus
 * rangos de edad y el precio anual en US$ de cada rango.
 *
 * Nunca se borra: al retirarlo pasa a INACTIVO, porque hay afiliados que lo tienen.
 * La escritura pasa siempre por `UpgradeCatalogPersistence`.
 */
class Upgrade extends Model
{
    public const STATUS_ACTIVE = 'ACTIVO';

    public const STATUS_INACTIVE = 'INACTIVO';

    protected $table = 'upgrades';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'legacy_upgrade_benefit_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return HasMany<UpgradeService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(UpgradeService::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<UpgradeService, $this>
     */
    public function activeServices(): HasMany
    {
        return $this->services()->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @return HasMany<UpgradeCoverage, $this>
     */
    public function coverages(): HasMany
    {
        return $this->hasMany(UpgradeCoverage::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<UpgradeCoverage, $this>
     */
    public function activeCoverages(): HasMany
    {
        return $this->coverages()->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @return HasMany<AffiliateCorporateUpgrade, $this>
     */
    public function corporateAssignments(): HasMany
    {
        return $this->hasMany(AffiliateCorporateUpgrade::class);
    }

    /**
     * @return HasMany<AffiliateUpgrade, $this>
     */
    public function individualAssignments(): HasMany
    {
        return $this->hasMany(AffiliateUpgrade::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * @param  Builder<Upgrade>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }
}
