<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineRepresentative extends Model
{
    protected $table = 'telemedicine_representatives';

    protected $fillable = [
        'telemedicine_patient_id',
        'full_name',
        'nro_identificacion',
        'phone',
        'email',
        'relationship'
    ];

    public function telemedicinePatient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }
}