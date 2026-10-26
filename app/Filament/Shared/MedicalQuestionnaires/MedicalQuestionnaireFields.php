<?php

declare(strict_types=1);

namespace App\Filament\Shared\MedicalQuestionnaires;

use App\Support\MedicalQuestionnaires\MedicalQuestionnaireAnswers;
use App\Support\MedicalQuestionnaires\MedicalQuestionnaireCatalog;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

/**
 * Las preguntas del cuestionario médico del plan elegido, leídas de Negocios → Cuestionarios médicos.
 *
 * Se pone en el lugar donde antes estaban las preguntas escritas a mano. Lee el plan del campo
 * `$planField` del mismo formulario; si ese plan no tiene cuestionario, no se muestra. Al editar
 * se llena con las respuestas guardadas y al guardar las escribe en `medical_questionnaire_answers`
 * (no en la afiliación), así que funciona igual en creación y edición sin tocar cada página.
 */
final class MedicalQuestionnaireFields
{
    public const STATE_PATH = 'medical_answers';

    public static function make(string $planField = 'plan_id'): Group
    {
        return Group::make()
            ->statePath(self::STATE_PATH)
            ->schema(fn (Get $get): array => self::radios($get($planField)))
            ->visible(fn (Get $get): bool => MedicalQuestionnaireCatalog::planHasQuestionnaire($get($planField)))
            ->dehydrated(false)
            ->loadStateFromRelationshipsUsing(function (Group $component, ?Model $record): void {
                $component->state(MedicalQuestionnaireAnswers::stateFor($record));
            })
            ->saveRelationshipsUsing(function (Group $component, Model $record, Get $get) use ($planField): void {
                MedicalQuestionnaireAnswers::save($record, $get($planField), (array) $component->getRawState());
            })
            ->columnSpanFull();
    }

    /**
     * @return list<Radio>
     */
    private static function radios(mixed $planId): array
    {
        $radios = [];
        $number = 1;

        foreach (MedicalQuestionnaireCatalog::questionsForPlan($planId) as $question) {
            $radios[] = Radio::make(MedicalQuestionnaireAnswers::FIELD_PREFIX.$question->getKey())
                ->label($number.'. '.$question->text)
                ->boolean('Sí', 'No')
                ->inline();
            $number++;
        }

        return $radios;
    }
}
