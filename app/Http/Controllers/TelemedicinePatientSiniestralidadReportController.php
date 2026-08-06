<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PatientSiniestralidadReportService;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TelemedicinePatientSiniestralidadReportController extends Controller
{
    public function previewPdf(Request $request): Response
    {
        return $this->respondPdf($request, inline: true);
    }

    public function downloadPdf(Request $request): Response
    {
        return $this->respondPdf($request, inline: false);
    }

    public function downloadCsv(Request $request): StreamedResponse
    {
        $this->ensureTdgAnalyst();

        $params = $this->paramsFromRequest($request);

        return PatientSiniestralidadReportService::streamCsv($params);
    }

    private function respondPdf(Request $request, bool $inline): Response
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $this->ensureTdgAnalyst();

        $params = $this->paramsFromRequest($request);
        $binary = PatientSiniestralidadReportService::makePdf($params)->output();
        $filename = PatientSiniestralidadReportService::pdfFilename($params['top_n']);
        $disposition = $inline ? 'inline' : 'attachment';

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disposition.'; filename="'.$filename.'"');
    }

    /**
     * @return array{top_n: int, date_from: string|null, date_to: string|null}
     */
    private function paramsFromRequest(Request $request): array
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(400, 'Token de reporte no válido o expirado.');
        }

        $params = PatientSiniestralidadReportService::pullParamsFromToken($token);

        if ($params === null) {
            abort(400, 'Token de reporte no válido o expirado.');
        }

        return $params;
    }

    private function ensureTdgAnalyst(): void
    {
        if (! OperationsSupplierScope::authenticatedUserIsTdgAnalyst()) {
            abort(403, 'Solo analistas TDG pueden generar el reporte de siniestralidad.');
        }
    }
}
