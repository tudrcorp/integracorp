<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanGenerator extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'plan_id',
        'control_number',
        'client_data',
        'issued_at',
        'agent_name',
        'population_summary',
        'population_unit',
        'include_monthly_total',
        'brand_color',
        'quotation_page_count',
        'plan_page_number',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'plan_id' => 'integer',
            'issued_at' => 'date',
            'quotation_page_count' => 'integer',
            'plan_page_number' => 'integer',
            'include_monthly_total' => 'boolean',
        ];
    }

    /**
     * Registro base que sirvió de plantilla para esta cotización derivada.
     *
     * Nulo en un registro base. La familia es de un solo nivel: derivar de una
     * derivada cuelga la nueva del mismo base, nunca de su hermana.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * Cotizaciones armadas a partir de este registro como plantilla.
     */
    public function derivedQuotations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function isDerivedQuotation(): bool
    {
        return filled($this->parent_id);
    }

    /**
     * Registro base de la familia: el padre si esta cotización es derivada, o
     * ella misma si ya es la plantilla. Un solo nivel, siempre.
     */
    public function templateBase(): self
    {
        return $this->isDerivedQuotation()
            ? ($this->relationLoaded('parent') ? ($this->parent ?? $this) : ($this->parent()->first() ?? $this))
            : $this;
    }

    /**
     * Plan del catálogo que originó la matriz. Es trazabilidad, no un vínculo
     * vivo: la cotización conserva su propia copia de columnas, beneficios y
     * tarifas, así que editar el plan no la modifica.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(PlanGeneratorColumn::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(PlanGeneratorRow::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function rateRows(): HasMany
    {
        return $this->hasMany(PlanGeneratorRateRow::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function quotationPages(): HasMany
    {
        return $this->hasMany(PlanGeneratorQuotationPage::class)
            ->orderBy('sort_order')
            ->orderBy('page_number');
    }
}
