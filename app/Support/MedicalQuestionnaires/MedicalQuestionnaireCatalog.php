<?php

declare(strict_types=1);

namespace App\Support\MedicalQuestionnaires;

use App\Models\MedicalQuestionnaire;
use App\Models\MedicalQuestionnaireQuestion;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Qué preguntas tiene hoy el cuestionario médico de cada plan. Lo leen todos los formularios de
 * afiliación (paneles internos, red comercial y pre-afiliaciones públicas), así que un cambio en la
 * pantalla de Negocios se ve en todos lados sin tocar código.
 *
 * Cachea por request (un formulario lo consulta varias veces por render).
 */
final class MedicalQuestionnaireCatalog
{
    /**
     * @var array<int, ?MedicalQuestionnaire>
     */
    private static array $cache = [];

    public static function forPlan(mixed $planId): ?MedicalQuestionnaire
    {
        $planId = (int) $planId;

        if ($planId < 1) {
            return null;
        }

        if (! array_key_exists($planId, self::$cache)) {
            try {
                self::$cache[$planId] = MedicalQuestionnaire::query()
                    ->where('plan_id', $planId)
                    ->where('is_active', true)
                    ->with('activeQuestions')
                    ->first();
            } catch (Throwable) {
                self::$cache[$planId] = null;
            }
        }

        return self::$cache[$planId];
    }

    /**
     * @return Collection<int, MedicalQuestionnaireQuestion>
     */
    public static function questionsForPlan(mixed $planId): Collection
    {
        return self::forPlan($planId)?->activeQuestions ?? new Collection;
    }

    public static function planHasQuestionnaire(mixed $planId): bool
    {
        return self::questionsForPlan($planId)->isNotEmpty();
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
