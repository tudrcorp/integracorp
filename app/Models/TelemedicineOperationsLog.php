<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineOperationsLog extends Model
{
    protected $table = 'telemedicine_operations_logs';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_consultation_patient_id',
        'code_reference',
        'operation',
        'description',
        'status',
        'observations',
        'responsable',
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineCase()
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function telemedicineConsultationPatient()
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class);
    }
    
}