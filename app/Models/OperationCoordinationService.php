<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationCoordinationService extends Model
{
    //
    protected $table = 'operation_coordination_services';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_doctor_id',
        'telemedicine_consultation_patient_id',
        'date_solicitud',
        'date_service',
        'business_line_id',
        'business_unit_id',
        'reference_number',
        'status',
        'holder',
        'ci_holder',
        'patient',
        'ci_patient',
        'birth_date_patient',
        'relationship_patient',
        'age_patient',
        'contractor',
        'state_id',
        'city_id',
        'address',
        'phone_holder',
        'symptoms_diagnosis',
        'servicie',
        'specific_service',
        'type_service',
        'supplier_service',
        'farmadoc',
        'type_negotiation',
        'status_negotiation',
        'neto',
        'porcen_tdec',
        'quote_price',
        'negotiation',
        'porcen_discount',
        'price_discount',
        'quote_number',
        'approved_number',
        'service_order_number',
        'bill_number',
        'bill_price',
        'bill_date',
        'incidence',
        'negotiation_description',
        'qc_description',
        'observations',
        'created_by',
        'updated_by',
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

    public function businessLine()
    {
        return $this->belongsTo(BusinessLine::class);
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // relacion 1 a N con la tabla TelemedicinePatientLab
    public function telemedicinePatientLabs()
    {
        return $this->hasMany(TelemedicinePatientLab::class);
    }

    // relacion 1 a N con la tabla TelemedicinePatientMedications
    public function telemedicinePatientMedications()
    {
        return $this->hasMany(TelemedicinePatientMedications::class);
    }

    // relacion 1 a N con la tabla TelemedicinePatientSpecialty
    public function telemedicinePatientSpecialties()
    {
        return $this->hasMany(TelemedicinePatientSpecialty::class);
    }

    // relacion 1 a N con la tabla TelemedicinePatientStudy
    public function telemedicinePatientStudies()
    {
        return $this->hasMany(TelemedicinePatientStudy::class);
    }
}
