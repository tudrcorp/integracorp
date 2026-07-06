<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterChatAgentRequest;
use App\Services\PublicAiAgent\ChatAgentRegistrationService;
use Illuminate\Http\JsonResponse;

class ChatAgentRegistrationController extends Controller
{
    public function store(
        RegisterChatAgentRequest $request,
        ChatAgentRegistrationService $registrationService,
    ): JsonResponse {
        $payload = $request->validated();

        if (! isset($payload['owner_code']) || $payload['owner_code'] === '') {
            $payload['owner_code'] = $registrationService->resolveOwnerCode($payload);
        }

        $result = $registrationService->register($payload);

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
