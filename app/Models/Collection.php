<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collections';

    protected $fillable = [
        'sale_id',
        'include_date',
        'owner_code',
        'code_agency',
        'agent_id',
        'coverage_id',
        'collection_invoice_number',
        'quote_number',
        'affiliation_code',
        'affiliate_full_name',
        'affiliate_contact',
        'affiliate_ci_rif',
        'affiliate_phone',
        'affiliate_email',
        'affiliate_status',
        'plan_id',
        'service',
        'persons',
        'type',
        'reference',
        'payment_method',
        'payment_frequency',
        'next_payment_date',
        'total_amount',
        'expiration_date',
        'status',
        'days',
        'created_by',
        'bank',
        
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

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function detail_individual_quote()
    {
        return $this->belongsTo(DetailIndividualQuote::class);
    }

    public function paid_memberships()
    {
        return $this->hasMany(PaidMembership::class);
    }

    public function individual_quote()
    {
        return $this->belongsTo(IndividualQuote::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    
}