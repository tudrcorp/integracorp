<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Telemedicine\Concerns\HidesDeletedTelemedicineCaseTraces;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemedicineCaseMessage extends Model
{
    use HidesDeletedTelemedicineCaseTraces;

    /** Mensaje escrito por una persona. */
    public const KIND_MESSAGE = 'message';

    /** Resumen de consulta que publica INTEGRACORP (ver ConsultationChatSummary). */
    public const KIND_CONSULTATION_SUMMARY = 'consultation_summary';

    protected $table = 'telemedicine_case_messages';

    protected $fillable = [
        'telemedicine_case_id',
        'user_id',
        'body',
        'kind',
        'telemedicine_consultation_patient_id',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function isConsultationSummary(): bool
    {
        return $this->kind === self::KIND_CONSULTATION_SUMMARY;
    }

    /**
     * @return BelongsTo<TelemedicineCase, $this>
     */
    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
