<?php

declare(strict_types=1);

namespace App\Filament\Shared\MedicalQuestionnaires;

use App\Support\MedicalQuestionnaires\MedicalQuestionnaireAnswers;
use Closure;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Respuestas guardadas del cuestionario médico de una afiliación, con el texto de cada pregunta tal
 * como se preguntó. Reemplaza las listas de preguntas escritas a mano en las fichas.
 */
final class MedicalQuestionnaireAnswersEntry
{
    /**
     * @param  (Closure(Model): ?Model)|null  $affiliation  de dónde sale la afiliación si el registro no lo es (p. ej. un afiliado)
     */
    public static function make(?Closure $affiliation = null): TextEntry
    {
        return TextEntry::make('medical_questionnaire_answers')
            ->hiddenLabel()
            ->state(fn (Model $record): HtmlString => self::html($affiliation !== null ? $affiliation($record) : $record))
            ->columnSpanFull();
    }

    public static function html(?Model $affiliation): HtmlString
    {
        $answers = MedicalQuestionnaireAnswers::forDisplay($affiliation);

        if ($answers === []) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Esta afiliación no tiene cuestionario médico respondido.</p>');
        }

        $rows = '';

        foreach ($answers as $index => $answer) {
            [$label, $color] = match ($answer['answer']) {
                true => ['Sí', '#dc2626'],
                false => ['No', '#16a34a'],
                default => ['Sin responder', '#6b7280'],
            };

            $rows .= '<li style="display:flex;gap:12px;align-items:flex-start;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(127,127,127,.15);">'
                .'<span class="text-sm text-gray-700 dark:text-gray-200"><strong>'.($index + 1).'.</strong> '.e($answer['text']).'</span>'
                .'<span style="flex:none;font-weight:700;font-size:.8rem;color:'.$color.';border:1px solid '.$color.';border-radius:9999px;padding:1px 10px;">'.$label.'</span>'
                .'</li>';
        }

        return new HtmlString('<ul>'.$rows.'</ul>');
    }
}
