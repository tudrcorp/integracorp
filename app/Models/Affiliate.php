<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Affiliate extends Model
{
    protected $table = 'affiliates';

    protected $fillable = [
        'affiliation_id',
        'affiliation_type',
        'full_name',
        'birth_date',
        'nro_identificacion',
        'sex',
        'age',
        'relationship',
        'document',
        'phone',
        'email',
        'status',
        'country_id',
        'city_id',
        'state_id',
        'region',
        'address',
        'plan_id',
        'coverage_id',
        'age_range_id',
        'created_by',

        // ...Informacion ILS
        'vaucherIls',
        'dateInit',
        'dateEnd',
        'numberDays',
        'document_ils',

        // ...Informacion adicional
        'document_telemedicine',
        'fee',
        'total_amount',
        'created_by',
        'payment_frequency',
        'business_unit_id',
        'business_line_id',
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

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function ageRange()
    {
        return $this->belongsTo(AgeRange::class);
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
