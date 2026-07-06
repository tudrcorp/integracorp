<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterChatIndividualQuoteRequest;
use App\Services\PublicAiAgent\ChatIndividualQuoteService;
use Illuminate\Http\JsonResponse;

class ChatIndividualQuoteController extends Controller
{
    public function store(
        RegisterChatIndividualQuoteRequest $request,
        ChatIndividualQuoteService $quoteService,
    ): JsonResponse {
        $result = $quoteService->register($request->validated());

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
