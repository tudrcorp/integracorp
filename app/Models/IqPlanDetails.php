<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IqPlanDetails extends Model
{
    protected $table = 'iq_plan_details';

    protected $fillable = [
        'agent_id',
        'agency_id',
        'individual_quote_id',
        'individual_quote_code',
        'plan_id',
        'coverage_id',
        'amount',
        'status',
    ];

    public function individualQuote()
    {
        return $this->belongsTo(IndividualQuote::class, 'individual_quote_id', 'id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class, 'coverage_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }
    
    // public function getStatusAttribute($value)
    // {
    //     return $value === 1 ? 'Active' : 'Inactive';
    // }
    // public function setStatusAttribute($value)
    // {
    //     $this->attributes['status'] = $value === 'Active' ? 1 : 0;
    // }
        
    // public function getAmountAttribute($value)
    // {
    //     return '$' . number_format($value, 2);
    // }   
}