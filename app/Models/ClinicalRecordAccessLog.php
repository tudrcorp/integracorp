<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una lectura de la ficha clínica de un paciente (vista en pantalla o PDF). Solo se agrega, nunca se edita.
 */
class ClinicalRecordAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_VIEW = 'view';

    public const ACTION_PDF = 'pdf';

    protected $fillable = [
        'telemedicine_patient_id',
        'user_id',
        'user_name',
        'panel',
        'action',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class, 'telemedicine_patient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
