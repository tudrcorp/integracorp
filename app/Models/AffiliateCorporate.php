<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateCorporate extends Model
{
    protected $table = 'affiliate_corporates';

    protected $fillable = [
        'affiliation_corporate_id',
        'last_name',
        'first_name',
        'nro_identificacion',
        'birth_date',
        'age',
        'sex',
        'phone',
        'email',
        'condition_medical',
        'initial_date',
        'position_company',
        'address',
        'full_name_emergency',
        'phone_emergency',
    ];

    public function affiliationCorporate()
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }
}