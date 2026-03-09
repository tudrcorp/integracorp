<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineMedicalReport extends Model
{
    //
    protected $table = 'telemedicine_medical_reports';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_doctor_id',
        'telemedicine_consultation_patient_id',
        'operation_coordination_service_id',
        'pa',
        'fc',
        'fr',
        'temp',
        'saturacion',
        'peso',
        'estatura',
        'imc',
        'reason_consultation',
        'actual_phatology',
        'background',
        'diagnostic_impression',
        'observations',
        'type_service',
        'priority_service',
        'created_by',
        'updated_by',
        'status',
        'cuestion_1',
        'cuestion_2',
        'cuestion_3',
        'cuestion_4',
        'cuestion_5',
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineCase()
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function telemedicineDoctor()
    {
        return $this->belongsTo(TelemedicineDoctor::class);
    }

    public function telemedicineConsultationPatient()
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class);
    }

    public function operationCoordinationService()
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    public function telemedicinePriority()
    {
        return $this->belongsTo(TelemedicinePriority::class);
    }
}
