<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cobertura de un upgrade: un monto cubierto en US$ con sus rangos de edad.
 * `amount` nulo solo existe en los upgrades migrados del catálogo anterior.
 */
class UpgradeCoverage extends Model
{
    protected $table = 'upgrade_coverages';

    protected $fillable = [
        'upgrade_id',
        'amount',
        'sort',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Upgrade, $this>
     */
    public function upgrade(): BelongsTo
    {
        return $this->belongsTo(Upgrade::class);
    }

    /**
     * @return HasMany<UpgradeCoverageAgeRange, $this>
     */
    public function ageRanges(): HasMany
    {
        return $this->hasMany(UpgradeCoverageAgeRange::class)->orderBy('age_from')->orderBy('id');
    }

    /**
     * @return HasMany<UpgradeCoverageAgeRange, $this>
     */
    public function activeAgeRanges(): HasMany
    {
        return $this->ageRanges()->where('status', Upgrade::STATUS_ACTIVE);
    }

    public function label(): string
    {
        return $this->amount !== null
            ? 'Cobertura '.number_format((float) $this->amount, 2, ',', '.').' US$'
            : 'Cobertura única';
    }
}
