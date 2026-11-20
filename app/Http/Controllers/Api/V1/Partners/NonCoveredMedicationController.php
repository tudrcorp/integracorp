<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Partners;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateApiPartner;
use App\Http\Requests\ApiPartners\NonCoveredMedicationIndexRequest;
use App\Support\ApiPartners\NonCoveredMedicationFeed;
use App\Support\Operations\MedicationInventoryStockStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de aliados — medicamentos NO CUBIERTOS en gestión. Solo lectura.
 * La autenticación, IPs, permisos, límite y bitácora los aplica {@see AuthenticateApiPartner}.
 */
class NonCoveredMedicationController extends Controller
{
    public function index(NonCoveredMedicationIndexRequest $request): JsonResponse
    {
        $page = NonCoveredMedicationFeed::paginate($request->filters());
        $data = NonCoveredMedicationFeed::present($page->items());

        AuthenticateApiPartner::recordCount($request, count($data));

        return response()->json([
            'data' => $data,
            'meta' => [
                'pagina_actual' => $page->currentPage(),
                'por_pagina' => $page->perPage(),
                'total' => $page->total(),
                'ultima_pagina' => $page->lastPage(),
                'estados_inventario' => MedicationInventoryStockStatus::values(),
                'generado_en' => now()->toIso8601String(),
            ],
        ]);
    }

    public function show(Request $request, int $medication): JsonResponse
    {
        $record = NonCoveredMedicationFeed::find($medication);

        if ($record === null) {
            AuthenticateApiPartner::recordCount($request, 0);

            return response()->json([
                'error' => 'no_encontrado',
                'mensaje' => 'No existe un medicamento no cubierto en gestión con ese identificador.',
            ], 404);
        }

        AuthenticateApiPartner::recordCount($request, 1);

        return response()->json([
            'data' => NonCoveredMedicationFeed::present([$record])[0],
            'meta' => ['generado_en' => now()->toIso8601String()],
        ]);
    }
}
