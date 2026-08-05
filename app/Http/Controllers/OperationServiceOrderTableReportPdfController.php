<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OperationServiceOrderTableReportPdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperationServiceOrderTableReportPdfController extends Controller
{
    public function preview(Request $request): Response
    {
        return $this->respond($request, inline: true);
    }

    public function download(Request $request): Response
    {
        return $this->respond($request, inline: false);
    }

    private function respond(Request $request, bool $inline): Response
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $token = $request->query('token');
        $type = (string) $request->query('type', OperationServiceOrderTableReportPdfService::TYPE_BY_PATIENT);

        if (! is_string($token) || $token === '') {
            abort(400, 'Token de reporte no válido o expirado.');
        }

        if (! OperationServiceOrderTableReportPdfService::isValidType($type)) {
            abort(400, 'Tipo de reporte no válido.');
        }

        $ids = OperationServiceOrderTableReportPdfService::pullIdsFromToken($token);

        if ($ids === null) {
            abort(400, 'Token de reporte no válido o expirado.');
        }

        $binary = OperationServiceOrderTableReportPdfService::make($type, $ids)->output();
        $filename = OperationServiceOrderTableReportPdfService::filename($type);
        $disposition = $inline ? 'inline' : 'attachment';

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $disposition.'; filename="'.$filename.'"');
    }
}
