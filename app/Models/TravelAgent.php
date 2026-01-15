<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelAgent extends Model
{
    //
    protected $table = "travel_agents";

    protected $fillable = [
        "name",
        "email",
        "phone",
        "cargo",
        "fechaNacimiento",

        //contacto secundario
        "nameSecundario",
        "emailSecundario",
        "phoneSecundario",
        "cargoSecundario",
        "fechaNacimientoSecundario",

        //datos bancarios moneda local
        "local_beneficiary_name",
        "local_beneficiary_rif",
        "local_beneficiary_account_number",
        "local_beneficiary_account_bank",
        "local_beneficiary_account_type",
        "local_beneficiary_phone_pm",
        "local_beneficiary_account_number_mon_inter",
        "local_beneficiary_account_bank_mon_inter",
        "local_beneficiary_account_type_mon_inter",

        //datos bancarios moneda extrangera
        "extra_beneficiary_name",
        "extra_beneficiary_ci_rif",
        "extra_beneficiary_account_number",
        "extra_beneficiary_account_bank",
        "extra_beneficiary_account_type",
        "extra_beneficiary_route",
        "extra_beneficiary_zelle",
        "extra_beneficiary_ach",
        "extra_beneficiary_swift",
        "extra_beneficiary_aba",
        "extra_beneficiary_address",
        
        "logo",
        "createdBy",
        "updatedBy",
        "travel_agency_id",
    ];

    public function travelAgency()
    {
        return $this->belongsTo(TravelAgency::class);
    }
}
