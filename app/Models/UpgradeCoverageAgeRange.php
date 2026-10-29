<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rango de edad de una cobertura de upgrade y su precio anual en US$.
 * Dentro de una misma cobertura los rangos activos nunca se solapan.
 */
class UpgradeCoverageAgeRange extends Model
{
    protected $table = 'upgrade_coverage_age_ranges';

    protected $fillable = [
        'upgrade_coverage_id',
        'age_from',
        'age_to',
        'price',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'age_from' => 'integer',
            'age_to' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<UpgradeCoverage, $this>
     */
    public function coverage(): BelongsTo
    {
        return $this->belongsTo(UpgradeCoverage::class, 'upgrade_coverage_id');
    }

    public function label(): string
    {
        return $this->age_from.' a '.$this->age_to.' años';
    }
}
