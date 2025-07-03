<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AffiliationCorporate extends Model
{
    protected $table = 'affiliation_corporates';

    protected $fillable = [
        'code',
        'corporate_quote_id',
        'code_agency',
        'plan_id',
        'agent_id',
        'owner_code',

        'full_name_con',
        'rif',
        'adress_con',
        'city_id_con',
        'state_id_con',
        'country_id_con',
        'region_con',
        'phone_con',
        'email_con',

        'cuestion_1',
        'cuestion_2',
        'cuestion_3',
        'cuestion_4',
        'cuestion_5',
        'cuestion_6',
        'cuestion_7',
        'cuestion_8',
        'cuestion_9',
        'cuestion_10',
        'cuestion_11',
        'cuestion_12',
        'cuestion_13',
        'cuestion_14',
        'cuestion_15',

        'full_name_applicant',
        'signature_applicant',
        'nro_identificacion_applicant',

        'full_name_agent',
        'signature_agent',
        'code_agent',

        'date_today',

        'created_by',
        'status',
        'individual_quote_id',
        'document',
        'observations_payment',

        //despues de afiliar el poago
        'payment_frequency',
        'coverage_id',
        'activated_at',
        'corporate_members',
        'code_corporate_quote',
        'vaucher_ils',
        'date_payment_initial_ils',
        'date_payment_final_ils',
        'document_ils',
        'type'


    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function corporateAffiliates()
    {
        return $this->hasMany(AffiliateCorporate::class);
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
        return $this->belongsTo(State::class, 'state_id_con', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id_con', 'id');
    }

    
}