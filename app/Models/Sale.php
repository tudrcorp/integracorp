<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $table = 'sales';

    protected $fillable = [
        'plan_id',
        'coverage_id',
        'agent_id',
        'date_activation',
        'code_agency',
        'owner_code',
        'invoice_number',
        'affiliation_id',
        'affiliation_code',
        'affiliate_full_name',
        'affiliate_contact',
        'affiliate_ci_rif',
        'affiliate_phone',
        'affiliate_email',
        'service',
        'persons',
        'created_by',
        'total_amount',
        'total_amount_ves',
        'type',
        'payment_method',
        'payment_frequency',
        'bank',
        'status_payment_commission',
        "pay_amount_usd",
        "pay_amount_ves",
        "type_roll",
        "bank_usd",
        "bank_ves",
        "payment_date",
        "observations",
        'reference_payment',
        'date_payment_voucher',
    ];

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
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

    public function collections()
    {
        return $this->hasMany(Collection::class);
    }

    public function agencyMasterName()
    {
        return $this->belongsTo(Agency::class, 'owner_code', 'code');
    }

    
}