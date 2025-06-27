<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionPayroll extends Model
{
    protected $table = 'commission_payrolls';

    protected $fillable = [
        'code',
        'code_pcc',
        'date_ini',
        'date_end',
        'type',
        'owner_code',
        'code_agency',
        'agent_id',
        'owner_name',

        'total_commission',
        
        'amount_commission_master_agency',
        'amount_commission_master_agency_usd',
        'amount_commission_master_agency_ves',
        
        'amount_commission_general_agency',
        'amount_commission_general_agency_usd',
        'amount_commission_general_agency_ves',
        
        'amount_commission_agent',
        'amount_commission_agent_usd',
        'amount_commission_agent_ves',
        
        'amount_commission_subagent',
        'amount_commission_subagent_usd',
        'amount_commission_subagent_ves',
    ];

    public function ownerNameAgency()
    {
        return $this->belongsTo(Agency::class, 'owner_code', 'code');
    }

    public function generalNameAgency()
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }

}