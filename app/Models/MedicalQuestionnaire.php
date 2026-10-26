<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuestionario médico de un plan (Negocios → Cuestionarios médicos). Sus preguntas se editan desde
 * la pantalla y todos los formularios de afiliación las leen de aquí.
 *
 * @see \App\Support\MedicalQuestionnaires\MedicalQuestionnaireCatalog
 */
class MedicalQuestionnaire extends Model
{
    protected $table = 'medical_questionnaires';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'name',
        'is_active',
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
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<MedicalQuestionnaireQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(MedicalQuestionnaireQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<MedicalQuestionnaireQuestion, $this>
     */
    public function activeQuestions(): HasMany
    {
        return $this->questions()->where('is_active', true);
    }
}
