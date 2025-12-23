<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineDoctor extends Model
{
    protected $table = 'telemedicine_doctors';

    protected $fillable = [
        'full_name',
        'nro_identificacion',
        'email',
        'code_cm',
        'code_mpps',
        'phone',
        'specialty',
        'address',
        'image',
        'signature',
        'code',
        'status',
        'created_by',
        'updated_by'
    ];

    public function telemedicineConsultationPatients()
    {
        return $this->hasMany(TelemedicineConsultationPatient::class);
    }

    public function telemedicinePatientStudies()
    {
        return $this->hasMany(TelemedicinePatientStudy::class);
    }

    public function telemedicinePatientLbas()
    {
        return $this->hasMany(TelemedicineConsultationPatient::class);
    }

    public function telemedicinePatients()
    {
        return $this->belongsToMany(TelemedicinePatient::class, 'telemedicine_cases', 'telemedicine_doctor_id', 'telemedicine_patient_id');
    }

}