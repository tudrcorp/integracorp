<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelemedicineCaseAttachmentStage;
use App\Support\Telemedicine\Concerns\HidesDeletedTelemedicineCaseTraces;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento o imagen que el médico cargó durante la consulta inicial o un
 * seguimiento del caso.
 *
 * @property TelemedicineCaseAttachmentStage $stage
 */
class TelemedicineCaseAttachment extends Model
{
    use HidesDeletedTelemedicineCaseTraces;

    protected $table = 'telemedicine_case_attachments';

    protected $fillable = [
        'telemedicine_case_id',
        'telemedicine_patient_id',
        'telemedicine_consultation_patient_id',
        'telemedicine_doctor_id',
        'stage',
        'follow_up_number',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'description',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => TelemedicineCaseAttachmentStage::class,
            'follow_up_number' => 'integer',
            'size' => 'integer',
        ];
    }

    public function stageLabel(): string
    {
        return $this->stage->labelWithNumber($this->follow_up_number);
    }

    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    public function telemedicineDoctor(): BelongsTo
    {
        return $this->belongsTo(TelemedicineDoctor::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
