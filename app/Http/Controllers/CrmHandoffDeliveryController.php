<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CrmInbox\CrmHandoffDeliveryIngress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Doble check de Atención WhatsApp: estados de entrega y lectura que manda el bot (firmados).
 */
class CrmHandoffDeliveryController extends Controller
{
    public function __invoke(Request $request, CrmHandoffDeliveryIngress $ingress): JsonResponse
    {
        $result = $ingress->accept(
            $request->getContent(),
            $request->header('X-Crm-Timestamp'),
            $request->header('X-Crm-Signature'),
            (string) $request->ip(),
        );

        return response()->json($result->body(), $result->httpStatus());
    }
}
