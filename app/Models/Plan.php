<?php

namespace App\Models;

use App\Enums\PlanPricingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'business_unit_id',
        'code',
        'description',
        'status',
        'created_by',
        'type',
        'agencies',
        'pricing_mode',
        'structure_version',
    ];

    /** Planes armados con el asistente de Negocios. */
    public const STRUCTURE_VERSION_WIZARD = 2;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_mode' => PlanPricingMode::class,
            'structure_version' => 'integer',
        ];
    }

    public function pricingMode(): PlanPricingMode
    {
        return $this->pricing_mode instanceof PlanPricingMode
            ? $this->pricing_mode
            : (PlanPricingMode::fromStored($this->pricing_mode) ?? PlanPricingMode::Coberturas);
    }

    /**
     * Un paquete de beneficios no tiene coberturas: la tarifa depende solo del
     * rango de edad.
     */
    public function isBenefitPackage(): bool
    {
        return $this->pricingMode() === PlanPricingMode::Paquete;
    }

    /**
     * Los planes históricos se siguen editando con el formulario anterior: su
     * estructura alimenta cotizaciones y afiliaciones ya emitidas.
     */
    public function usesStructureWizard(): bool
    {
        return (int) ($this->structure_version ?? 1) >= self::STRUCTURE_VERSION_WIZARD;
    }

    /**
     * Get all of the comments for the Plan
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class, 'plan_id', 'id');
    }

    /**
     * The servicios that belong to the User
     */
    public function agencyPlans(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'agency_plans')
            ->using(AgencyPlan::class)
            ->withPivot(['description']);
    }

    /**
     * The servicios that belong to the User
     */
    public function benefitPlans(): BelongsToMany
    {
        return $this->belongsToMany(Benefit::class, 'benefit_plans')
            ->using(BenefitPlan::class)
            ->withPivot(['description']);
    }

    /**
     * The servicios that belong to the User
     */
    public function coveragePlans(): BelongsToMany
    {
        return $this->belongsToMany(Coverage::class, 'coverage_plans')
            ->using(CoveragePlan::class)
            ->withPivot(['price']);
    }

    /**
     * The servicios that belong to the User
     */
    public function feePlans(): BelongsToMany
    {
        return $this->belongsToMany(Fee::class, 'fee_plans')
            ->using(FeePlan::class)
            ->withPivot(['range', 'price']);
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class, 'plan_id', 'id');
    }

    public function clinicalSettings(): HasMany
    {
        return $this->hasMany(PlanBenefitClinicalSetting::class);
    }

    public function businessLine()
    {
        return $this->belongsTo(BusinessLine::class, 'business_line_id', 'id');
    }

    public function businessUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }

    public function ageRanges(): HasMany
    {
        return $this->hasMany(AgeRange::class, 'plan_id', 'id');
    }

    public function affiliationCorporates(): BelongsToMany
    {
        return $this->belongsToMany(AffiliationCorporate::class);
    }
}
