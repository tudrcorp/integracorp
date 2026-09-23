<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanGenerator extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'plan_id',
        'catalog_plan_id',
        'control_number',
        'client_data',
        'issued_at',
        'agent_name',
        'population_summary',
        'population_unit',
        'include_monthly_total',
        'conditions',
        'brand_color',
        'quotation_page_count',
        'plan_page_number',
        'population_import_id',
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
            'catalog_plan_id' => 'integer',
            'issued_at' => 'date',
            'quotation_page_count' => 'integer',
            'plan_page_number' => 'integer',
            'population_import_id' => 'integer',
            'include_monthly_total' => 'boolean',
            'conditions' => 'array',
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

    /**
     * Plan del catálogo que materializa esta matriz.
     *
     * Se crea al cerrar la pre-afiliación (ver PlanGeneratorCatalogPublisher) y
     * es lo que da a la afiliación un `plan_id`, `coverage_id` y `age_range_id`
     * reales. A diferencia de `plan()` —el plan del que se importó la
     * estructura— este es el plan que el generador produjo.
     */
    public function catalogPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'catalog_plan_id', 'id');
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

    /**
     * Padrón importado para la pre-afiliación corporativa.
     */
    public function populations(): HasMany
    {
        return $this->hasMany(PlanGeneratorPopulation::class)
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * Último import de población encolado, para mostrar su progreso y no dejar
     * crear la afiliación mientras siga corriendo.
     */
    public function populationImport(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'population_import_id');
    }
}
