<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicinePatient extends Model
{
    protected $table = 'telemedicine_patients';
    
    protected $fillable = [
        'plan_id',
        'afilliation_id',
        'afilliation_corporate_id',
        'full_name',
        'nro_identificacion',
        'date_birth',
        'sex',
        'phone',
        'email',
        'address',
        'city_id',
        'country_id',
        'region',
        'state_id',
        'phone_contact',
        'email_contact',
        'code',
        'created_by',
        'age',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function afilliation()
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function afilliationCorporate()
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ...
    //Relacion 1 a N con la tabla telemedicine_consultation_patients
    public function telemedicineConsultationPatients()
    {
        return $this->hasMany(TelemedicineConsultationPatient::class);
    }

    public function telemedicinePatientStudies()
    {
        return $this->hasMany(TelemedicinePatientStudy::class);
    }

    public function telemedicinePatientMedicines()
    {
        return $this->hasMany(TelemedicinePatientMedicine::class);
    }

    public function telemedicinePatientLabs()
    {
        return $this->hasMany(TelemedicinePatientLaboratoryTest::class);
    }
    
}