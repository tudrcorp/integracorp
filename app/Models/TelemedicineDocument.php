<?php

namespace App\Models;

use App\Support\Telemedicine\Concerns\HidesDeletedTelemedicineCaseTraces;
use Illuminate\Database\Eloquent\Model;

class TelemedicineDocument extends Model
{
    use HidesDeletedTelemedicineCaseTraces;

    protected $table = 'telemedicine_documents';

    protected $fillable = [
        'telemedicine_case_id',
        'telemedicine_case_code',
        'telemedicine_consultation_id',
        'telemedicine_patient_id',
        'name',
    ];

    public function telemedicine_case()
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function telemedicine_consultation()
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class);
    }

    public function telemedicine_patient()
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }
}
