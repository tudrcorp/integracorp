<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca del carnet de un plan: logo, color de los detalles y nombre del pie.
 * Se escribe solo por `PlanCarnetBrands`.
 */
class PlanCarnetBrand extends Model
{
    protected $table = 'plan_carnet_brands';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'logo_path',
        'color',
        'company_label',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
