<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reprogramación del «Próximo seguimiento» de un caso, hecha por el médico sin
 * registrar una consulta nueva. Ver {@see \App\Support\Telemedicine\TelemedicineCaseFollowUpRescheduler}.
 */
class TelemedicineCaseFollowUpReschedule extends Model
{
    protected $table = 'telemedicine_case_follow_up_reschedules';

    protected $fillable = [
        'telemedicine_case_id',
        'telemedicine_doctor_id',
        'user_id',
        'priority_monitoring',
        'previous_next_follow_up_at',
        'next_follow_up_at',
        'observation',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority_monitoring' => 'integer',
            'previous_next_follow_up_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TelemedicineCase, $this>
     */
    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class, 'telemedicine_case_id');
    }

    /**
     * @return BelongsTo<TelemedicineDoctor, $this>
     */
    public function telemedicineDoctor(): BelongsTo
    {
        return $this->belongsTo(TelemedicineDoctor::class, 'telemedicine_doctor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
