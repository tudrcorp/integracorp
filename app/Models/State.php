<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $table = 'states';

    protected $fillable = [
        'country_id',
        'region_id',
        'definition',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function cities()
    {
        return $this->hasMany(City::class, 'city_id', 'id');
    }

    public function businessAppointments()
    {
        return $this->hasMany(BusinessAppointments::class);
    }

}