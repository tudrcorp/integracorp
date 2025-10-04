<?php

namespace App\Models;

use App\Models\TelemedicineServiceList;
use Illuminate\Database\Eloquent\Model;

class TelemedicineConsultationPatient extends Model
{
    protected $table = 'telemedicine_consultation_patients';

    protected $fillable = [
        'telemedicine_case_id',
        'telemedicine_case_code',
        'telemedicine_patient_id',
        'telemedicine_doctor_id',
        'telemedicine_service_list_id',
        'telemedicine_priority_id',
        
        'code_reference',
        'full_name',
        'nro_identificacion',
        'reason_consultation',
        'actual_phatology',
        'background',
        'diagnostic_impression',
        
        'labs',
        'studies',
        'other_labs',
        'other_studies',
        'consult_specialist',
        'other_specialist',

        'assigned_by',

        'status',
        'cuestion_1',
        'cuestion_2',
        'cuestion_3',
        'cuestion_4',
        'cuestion_5',
        'feedbackOne',
        'duration',
        
    ];

    protected $casts = [
        'labs'                  => 'array',
        'studies'               => 'array',
        'consult_specialist'    => 'array',
        'other_labs'            => 'array',
        'other_studies'         => 'array',
        'other_specialist'      => 'array',
    ];

    
    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineDoctor()
    {
        return $this->belongsTo(TelemedicineDoctor::class);
    }

    public function telemedicineCase()
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    //...Muchos medicamentos
    public function telemedicinePatientMedications()
    {
        return $this->hasMany(TelemedicinePatientMedications::class);
    }

    //...Muchos laboratorios
    public function telemedicinePatientLabs()
    {
        return $this->hasMany(TelemedicinePatientLab::class);
    }

    //...Muchas imagenes
    public function telemedicinePatientStudies()
    {
        return $this->hasMany(TelemedicinePatientStudy::class);
    }

    //...Muchas consultas con especialistas
    public function telemedicinePatientSpecialists()
    {
        return $this->hasMany(TelemedicinePatientSpecialty::class);
    }

    public function telemedicineConsultationPatient()
    {
        return $this->hasMany(TelemedicineConsultationPatient::class, 'telemedicine_case_code', 'code');
    }

    public function telemedicineServiceList()
    {
        return $this->belongsTo(TelemedicineServiceList::class, 'telemedicine_service_list_id');
    }

    public function telemedicinePriority()
    {
        return $this->belongsTo(TelemedicinePriority::class, 'telemedicine_priority_id');
    }

    
    
}