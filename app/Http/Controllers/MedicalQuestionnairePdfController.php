<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Business\Resources\MedicalQuestionnaires\MedicalQuestionnaireResource;
use App\Models\MedicalQuestionnaire;
use App\Services\MedicalQuestionnairePdfService;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\Response;

class MedicalQuestionnairePdfController extends Controller
{
    public function __invoke(MedicalQuestionnaire $questionnaire): Response
    {
        if (! MedicalQuestionnaireResource::canAccess()) {
            SecurityAudit::log('AUDIT_BUSINESS_MEDICAL_QUESTIONNAIRE_PDF_DENIED', 'business.medical-questionnaires.pdf', [
                'questionnaire_id' => $questionnaire->getKey(),
            ]);

            abort(403);
        }

        $filename = MedicalQuestionnairePdfService::filename($questionnaire);

        SecurityAudit::log('AUDIT_BUSINESS_MEDICAL_QUESTIONNAIRE_PDF_VIEWED', 'business.medical-questionnaires.pdf', [
            'questionnaire_id' => $questionnaire->getKey(),
            'filename' => $filename,
        ]);

        return MedicalQuestionnairePdfService::make($questionnaire)->stream($filename);
    }
}
