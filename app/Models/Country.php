<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';

    protected $fillable = [
        'name',
        'code',
    ];

    public function states()
    {
        return $this->hasMany(State::class, 'country_id', 'id');
    }

    public function cities()
    {
        return $this->hasMany(City::class, 'country_id', 'id');
    }

    public function regions()
    {
        return $this->hasMany(Region::class, 'country_id', 'id');
    }
}