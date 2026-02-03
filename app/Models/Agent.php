<?php

namespace App\Models;

use App\Jobs\SendCartaBienvenidaAgenteAgencia;
use App\Jobs\SendCartaBienvenidaEjecutivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agent extends Model
{
    protected $table = 'agents';

    protected $fillable = [
        'agency_id',
        'code_agent',
        'owner_code',
        'owner_agent',
        'code_agency',
        'name',
        'rif',
        'ci',
        'email',
        'address',
        'phone',
        'user_instagram',
        'country_id',
        'state_id',
        'city_id',
        'region',
        'sex',
        'marital_status',
        'birth_date',

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
        'local_beneficiary_account_number_mon_inter',
        'local_beneficiary_account_bank_mon_inter',
        'local_beneficiary_account_type_mon_inter',


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

        'agent_type_id',
        'status',
        'created_by',
        'date_register',
        'is_accepted_conditions',
        'status',
        'created_by',
        'updated_by',
        'file_coord_bancarias',
        'fir_dig_agent',
        'fir_dig_agency',
        'file_ci_rif',
        'file_w8_w9',
        'file_account_usd',
        'file_account_bsd',
        'file_account_zelle',
        'comments',

        'conf_position_menu',
        'ownerAccountManagers',
        'user_tdev',

        'activate_monthly_frequency',
        'role',

    ];

    /**
     * Get the user associated with the Agency
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function typeAgent(): HasOne
    {
        return $this->hasOne(AgentType::class, 'id', 'agent_type_id');
    }

    /**
     * Get the user that owns the Agent
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id', 'id');
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

    public function documents()
    {
        return $this->hasMany(AgentDocument::class);
    }

    public function notes()
    {
        return $this->hasMany(AgentNoteBlog::class);
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
    public function sendCartaBienvenida($id, $name, $email)
    {

        /**
         * JOB
         */
        SendCartaBienvenidaAgenteAgencia::dispatch($id, $name, $email);
    }

    public function sendCartaBienvenidaEjecutivo($id, $name, $email)
    {

        /**
         * JOB
         */
        SendCartaBienvenidaEjecutivo::dispatch($id, $name, $email);
    }

    public function accountManager()
    {
        return $this->belongsTo(AccountManager::class, 'ownerAccountManagers', 'user_id');
    }

    public function observationCommercialStructures()
    {
        return $this->hasMany(ObservationCommercialStructure::class);
    }
}