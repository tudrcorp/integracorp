<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaidMembership extends Model
{
    protected $table = 'paid_memberships';

    protected $fillable = [
        'affiliation_id',
        'agent_id',
        'code_agency',
        'plan_id',
        'coverage_id',
        'pay_amount_ves',
        'pay_amount_usd',
        'total_amount',
        'currency',
        'reference_payment_zelle',
        'reference_payment_ves',
        'payment_date',
        'prox_payment_date',
        'document_usd',
        'document_ves',
        'observations_payment',
        'status',
        'renewal_date',
        'payment_frequency',
        'bank_usd',
        'bank_ves',
        'payment_method',
        'payment_method_usd',
        'payment_method_ves',
        'type_roll',
        'tasa_bcv',
        'created_by',

        //Agregado
        'name_ti_usd',
        'date_payment_voucher',
        
    ];

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
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