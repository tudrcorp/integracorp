<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineHistoryPatient extends Model
{
    protected $table = 'telemedicine_history_patients';

    protected $fillable = [
            'telemedicine_doctor_id',
            'telemedicine_patient_id',
            'code',
            'code_patient',
            'history_date',
            'weight',
            'height',
            'condition',
            'cancer',
            'diabetes',
            'tension_alta',
            'cardiacos',
            'psiquiatricas',
            'alteraciones_coagulacion',
            'trombosis_embooleanas',
            'tranfusiones_sanguineas',
            'covid',
            'hepatitis',
            'vih',
            'gastritis_ulceras',
            'neurologia',
            'ansiedad_angustia',
            'tiroides',
            'lupus',
            'enfermedad_autoimmune',
            'diabetes_mellitus',
            'presion_arterial_alta',
            'tiene_cateter_venoso',
            'fracturas',
            'trombosis_venosa',
            'embooleania_pulmonar',
            'varices_piernas',
            'insuficiencia_arterial',
            'coagulacion_anormal',
            'moretones_frecuentes',
            'sangrado_cirugias_previas',
            'sangrado_cepillado_dental',
            'alcohol',
            'drogas',
            'vacunas_recientes',
            'transfusiones_sanguineas',
            'numero_embarazos',
            'numero_partos',
            'numero_abortos',
            'cesareas',
            'allergies',
            'history_surgical',
            'medications_supplements',
            'observations_personal',
            'observations_allergies',
            'observations_medication',
            'observations_ginecologica',
            'observations_pathological',
            'observations_not_pathological',
            'created_by'
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

    
    
}