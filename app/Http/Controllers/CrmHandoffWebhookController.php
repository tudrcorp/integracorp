<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CrmInbox\CrmHandoffIngress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmHandoffWebhookController extends Controller
{
    public function __invoke(Request $request, CrmHandoffIngress $ingress): JsonResponse
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
