<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnotherAddress extends Model
{
    protected $table = 'another_addresses';

    protected $fillable = [
        'telemedicine_patient_id',
        'address',
        'city_id',
        'country_id',
        'state_id',
        'phone_1',
        'phone_2',
        'ambulanceParking',
        'relationship',
    ];
    
    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}