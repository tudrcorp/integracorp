<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelAgency extends Model
{
    //
    protected $table = "travel_agencies";

    protected $fillable = [
        'status',
        'fechaIngreso',
        'representante',
        'idRepresentante',
        'FechaNacimientoRepresentante',
        'name',
        'typeIdentification',
        'numberIdentification',
        'userPortalWeb',
        'aniversary',
        'country_id',
        'state_id',
        'city_id',
        'address',
        'phone',
        'phoneAdditional',
        'email',
        'userInstagram',
        'classification',
        'comision',
        'montoCreditoAprobado',
        'nivel',
        'agenteSuperiorNivel3',
        'agenciaSuperiorNivel2',
        'agenciaPpalNivel1',
        'createdBy',
        'updatedBy',

        
        'logo',
        'nameSecundario',
        'emailSecundario',
        'phoneSecundario',
        'fechaNacimientoSecundario',


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


    ];

    public function travelAgents()
    {
        return $this->hasMany(TravelAgent::class);
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
}
