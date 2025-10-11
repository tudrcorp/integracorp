<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyHistory extends Model
{
    protected $table = 'family_histories';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_history_patient_id',
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