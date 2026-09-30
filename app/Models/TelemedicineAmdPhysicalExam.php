<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemedicine\Concerns\HidesDeletedTelemedicineCaseTraces;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Examen físico de una consulta AMD: signos vitales y exploración por sistemas.
 */
class TelemedicineAmdPhysicalExam extends Model
{
    use HasFactory;
    use HidesDeletedTelemedicineCaseTraces;

    protected $table = 'telemedicine_amd_physical_exams';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'telemedicine_consultation_patient_id',
        'telemedicine_doctor_id',
        'heart_rate',
        'respiratory_rate',
        'blood_pressure',
        'pulse',
        'oxygen_saturation',
        'skin',
        'head',
        'ears',
        'nose',
        'mouth',
        'neck',
        'thorax',
        'abdomen',
        'genitourinary',
        'extremities',
        'nervous_system',
        'mental_status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return BelongsTo<TelemedicinePatient, $this>
     */
    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    /**
     * @return BelongsTo<TelemedicineCase, $this>
     */
    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    /**
     * @return BelongsTo<TelemedicineConsultationPatient, $this>
     */
    public function telemedicineConsultationPatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicineConsultationPatient::class);
    }

    /**
     * @return BelongsTo<TelemedicineDoctor, $this>
     */
    public function telemedicineDoctor(): BelongsTo
    {
        return $this->belongsTo(TelemedicineDoctor::class);
    }
}
