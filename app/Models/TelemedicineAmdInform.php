<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemedicineAmdInform extends Model
{
    protected $table = 'telemedicine_amd_informs';

    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_consultation_patient_id',
        'telemedicine_doctor_id',
        'supplier_id',
        'reason_consultation',
        'actual_phatology',
        'background',
        'diagnostic_impression',
        'pa',
        'fc',
        'fr',
        'temp',
        'saturacion',
        'peso',
        'estatura',
        'imc',
        'pdf_document_name',
        'pdf_file_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
            'estatura' => 'decimal:2',
            'imc' => 'decimal:2',
        ];
    }

    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class, 'telemedicine_case_id');
    }

    public function telemedicineConsultationPatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class, 'telemedicine_consultation_patient_id');
    }

    public function telemedicineDoctor(): BelongsTo
    {
        return $this->belongsTo(TelemedicineDoctor::class, 'telemedicine_doctor_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
