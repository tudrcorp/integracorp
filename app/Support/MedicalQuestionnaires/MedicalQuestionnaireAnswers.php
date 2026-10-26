<?php

declare(strict_types=1);

namespace App\Support\MedicalQuestionnaires;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\MedicalQuestionnaireAnswer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Lee y guarda las respuestas del cuestionario médico de una afiliación.
 *
 * El estado del formulario es `['q_{id de la pregunta}' => true|false|null]`. Al guardar se copia el
 * texto y el orden de la pregunta: si después se cambia la redacción, la afiliación sigue mostrando
 * lo que de verdad se le preguntó. Nunca se borran respuestas.
 */
final class MedicalQuestionnaireAnswers
{
    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_CORPORATE = 'corporativa';

    public const FIELD_PREFIX = 'q_';

    public static function typeOf(Model $affiliation): string
    {
        return match (true) {
            $affiliation instanceof Affiliation => self::TYPE_INDIVIDUAL,
            $affiliation instanceof AffiliationCorporate => self::TYPE_CORPORATE,
            default => throw new InvalidArgumentException('El cuestionario médico solo aplica a afiliaciones.'),
        };
    }

    /**
     * Estado para llenar el formulario al editar.
     *
     * @return array<string, ?bool>
     */
    public static function stateFor(?Model $affiliation): array
    {
        if ($affiliation === null || ! $affiliation->exists) {
            return [];
        }

        $state = [];

        foreach (self::answersQuery($affiliation)->get(['medical_questionnaire_question_id', 'answer']) as $answer) {
            $state[self::FIELD_PREFIX.$answer->medical_questionnaire_question_id] = $answer->answer;
        }

        return $state;
    }

    /**
     * Guarda las respuestas del formulario para las preguntas vigentes del plan. Solo se aceptan
     * preguntas de ese cuestionario y valores Sí/No; lo demás se ignora.
     *
     * @param  array<string, mixed>|null  $state
     */
    public static function save(Model $affiliation, mixed $planId, ?array $state, ?string $actor = null): int
    {
        $questions = MedicalQuestionnaireCatalog::questionsForPlan($planId);

        if ($questions->isEmpty() || $state === null || $state === []) {
            return 0;
        }

        $type = self::typeOf($affiliation);
        $actor ??= Auth::user()?->name ?? 'SISTEMA';
        $saved = 0;

        foreach ($questions as $question) {
            $key = self::FIELD_PREFIX.$question->getKey();

            if (! array_key_exists($key, $state)) {
                continue;
            }

            $value = self::normalize($state[$key]);

            MedicalQuestionnaireAnswer::query()->updateOrCreate(
                [
                    'answerable_type' => $type,
                    'answerable_id' => $affiliation->getKey(),
                    'medical_questionnaire_question_id' => $question->getKey(),
                ],
                [
                    'question_text' => (string) $question->text,
                    'sort_order' => (int) $question->sort_order,
                    'answer' => $value,
                    'answered_by' => $actor,
                ],
            );
            $saved++;
        }

        return $saved;
    }

    /**
     * Respuestas guardadas, en el orden en que se preguntaron, con su texto de entonces.
     *
     * @return list<array{text: string, answer: ?bool}>
     */
    public static function forDisplay(?Model $affiliation): array
    {
        if ($affiliation === null || ! $affiliation->exists) {
            return [];
        }

        return self::answersQuery($affiliation)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['question_text', 'answer'])
            ->map(static fn (MedicalQuestionnaireAnswer $answer): array => ['text' => (string) $answer->question_text, 'answer' => $answer->answer])
            ->values()
            ->all();
    }

    public static function normalize(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<MedicalQuestionnaireAnswer>
     */
    private static function answersQuery(Model $affiliation)
    {
        return MedicalQuestionnaireAnswer::query()
            ->where('answerable_type', self::typeOf($affiliation))
            ->where('answerable_id', $affiliation->getKey());
    }
}
