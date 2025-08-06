<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateCorporate extends Model
{
    protected $table = 'affiliate_corporates';

    protected $fillable = [
        'affiliation_corporate_id',
        'first_name',
        'last_name',
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
        'plan_id',
        'coverage_id',
        'fee',
        'subtotal_anual',
        'payment_frequency',
        'subtotal_payment_frequency',
        'subtotal_daily',
        'status',
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

}