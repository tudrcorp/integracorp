<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessAppointments extends Model
{
    protected $fillable = [
        'legal_name',
        'phone',
        'email',
        'country_id',
        'state_id',
        'city_id',
        'status',
        'created_by',
        'updated_by',
    ];

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

    public function businessAppointmentObservations()
    {
        return $this->hasMany(BusinessAppointmentObservation::class, 'business_appointment_id', 'id');
    }
}
