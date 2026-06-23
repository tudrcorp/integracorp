<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Services\PublicAiAgent\AgentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicChatController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $session = ChatSession::startPublic(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'session_token' => $session->public_token,
            'state' => $session->current_state,
            'intent' => $session->detected_intent,
            'handoff_requested' => $session->handoff_requested,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function message(Request $request, AgentOrchestrator $orchestrator): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:3000'],
            'action_key' => ['nullable', 'string', 'max:80'],
        ]);

        $session = $this->resolveSession(
            sessionToken: $validated['session_token'] ?? null,
            request: $request,
        );

        $result = $orchestrator->processUserMessage(
            $session,
            (string) $validated['message'],
            isset($validated['action_key']) ? (string) $validated['action_key'] : null,
        );
        $session->refresh();

        return response()->json([
            'session_token' => $session->public_token,
            'reply' => $result['reply'],
            'state' => $result['state'],
            'intent' => $result['intent'],
            'handoff_requested' => $result['handoff_requested'],
            'tool_runs' => $result['tool_runs'],
            'external_redirect_url' => $result['external_redirect_url'] ?? null,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => ['required', 'string', 'max:80'],
        ]);

        $session = ChatSession::query()
            ->where('public_token', $validated['session_token'])
            ->firstOrFail();

        $messages = $session->messages()
            ->select(['id', 'role', 'content', 'tool_name', 'created_at'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'session_token' => $session->public_token,
            'state' => $session->current_state,
            'intent' => $session->detected_intent,
            'handoff_requested' => $session->handoff_requested,
            'messages' => $messages,
        ]);
    }

    private function resolveSession(?string $sessionToken, Request $request): ChatSession
    {
        if (is_string($sessionToken) && $sessionToken !== '') {
            $existingSession = ChatSession::query()
                ->where('public_token', $sessionToken)
                ->first();

            if ($existingSession !== null) {
                return $existingSession;
            }
        }

        return ChatSession::startPublic(
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
