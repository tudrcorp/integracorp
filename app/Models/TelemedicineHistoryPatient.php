<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelemedicineHistoryPatient extends Model
{
    protected $table = 'telemedicine_history_patients';

    protected $fillable = [
            'telemedicine_doctor_id',
            'telemedicine_patient_id',
            'code' ,
            'user_id' ,
            'history_date' ,
            'tension_alta' ,
            'asma' ,
            'cardiacos' ,
            'gastritis_ulceras' ,
            'enfermedad_autoimmune' ,
            'trombosis_embooleanas' ,
            'fracturas' ,
            'cancer' ,
            'tranfusiones_sanguineas' ,
            'tiroides' ,
            'hepatitis' ,
            'moretones_frecuentes' ,
            'psiquiatricas' ,
            'covid' ,
            'diabetes' ,
            'alteraciones_coagulacion' ,
            'vih' ,
            'neurologia' ,
            'ansiedad_angustia' ,
            'lupus' ,
            'diabetes_mellitus' ,
            'presion_arterial_alta' ,
            'tiene_cateter_venoso' ,
            'trombosis_venosa' ,
            'embooleania_pulmonar' ,
            'varices_piernas' ,
            'insuficiencia_arterial' ,
            'coagulacion_anormal' ,
            'alcohol' ,
            'drogas' ,
            'vacunas_recientes' ,
            'transfusiones_sanguineas' ,
            'edad_primera_menstruation' ,
            'fecha_ultima_regla' ,
            'numero_embarazos' ,
            'numero_partos' ,
            'numero_abortos' ,
            'cesareas' ,
            'allergies' ,
            'history_surgical' ,
            'medications_supplements' ,
            'observations_ginecologica' ,
            'observations_allergies' ,
            'observations_medication' ,
            'observations_personal' ,
            'observations_diagnosis' ,
            'observations_not_pathological' ,
            'created_by' ,
            'created_at' ,
            'updated_at' ,
            'observations_pathological' ,
            'tension_alta_app' ,
            'diabetes_app' ,
            'asma_app' ,
            'cardiacos_app' ,
            'gastritis_ulceras_app' ,
            'enfermedad_autoimmune_app' ,
            'vih_app' ,
            'trombosis_embooleanas_app' ,
            'fracturas_app' ,
            'cancer_app' ,
            'tranfusiones_sanguineas_app' ,
            'tiroides_app' ,
            'hepatitis_app' ,
            'moretones_frecuentes_app' ,
            'transfusiones_sanguineas_app' ,
            'psiquiatricas_app' ,
            'covid_app' ,
            'tabaco' ,
            'input_tension_alta',
            'input_diabetes',
            'input_asma',
            'input_cardiacos',
            'input_gastritis_ulceras',
            'input_enfermedad_autoimmune',
            'input_trombosis_embooleanas',
            'input_fracturas',
            'input_cancer',
            'input_ftranfusiones_sanguineas',
            'input_tiroides',
            'input_hepatitis',
            'input_moretones_frecuentes',
            'input_psiquiatricas',
            'input_tension_alta_app',
            'input_diabetes_app',
            'input_asma_app',
            'input_cardiacos_app',
            'input_gastritis_ulceras_app',
            'input_enfermedad_autoimmune_app',
            'input_trombosis_embooleanas_app',
            'input_fracturas_app',
            'input_cancer_app',
            'input_ftranfusiones_sanguineas_app',
            'input_tiroides_app',
            'input_hepatitis_app',
            'input_moretones_frecuentes_app',
            'input_psiquiatricas_app',
    ];

    //Declarar un campo tipo json
    protected $casts = [
        'allergies' => 'array',
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function telemedicineDoctor()
    {
        return $this->belongsTo(TelemedicineDoctor::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pathologicalHistories (): HasMany
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