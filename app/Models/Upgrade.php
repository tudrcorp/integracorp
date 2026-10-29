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
        'image_path',
        'status',
        'legacy_upgrade_benefit_id',
        'created_by',
        'updated_by',
    ];

    /** Carpeta del disco `public` donde viven las imágenes de las tarjetas. */
    public const IMAGE_DIRECTORY = 'upgrades/images';

    /**
     * Si la tabla ya tiene la columna de imagen (el código puede llegar antes que la migración).
     * Se recuerda por conexión.
     */
    public static function hasImageColumn(?string $connection = null): bool
    {
        static $exists = [];
        $connection ??= (new self)->getConnectionName() ?? (string) config('database.default');

        try {
            return $exists[$connection] ??= \Illuminate\Support\Facades\Schema::connection($connection)->hasColumn('upgrades', 'image_path');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * URL pública de la imagen de la tarjeta, o null si no tiene (o el archivo ya no existe).
     */
    public function imageUrl(): ?string
    {
        $path = trim((string) $this->image_path);

        if ($path === '' || ! str_starts_with($path, self::IMAGE_DIRECTORY.'/') || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }

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
