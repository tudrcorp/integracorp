<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationInventoryMovement extends Model
{
    protected $fillable = [
        'operation_inventory_id',
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_consultation_id',
        'telemedicine_doctor_id',
        'business_unit_id',
        'business_line_id',
        'quantity',
        'unit',
        'type',
        'created_by',
    ];

    // relaciones 1 a 1
    public function operationInventory()
    {
        return $this->belongsTo(OperationInventory::class);
    }

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineCase()
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function telemedicineConsultation()
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class);
    }

    public function telemedicineDoctor()
    {
        return $this->belongsTo(TelemedicineDoctor::class);
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function businessLine()
    {
        return $this->belongsTo(BusinessLine::class);
    }
}
