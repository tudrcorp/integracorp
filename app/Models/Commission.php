<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $table = 'commissions';

    protected $fillable = [
        'date_payment_affiliate',
        'agency_id',
        'code_agency',
        'owner_code',
        'agent_id',
        'plan_id',
        'coverage_id',
        'sale_id',
        'invoice_number',
        'affiliate_full_name',
        'amount',
        'payment_method',
        'veto',
        'payment_frequency',
        'commission_agency_master',
        'commission_agency_general',
        'commission_agent',
        'total_payment_commission',
        'date_payment_commission',
        'created_by',
        'commission_agency_master_tdec',
        'commission_agency_general_tdec',
        'commission_agent_tdec',

        'pay_amount_usd',
        'pay_amount_ves',
        'commission_agency_master_usd',
        'commission_agency_general_usd',
        'commission_agent_usd',
        'commission_agency_master_ves',
        'commission_agency_general_ves',
        'commission_agent_ves',

        'payment_method_usd',
        'payment_method_ves',

        'code',
        'date_ini',
        'date_end'
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
        return $this->belongsTo(Agency::class);
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