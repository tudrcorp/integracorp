<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaidMembershipCorporate extends Model
{
    protected $table = 'paid_membership_corporates';

    protected $fillable = [
        'affiliation_corporate_id',
        'agent_id',
        'code_agency',
        'plan_id',
        'coverage_id',
        'pay_amount_ves',
        'pay_amount_usd',
        'total_amount',
        'currency',
        'reference_payment_usd',
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
        'date_payment_voucher',
        'aproved_by',
    ];

    public function affiliation_corporate()
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

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    
}