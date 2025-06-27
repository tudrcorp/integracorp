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
        'total_amount',
        'currency',
        'reference_payment',
        'payment_date',
        'prox_payment_date',
        'document',
        'observations_payment',
        'status',
        'renewal_date',
        'payment_frequency',
        'banck'
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