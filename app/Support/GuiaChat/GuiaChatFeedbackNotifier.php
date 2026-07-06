<?php

declare(strict_types=1);

namespace App\Support\GuiaChat;

use App\Jobs\SendGuiaChatFeedbackMailJob;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\GuiaChatFeedback;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use Illuminate\Support\Facades\Log;

final class GuiaChatFeedbackNotifier
{
    private const EMAIL_TO = 'soporte@tudrencasa.com';

    private const EMAIL_CC = 'solrodriguez@tudrencasa.com';

    /**
     * @var array<int, string>
     */
    private const WHATSAPP_PHONES = [
        '04127018390',
        '04121931865',
        '04143027250',
    ];

    public static function dispatchForFeedback(int $feedbackId): void
    {
        $feedback = GuiaChatFeedback::query()->find($feedbackId);

        if (! $feedback instanceof GuiaChatFeedback) {
            Log::warning('GUIA-CHAT: feedback no encontrado para notificar.', ['feedback_id' => $feedbackId]);

            return;
        }

        SendGuiaChatFeedbackMailJob::dispatch($feedbackId);

        $caption = self::buildWhatsAppCaption($feedback);

        foreach (self::WHATSAPP_PHONES as $phone) {
            $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone);

            if ($normalized === null) {
                continue;
            }

            SendNotificacionWhatsApp::dispatch(
                null,
                $caption,
                $normalized,
                null,
                [
                    'panel' => 'business',
                    'context' => 'guia_chat_feedback',
                    'feedback_id' => $feedbackId,
                    'feedback_type' => $feedback->type,
                ],
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public static function buildDetails(GuiaChatFeedback $feedback): array
    {
        $type = $feedback->typeEnum();

        $details = [
            'Tipo' => $type?->label() ?? $feedback->type,
            'Fecha' => $feedback->created_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            'ID registro' => '#'.$feedback->getKey(),
        ];

        if ($feedback->requiresReporterName()) {
            $details['Nombre'] = (string) ($feedback->reporter_first_name ?? '—');
            $details['Apellido'] = (string) ($feedback->reporter_last_name ?? '—');
        }

        $details['Mensaje'] = (string) $feedback->message;

        if (filled($feedback->public_token)) {
            $details['Sesión chat'] = (string) $feedback->public_token;
        }

        return $details;
    }

    public static function buildWhatsAppCaption(GuiaChatFeedback $feedback): string
    {
        $type = $feedback->typeEnum();
        $typeLabel = $type?->label() ?? $feedback->type;
        $date = $feedback->created_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
        $message = mb_strlen((string) $feedback->message) > 900
            ? mb_substr((string) $feedback->message, 0, 900).'…'
            : (string) $feedback->message;

        $header = match ($type) {
            GuiaChatFeedbackType::ServiceSuggestion => '💡 *SUGERENCIA GUIA-CHAT*',
            GuiaChatFeedbackType::GuiaChatBug => '⚠️ *FALLA GUIA-CHAT*',
            GuiaChatFeedbackType::IntegracorpBug => '🚨 *FALLA INTEGRACORP*',
            default => '📩 *REGISTRO GUIA-CHAT*',
        };

        $lines = [
            $header,
            '',
            "*Tipo:* {$typeLabel}",
            "*Fecha:* {$date}",
            "*ID:* #{$feedback->getKey()}",
        ];

        if ($feedback->requiresReporterName()) {
            $lines[] = '*Reportado por:* '.trim((string) $feedback->reporter_first_name.' '.(string) $feedback->reporter_last_name);
        }

        $lines[] = '';
        $lines[] = '*Detalle:*';
        $lines[] = $message;

        return implode("\n", $lines);
    }

    public static function emailTo(): string
    {
        return self::EMAIL_TO;
    }

    public static function emailCc(): string
    {
        return self::EMAIL_CC;
    }
}
