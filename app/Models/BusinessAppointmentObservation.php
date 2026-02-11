<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessAppointmentObservation extends Model
{
    protected $table = 'business_appointment_observations';

    protected $fillable = [
        'business_appointment_id',
        'observation',
        'created_by',
        'updated_by',
    ];

    public function businessAppointment()
    {
        return $this->belongsTo(BusinessAppointments::class);
    }
}
