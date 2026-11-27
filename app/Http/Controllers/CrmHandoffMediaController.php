<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CrmInbox\CrmHandoffMediaIngress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Archivo que el cliente mandó por WhatsApp (imagen, documento o nota de voz), reenviado por n8n y firmado.
 */
class CrmHandoffMediaController extends Controller
{
    public function __invoke(Request $request, CrmHandoffMediaIngress $ingress): JsonResponse
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
