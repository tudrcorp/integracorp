<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QualityControlAffiliateType;
use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use App\Models\QualityControlSurvey;
use App\Services\QualityControlPdfService;
use App\Support\Operations\QualityControl\QualityControlReportRequest;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vista previa y descarga de los PDF de Quality Control, y el enlace firmado para WhatsApp.
 *
 * La huella del contenido viaja como ETag: si el navegador ya tiene esa versión, 304.
 * Quien no puede ver la encuesta recibe 404 (no debe saber que existe).
 */
class QualityControlPdfController extends Controller
{
    public function surveyPreview(Request $request, QualityControlSurvey $qualityControlSurvey): Response
    {
        return $this->survey($request, $qualityControlSurvey, 'inline');
    }

    public function surveyDownload(Request $request, QualityControlSurvey $qualityControlSurvey): Response
    {
        return $this->survey($request, $qualityControlSurvey, 'attachment');
    }

    public function reportPreview(Request $request, string $token): Response
    {
        return $this->report($request, $token, 'inline');
    }

    public function reportDownload(Request $request, string $token): Response
    {
        return $this->report($request, $token, 'attachment');
    }

    /**
     * Copia publicada para WhatsApp: solo con firma vigente (vence a las 48 horas).
     */
    public function shared(Request $request, string $share): Response
    {
        $path = QualityControlPdfService::sharedPath($share);

        abort_if($path === null, 404);

        return response((string) Storage::disk(QualityControlPdfService::DISK)->get($path), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', 'quality-control.pdf'),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function survey(Request $request, QualityControlSurvey $survey, string $disposition): Response
    {
        $resource = QualityControlSurveyResource::forType($survey->affiliate_type);

        if (! $resource::canViewSurvey($survey)) {
            SecurityAudit::log('AUDIT_QUALITY_CONTROL_PDF_DENIED', 'operations.quality-control.pdf', ['survey_id' => $survey->getKey()]);

            abort(404);
        }

        return $this->pdfResponse($request, QualityControlPdfService::surveyDocument($survey), $disposition);
    }

    private function report(Request $request, string $token, string $disposition): Response
    {
        $pending = QualityControlReportRequest::find($token);

        abort_if($pending === null, 404);

        $type = QualityControlAffiliateType::from($pending['type']);

        abort_unless(QualityControlSurveyResource::forType($type)::canAccess(), 404);

        return $this->pdfResponse($request, QualityControlPdfService::reportDocument($type, $pending['ids'], $pending['label']), $disposition);
    }

    /**
     * @param  array{contents: string, fingerprint: string, filename: string}  $document
     */
    private function pdfResponse(Request $request, array $document, string $disposition): Response
    {
        $etag = '"'.$document['fingerprint'].'"';
        $headers = ['Cache-Control' => 'private, no-cache', 'ETag' => $etag];

        if (in_array($etag, $request->getETags(), true)) {
            return response('', Response::HTTP_NOT_MODIFIED, $headers);
        }

        return response($document['contents'], Response::HTTP_OK, [
            ...$headers,
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $document['filename']),
        ]);
    }
}
