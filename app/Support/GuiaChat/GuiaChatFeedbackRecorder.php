<?php

declare(strict_types=1);

namespace App\Support\GuiaChat;

use App\Models\ChatSession;
use App\Models\GuiaChatFeedback;

final class GuiaChatFeedbackRecorder
{
    public function record(
        string $type,
        string $message,
        ?ChatSession $session = null,
        ?string $reporterFirstName = null,
        ?string $reporterLastName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): GuiaChatFeedback {
        $feedbackType = GuiaChatFeedbackType::tryFromString($type);

        if ($feedbackType?->requiresReporterName()) {
            $reporterFirstName = trim((string) $reporterFirstName);
            $reporterLastName = trim((string) $reporterLastName);
        } else {
            $reporterFirstName = null;
            $reporterLastName = null;
        }

        $feedback = GuiaChatFeedback::query()->create([
            'type' => $type,
            'message' => trim($message),
            'reporter_first_name' => $reporterFirstName !== '' ? $reporterFirstName : null,
            'reporter_last_name' => $reporterLastName !== '' ? $reporterLastName : null,
            'chat_session_id' => $session?->getKey(),
            'public_token' => $session?->public_token,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        GuiaChatFeedbackNotifier::dispatchForFeedback((int) $feedback->getKey());

        return $feedback;
    }
}
