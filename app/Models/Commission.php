<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $table = 'commissions';

    protected $fillable = [
        'code',
        'code_agency',
        'agent_id',
        'plan_id',
        'coverage_id',
        'sale_id',
        'affiliate_full_name',
        'amount',
        'veto',
        'payment_frequency',
        'created_by',
        'pay_amount_usd',
        'pay_amount_ves',
        'affiliation_code',
        'commission_agency_master_usd',
        'commission_agency_master_ves',
        'commission_agency_general_usd',
        'porcent_agency_general',
        'commission_agency_general_ves',
        'porcent_agente',
        'commission_agent_usd',
        'commission_agent_ves',
        'porcent_agency_master',
        'payment_method',
        
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
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

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    public function ownerNameAgency()
    {
        return $this->belongsTo(Agency::class, 'owner_code', 'code');
    }

    public function generalNameAgency()
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    
}