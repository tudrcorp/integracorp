<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineCase extends Model
{
    protected $table = 'telemedicine_cases';
    
    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_doctor_id',
        'patient_name',
        'patient_age',
        'patient_sex',
        'patient_phone',
        'patient_address',
        'patient_country',
        'patient_state',
        'patient_city',
        'assigned_by',
        'status',
        'reason'
    ];

    public function patient()
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(TelemedicineDoctor::class, 'telemedicine_doctor_id');
    }
    
}