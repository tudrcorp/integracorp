<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'regions';

    protected $fillable = [
        'definition',
        'country_id',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function states()
    {
        return $this->hasMany(State::class, 'region_id', 'id');
    }

    public function cities()
    {
        return $this->hasMany(City::class, 'region_id', 'id');
    }
}