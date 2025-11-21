<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhiteCompany extends Model
{
    protected $table = 'white_companies';

    protected $fillable = [
        'name',
        'logo',
        'rif',
        'email',
        'phone',
        'address',
        'city_id',
        'state_id',
        'country_id',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }
    
}