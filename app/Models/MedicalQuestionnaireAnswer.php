<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta Sí/No de una afiliación (individual o corporativa) a una pregunta del cuestionario
 * médico, con la copia del texto de la pregunta tal como se le preguntó.
 */
class MedicalQuestionnaireAnswer extends Model
{
    protected $table = 'medical_questionnaire_answers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'answerable_type',
        'answerable_id',
        'medical_questionnaire_question_id',
        'question_text',
        'sort_order',
        'answer',
        'answered_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MedicalQuestionnaireQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(MedicalQuestionnaireQuestion::class, 'medical_questionnaire_question_id');
    }
}
