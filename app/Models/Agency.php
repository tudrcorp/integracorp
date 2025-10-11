<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendCartaBienvenidaAgenteAgencia;
use App\Jobs\SendCartaBienvenidaAgenteAgenciaTwo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agency extends Model
{
    protected $table = 'agencies';

    protected $fillable = [
        'code',
        'code_agent',
        'code_agency',
        'owner_code',
        'name_corporative',
        'rif',
        'ci_responsable',
        'email',
        'address',
        'phone',
        'user_instagram',
        'country_id',
        'state_id',
        'city_id',
        'region',

        //contacto secundario
        'name_contact_2',
        'email_contact_2',
        'phone_contact_2',

        //datos bancarios moneda local
        'local_beneficiary_name',
        'local_beneficiary_rif',
        'local_beneficiary_account_number',
        'local_beneficiary_account_bank',
        'local_beneficiary_account_type',
        'local_beneficiary_phone_pm',


        //datos bancarios moneda extrangera
        'extra_beneficiary_name',
        'extra_beneficiary_ci_rif',
        'extra_beneficiary_account_number',
        'extra_beneficiary_account_bank',
        'extra_beneficiary_account_type',
        'extra_beneficiary_route',
        'extra_beneficiary_zelle',
        'extra_beneficiary_ach',
        'extra_beneficiary_swift',
        'extra_beneficiary_aba',
        'extra_beneficiary_address',

        //comisones        
        'tdec',
        'tdev',
        'commission_tdec',
        'commission_tdec_renewal',
        'commission_tdev',
        'commission_tdev_renewal',

        'agency_type_id',
        'date_register',
        'is_accepted_conditions',
        'status',
        'created_by',
        'comments',

        /*Docuemntos*/
        'doc_digital_signature',
        'doc_document_identity',
        'doc_w8_w9',
        'doc_bank_data_ves',
        'doc_bank_data_usd',
        'ownerAccountManagers'


    ];

    /**
     * Get the user associated with the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function typeAgency(): HasOne
    {
        return $this->hasOne(AgencyType::class, 'id', 'agency_type_id');
    }

    /**
     * Get all of the comments for the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'agency_id', 'id');
    }

    /**
     * Get the user that owns the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    /**
     * Get the user that owns the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    /**
     * Get the user that owns the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    /**
     * Get the user that owns the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }

    public function corporateQuoteRequests()
    {
        return $this->hasMany(CorporateQuoteRequest::class);
    }

    //Relacion uno a uno con la tanla de agentes
    public function agent()
    {
        return $this->hasOne(Agent::class);
    }

    public function documents()
    {
        return $this->hasMany(AgencyDocument::class);
    }

    public function notes()
    {
        return $this->hasMany(AgencyNoteBlog::class);
    }

    /**
     * Funciones para la ejecucion de jobs
     * para el envio de la carta de bienvenida
     * 
     * @return void
     * @author TuDrEnCasa
     * 
     * @param array $details
     */
    public function sendCartaBienvenida($code, $name, $email)
    {
        /**
         * JOB
         */
        SendCartaBienvenidaAgenteAgenciaTwo::dispatch($code, $name, $email);
    }

    /**
     * Get the user that owns the Agent
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function accountManager()
    {
        return $this->hasOne(User::class, 'id', 'ownerAccountManagers');
    }
}
