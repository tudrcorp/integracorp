<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fee extends Model
{
    protected $table = 'fees';

    protected $fillable = [
        'code',
        'plan_id',
        'age_range_id',
        'coverage_id',
        'price',
        'neta',
        'status',
        'created_by',
        'range',
        'coverage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
            'neta' => 'decimal:2',
        ];
    }

    /**
     * Red de seguridad para `plan_id`: es la columna con la que el catálogo
     * resuelve a qué plan pertenece una tarifa, así que una fila sin plan es
     * invisible para AffiliationAffiliateFeeCalculator. Cubrimos aquí y no solo
     * en el formulario para que también queden bien las tarifas creadas por
     * seeders, comandos o código viejo que no conoce la columna.
     */
    protected static function booted(): void
    {
        static::saving(function (self $fee): void {
            if (filled($fee->plan_id) || blank($fee->age_range_id)) {
                return;
            }

            $planId = AgeRange::query()
                ->whereKey($fee->age_range_id)
                ->value('plan_id');

            if (filled($planId)) {
                $fee->plan_id = (int) $planId;
            }
        });
    }

    /**
     * Tarifas de un plan. Centralizado acá para que ningún punto del código
     * vuelva a deducir el plan por `age_ranges.plan_id`, que quedó congelado.
     *
     * @param  Builder<Fee>  $query
     */
    public function scopeForPlan(Builder $query, int $planId): void
    {
        $query->where('fees.plan_id', $planId);
    }

    public function ageRange(): BelongsTo
    {
        return $this->belongsTo(AgeRange::class, 'age_range_id', 'id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    /**
     * Named coverageRecord to avoid colliding with the denormalized `coverage` column,
     * which Filament treats as an attribute and would block Select::relationship('coverage').
     */
    public function coverageRecord(): BelongsTo
    {
        return $this->belongsTo(Coverage::class, 'coverage_id', 'id');
    }

    /**
     * @return HasMany<WhiteCompanyFee, $this>
     */
    public function whiteCompanyFees(): HasMany
    {
        return $this->hasMany(WhiteCompanyFee::class);
    }
}
