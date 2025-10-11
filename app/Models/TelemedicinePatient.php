<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelemedicinePatient extends Model
{
    protected $table = 'telemedicine_patients';
    
    protected $fillable = [
        'plan_id',
        'coverage_id',
        'afilliation_id',
        'afilliation_corporate_id',
        'full_name',
        'nro_identificacion',
        'birth_date',
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
        'status_affiliation',
        'type_affiliation',
        'date_affiliation',
        'code',
        'code_affiliation',
        'business_unit_id',
        'business_line_id',
        'name_corporate',
    ];

    public function businessLine()
    {
        return $this->belongsTo(BusinessLine::class);
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

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

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
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

    public function telemedicinePatientMedications()
    {
        return $this->hasMany(TelemedicinePatientMedications::class);
    }

    public function telemedicineCases()
    {
        return $this->hasMany(TelemedicineCase::class);
    }

    public function telemedicinePatientHistory()
    {
        return $this->hasOne(TelemedicineHistoryPatient::class);
    }

    public function pathologicalHistories(): HasMany
    {
        return $this->hasMany(PathologicalHistory::class);
    }

    public function noPathologicalHistories(): HasMany
    {
        return $this->hasMany(NoPathologicalHistory::class);
    }

    public function surgicalHistories(): HasMany
    {
        return $this->hasMany(SurgicalHistory::class);
    }

    public function familyHistories(): HasMany
    {
        return $this->hasMany(FamilyHistory::class);
    }

    public function gynecologicalHistories(): HasMany
    {
        return $this->hasMany(GynecologicalHistory::class);
    }

    
    
}