<?php

namespace App\Models;

use App\Observers\PersonIdentityObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([PersonIdentityObserver::class])]
class Affiliate extends Model
{
    /** Eliminación reversible: ver {@see \App\Support\Affiliations\Deletion\AffiliationDeletionService}. */
    use SoftDeletes;

    protected $table = 'affiliates';

    protected $fillable = [
        'affiliation_id',
        'affiliation_type',
        'full_name',
        'birth_date',
        'nro_identificacion',
        'sex',
        'age',
        'relationship',
        'document',
        'phone',
        'email',
        'status',
        'country_id',
        'city_id',
        'state_id',
        'region',
        'address',
        'plan_id',
        'coverage_id',
        'age_range_id',
        'created_by',

        // ...Informacion ILS
        'vaucherIls',
        'dateInit',
        'dateEnd',
        'numberDays',
        'document_ils',

        // ...Informacion adicional
        'document_telemedicine',
        'fee',
        'total_amount',
        'created_by',
        'payment_frequency',
        'business_unit_id',
        'specific_business_unit',
        'business_line_id',
    ];

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function ageRange()
    {
        return $this->belongsTo(AgeRange::class);
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
     * @return HasMany<AffiliateUpgrade, $this>
     */
    public function upgrades(): HasMany
    {
        return $this->hasMany(AffiliateUpgrade::class);
    }

    /**
     * @return HasMany<AffiliateUpgrade, $this>
     */
    public function activeUpgrades(): HasMany
    {
        return $this->upgrades()
            ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
            ->orderBy('id');
    }

    /**
     * Agrega `active_upgrades_total`: suma de upgrades activos, sin N+1 en tablas.
     *
     * @param  Builder<Affiliate>  $query
     */
    public function scopeWithActiveUpgradesTotal(Builder $query): void
    {
        $query->withSum([
            'upgrades as active_upgrades_total' => fn (Builder $upgrades): Builder => $upgrades
                ->where('status', AffiliateUpgrade::STATUS_ACTIVE),
        ], 'amount');
    }
}
