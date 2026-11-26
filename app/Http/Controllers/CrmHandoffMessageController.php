<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CrmInbox\CrmHandoffMessageIngress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmHandoffMessageController extends Controller
{
    public function __invoke(Request $request, CrmHandoffMessageIngress $ingress): JsonResponse
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
