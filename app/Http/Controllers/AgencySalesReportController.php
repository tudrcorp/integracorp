<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Services\AgencySalesReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AgencySalesReportController extends Controller
{
    public function __invoke(Request $request): Response|StreamedResponse
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(400, 'Token de reporte no válido o expirado.');
        }

        $params = AgencySalesReportService::pullParamsFromToken($token);

        if ($params === null || $params['agency_id'] <= 0) {
            abort(400, 'Token de reporte no válido o expirado.');
        }

        $agency = Agency::query()->find($params['agency_id']);

        if (! $agency instanceof Agency) {
            abort(404, 'Agencia no encontrada.');
        }

        return AgencySalesReportService::download($agency, $params);
    }
}
