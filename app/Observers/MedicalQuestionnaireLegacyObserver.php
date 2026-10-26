<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Affiliation;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireAnswers;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireCatalog;
use Illuminate\Support\Facades\Schema;

/**
 * Si algún camino todavía escribe las columnas viejas `cuestion_N` de la afiliación (el importador
 * de afiliaciones), copia esas respuestas al cuestionario dinámico para las preguntas que vienen de
 * esa columna. Así nada queda fuera del cuestionario nuevo.
 */
class MedicalQuestionnaireLegacyObserver
{
    public function saved(Affiliation $affiliation): void
    {
        $columns = $affiliation->wasRecentlyCreated
            ? array_keys(array_filter($affiliation->getAttributes(), static fn (mixed $value): bool => $value !== null))
            : array_keys($affiliation->getChanges());
        $dirty = array_values(array_filter($columns, static fn (string $column): bool => str_starts_with($column, 'cuestion_')));

        if ($dirty === [] || ! self::tableExists()) {
            return;
        }

        $state = [];

        foreach (MedicalQuestionnaireCatalog::questionsForPlan($affiliation->plan_id) as $question) {
            if ($question->legacy_column !== null && in_array($question->legacy_column, $dirty, true)) {
                $state[MedicalQuestionnaireAnswers::FIELD_PREFIX.$question->getKey()] = $affiliation->getAttribute($question->legacy_column);
            }
        }

        MedicalQuestionnaireAnswers::save($affiliation, $affiliation->plan_id, $state);
    }

    private static function tableExists(): bool
    {
        static $exists = false;

        return $exists = $exists || Schema::hasTable('medical_questionnaire_answers');
    }
}
