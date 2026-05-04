<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorNurseObservacion extends Model
{
    protected $table = 'doctor_nurse_observacions';

    protected $fillable = [
        'doctor_nurse_id',
        'observation',
        'created_by',
        'updated_by',
    ];

    public function doctorNurse()
    {
        return $this->belongsTo(DoctorNurse::class);
    }
}
