<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    protected $table = 'affiliates';

    protected $fillable = [
        'affiliation_id',
        'full_name',
        'birth_date',
        'nro_identificacion',
        'sex',
        'age',
        'relationship',
        'document',
        'phone',
        'status',
        'country_id',
        'city_id',
        'state_id',
        'region',
        'address',
        'plan_id',
        'coverage_id'
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

}