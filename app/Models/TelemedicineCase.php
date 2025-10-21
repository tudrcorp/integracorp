<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineCase extends Model
{
    protected $table = 'telemedicine_cases';
    
    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_doctor_id',
        'telemedicine_priority_id',
        'patient_name',
        'patient_age',
        'patient_sex',
        'patient_phone',
        'patient_phone_2',
        'patient_address',
        'patient_country_id',
        'patient_state_id',
        'patient_city_id',
        'assigned_by',
        'status',
        'reason',
        'code',
        'ambulanceParking',
        'directionAmbulance'
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function telemedicineDoctor()
    {
        return $this->belongsTo(TelemedicineDoctor::class, 'telemedicine_doctor_id');
    }

    public function consultations()
    {
        return $this->hasMany(TelemedicineConsultationPatient::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'patient_country_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'patient_state_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'patient_city_id');
    }

    public function priority()
    {
        return $this->belongsTo(TelemedicinePriority::class, 'telemedicine_priority_id');
    }

    public function operationLogs()
    {
        return $this->hasMany(TelemedicineOperationsLog::class);
    }

    public function observations()
    {
        return $this->hasMany(ObservationCase::class);
    }

    
}