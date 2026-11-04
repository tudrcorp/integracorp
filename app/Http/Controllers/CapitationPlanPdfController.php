<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CapitationPlanPdfRequest;
use App\Services\CapitationPlanPdfService;
use App\Support\SecurityAudit;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Vista previa y descarga del PDF de uno o varios planes de capitado (Negocios).
 */
class CapitationPlanPdfController extends Controller
{
    public function __invoke(CapitationPlanPdfRequest $request): BinaryFileResponse
    {
        abort_if($request->exceedsLimit(), 422, 'Un reporte admite hasta '.CapitationPlanPdfService::MAX_PLANS.' planes.');

        $ids = $request->planIds();
        $plans = CapitationPlanPdfService::plans($ids);

        /** Un plan eliminado o inexistente en la lista invalida el documento completo. */
        abort_if($plans->count() !== count($ids), 404, 'Alguno de los planes ya no existe.');

        @set_time_limit(120);

        $path = CapitationPlanPdfService::path($plans);
        $filename = CapitationPlanPdfService::filename($plans);
        $download = $request->wantsDownload();

        SecurityAudit::log(
            $download ? 'AUDIT_CAPITATION_PLAN_PDF_DOWNLOADED' : 'AUDIT_CAPITATION_PLAN_PDF_VIEWED',
            'business.capitation-plans.pdf',
            ['capitation_plan_ids' => $ids, 'filename' => $filename],
        );

        $headers = [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $download
            ? response()->download($path, $filename, $headers)
            : response()->file($path, [...$headers, 'Content-Disposition' => 'inline; filename="'.$filename.'"']);
    }
}
