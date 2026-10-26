<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Pregunta Sí/No de un cuestionario médico. Una pregunta que ya se respondió no se borra: se
 * desactiva, y las afiliaciones que la respondieron la siguen mostrando con su texto de entonces.
 */
class MedicalQuestionnaireQuestion extends Model
{
    protected $table = 'medical_questionnaire_questions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'medical_questionnaire_id',
        'sort_order',
        'text',
        'is_active',
        'legacy_column',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $question): void {
            if ($question->answers()->exists()) {
                throw new RuntimeException('La pregunta «'.mb_substr((string) $question->text, 0, 80).'…» ya tiene respuestas: desactívela en lugar de borrarla.');
            }
        });
    }

    /**
     * @return BelongsTo<MedicalQuestionnaire, $this>
     */
    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(MedicalQuestionnaire::class, 'medical_questionnaire_id');
    }

    /**
     * @return HasMany<MedicalQuestionnaireAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(MedicalQuestionnaireAnswer::class, 'medical_questionnaire_question_id');
    }
}
