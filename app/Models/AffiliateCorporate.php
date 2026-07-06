<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCorporate extends Model
{
    protected $table = 'affiliate_corporates';

    protected $fillable = [
        'affiliation_corporate_id',
        'affiliation_type',
        'first_name',
        'last_name',
        'nro_identificacion',
        'birth_date',
        'age',
        'sex',
        'phone',
        'email',
        'condition_medical',
        'initial_date',
        'position_company',
        'address',
        'full_name_emergency',
        'phone_emergency',
        'plan_id',
        'coverage_id',
        'fee',
        'subtotal_anual',
        'payment_frequency',
        'subtotal_payment_frequency',
        'subtotal_daily',
        'status',
        'created_by',

        // ...Informacion ILS
        'vaucherIls',
        'dateInit',
        'dateEnd',
        'numberDays',
        'document_ils',
        'document',
        'business_unit_id',
        'business_line_id',
    ];

    public function affiliationCorporate()
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

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }
}
