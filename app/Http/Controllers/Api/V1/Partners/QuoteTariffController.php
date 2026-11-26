<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Partners;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateApiPartner;
use App\Support\ApiPartners\QuoteTariffFeed;
use App\Support\TuDrQuote\QuoteFeeMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API de aliados — tarifas vigentes de los planes de salud. Solo lectura.
 *
 * Responde 304 cuando el cliente manda `If-None-Match` con la versión que ya tiene, así el
 * agente de ventas puede consultar en cada cotización sin volver a descargar la tabla.
 * La autenticación, permisos, límite y bitácora los aplica {@see AuthenticateApiPartner}.
 */
class QuoteTariffController extends Controller
{
    public function index(Request $request): JsonResponse|Response
    {
        $matrix = QuoteFeeMatrix::all();

        if ($matrix === []) {
            return AuthenticateApiPartner::error(503, 'tarifas_no_disponibles', 'No hay tarifas vigentes publicadas. No cotices hasta que vuelvan a estar disponibles.');
        }

        $version = QuoteTariffFeed::version($matrix);
        $etag = '"'.$version.'"';

        if (in_array($etag, $request->getETags(), true)) {
            AuthenticateApiPartner::recordCount($request, 0);

            return response()->noContent(304)->setEtag($version);
        }

        AuthenticateApiPartner::recordCount($request, count($matrix));

        return response()->json([
            'data' => QuoteTariffFeed::payload($matrix),
            'meta' => [
                'version' => $version,
                'total' => count($matrix),
                'generado_en' => now()->toIso8601String(),
            ],
        ])->setEtag($version);
    }
}
