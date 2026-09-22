<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateCorporate extends Model
{
    protected $table = 'affiliate_corporates';

    protected $fillable = [
        'affiliation_corporate_id',
        'affiliation_type',
        'first_name',
        'last_name',
        'nro_identificacion',
        'birth_date',
        'age',
        'sex',
        'relationship',
        'phone',
        'email',
        'condition_medical',
        'initial_date',
        'position_company',
        'address',
        'full_name_emergency',
        'phone_emergency',
        'plan_id',
        'coverage_id',
        'fee',
        'subtotal_anual',
        'payment_frequency',
        'subtotal_payment_frequency',
        'subtotal_daily',
        'status',
        'created_by',

        // ...Informacion ILS
        'vaucherIls',
        'dateInit',
        'dateEnd',
        'numberDays',
        'document_ils',
        'document',
        'business_unit_id',
        'specific_business_unit',
        'business_line_id',
    ];

    public function affiliationCorporate()
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    /**
     * @return HasMany<AffiliateCorporateUpgrade, $this>
     */
    public function upgrades(): HasMany
    {
        return $this->hasMany(AffiliateCorporateUpgrade::class);
    }

    /**
     * @return HasMany<AffiliateCorporateUpgrade, $this>
     */
    public function activeUpgrades(): HasMany
    {
        return $this->upgrades()
            ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
            ->orderBy('id');
    }

    /**
     * Agrega `active_upgrades_total`: suma de upgrades activos, sin N+1 en tablas.
     *
     * @param  Builder<AffiliateCorporate>  $query
     */
    public function scopeWithActiveUpgradesTotal(Builder $query): void
    {
        $query->withSum([
            'upgrades as active_upgrades_total' => fn (Builder $upgrades): Builder => $upgrades
                ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE),
        ], 'amount');
    }
}
