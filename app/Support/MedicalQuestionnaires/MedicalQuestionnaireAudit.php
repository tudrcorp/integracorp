<?php

declare(strict_types=1);

namespace App\Support\MedicalQuestionnaires;

use App\Models\MedicalQuestionnaire;
use App\Support\SecurityAudit;

/**
 * Deja en la auditoría cada cambio de un cuestionario médico: las preguntas antes y después.
 */
final class MedicalQuestionnaireAudit
{
    /**
     * @return list<array{id: int, order: int, text: string, active: bool}>
     */
    public static function questionsSnapshot(MedicalQuestionnaire $questionnaire): array
    {
        return $questionnaire->questions()->get()
            ->map(static fn ($question): array => [
                'id' => (int) $question->getKey(),
                'order' => (int) $question->sort_order,
                'text' => (string) $question->text,
                'active' => (bool) $question->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $before
     */
    public static function log(MedicalQuestionnaire $questionnaire, array $before, string $action): void
    {
        MedicalQuestionnaireCatalog::flush();

        SecurityAudit::log($action, 'business.medical-questionnaires', [
            'questionnaire_id' => $questionnaire->getKey(),
            'plan_id' => $questionnaire->plan_id,
            'name' => $questionnaire->name,
            'is_active' => $questionnaire->is_active,
            'questions_before' => $before,
            'questions_after' => self::questionsSnapshot($questionnaire),
        ]);
    }
}
