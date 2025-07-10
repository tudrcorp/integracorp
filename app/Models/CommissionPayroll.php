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

        'local_beneficiary_name',
        'local_beneficiary_rif',
        'local_beneficiary_account_number',
        'local_beneficiary_account_bank',
        'local_beneficiary_account_type',
        'local_beneficiary_phone_pm',


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