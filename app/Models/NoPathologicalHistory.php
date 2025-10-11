<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoPathologicalHistory extends Model
{
    protected $table = 'no_pathological_histories';

    protected $fillable = [
        'telemedicine_history_patient_id',
        'telemedicine_patient_id',
        'observations',
        'created_by',
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function telemedicineHistoryPatient()
    {
        return $this->belongsTo(TelemedicineHistoryPatient::class, 'telemedicine_history_patient_id');
    }
}