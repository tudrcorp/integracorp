<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MedicalQuestionnaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Str;

/**
 * PDF con las preguntas vigentes de un cuestionario médico, listo para imprimir y firmar: casillas
 * Sí / No por pregunta, observaciones, datos del solicitante y firma. Es corto: se genera al
 * instante, sin cola.
 */
final class MedicalQuestionnairePdfService
{
    public static function make(MedicalQuestionnaire $questionnaire): PdfDocument
    {
        $questionnaire->loadMissing(['plan:id,description', 'activeQuestions']);

        return Pdf::loadView('documents.medical-questionnaire', [
            'questionnaire' => $questionnaire,
            'questions' => $questionnaire->activeQuestions,
            'logoDataUri' => AgentFichaPdfService::logoDataUri(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');
    }

    public static function filename(MedicalQuestionnaire $questionnaire): string
    {
        return 'cuestionario-medico-'.Str::slug((string) ($questionnaire->plan?->description ?? $questionnaire->name)).'.pdf';
    }
}
