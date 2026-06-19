<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliationCorporate extends Model
{
    protected $table = 'affiliation_corporates';

    protected $fillable = [
        'code',
        'corporate_quote_id',
        'code_agency',
        'agent_id',
        'owner_code',

        'name_corporate',
        'rif',
        'address',
        'city_id',
        'state_id',
        'country_id',
        'region_id',
        'phone',
        'email',
        'full_name_contact',
        'nro_identificacion_contact',
        'phone_contact',
        'email_contact',
        'created_by',
        'status',
        'document',
        'observations',
        'audit_items',
        'payment_frequency',
        'fee_anual',
        'total_amount',
        'vaucher_ils',
        'date_payment_initial_ils',
        'date_payment_final_ils',
        'document_ils',
        'type',
        'poblation',
        'activated_at',

        // ...Unidad de Negocio y linea de servicio
        'business_unit_id',
        'business_line_id',
        'ownerAccountManagers',

        // PROVEEDORRES DE SERVICIOS
        'service_providers',

        // ...Fecha de Vigencia de la afiliacion
        'effective_date',

        // Unidades e Negocio y Lineas de Servicio
        'business_unit_id',
        'business_line_id',
    ];

    protected $casts = [
        'service_providers' => 'array',
        'audit_items' => 'array',
    ];

    /**
     * Get the user that owns the Agent
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function accountManager()
    {
        return $this->hasOne(User::class, 'id', 'ownerAccountManagers');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function corporateAffiliates()
    {
        return $this->hasMany(AffiliateCorporate::class);
    }

    /**
     * Cobranzas asociadas al código de afiliación corporativa (`collections.affiliation_code`).
     *
     * @return HasMany<\App\Models\Collection, $this>
     */
    public function billingCollections(): HasMany
    {
        return $this->hasMany(Collection::class, 'affiliation_code', 'code')
            ->orderByRaw('COALESCE(next_payment_date, expiration_date) ASC')
            ->orderBy('id');
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }

    public function corporate_quote()
    {
        return $this->belongsTo(CorporateQuote::class);
    }

    public function paid_membership_corporates()
    {
        return $this->hasMany(PaidMembershipCorporate::class);
    }

    public function affiliationCorporateDocuments(): HasMany
    {
        return $this->hasMany(AffiliationCorporateDocument::class);
    }

    /**
     * @return HasMany<\App\Models\AffiliationCorporateObservation, $this>
     */
    public function affiliationCorporateObservations(): HasMany
    {
        return $this->hasMany(AffiliationCorporateObservation::class)->orderByDesc('created_at');
    }

    public function status_log_corporate_affiliations()
    {
        return $this->hasMany(StatusLogAffiliationCorporate::class);
    }

    public function affiliationCorporatePlans(): HasMany
    {
        return $this->hasMany(AfilliationCorporatePlan::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }
}
