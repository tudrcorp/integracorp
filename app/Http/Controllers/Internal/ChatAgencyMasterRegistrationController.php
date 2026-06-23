<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterChatAgencyMasterRequest;
use App\Services\PublicAiAgent\ChatAgencyMasterRegistrationService;
use Illuminate\Http\JsonResponse;

class ChatAgencyMasterRegistrationController extends Controller
{
    public function store(
        RegisterChatAgencyMasterRequest $request,
        ChatAgencyMasterRegistrationService $registrationService,
    ): JsonResponse {
        $result = $registrationService->register($request->validated());

        if ($result['success'] === true) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'] ?? [],
            ], 200);
        }

        $status = isset($result['errors']) ? 422 : 500;

        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'errors' => $result['errors'] ?? [],
        ], $status);
    }
}
