<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalUsageAccessContext;
use App\Models\User;

final class ClinicalUsageAccessNotificationMessage
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(
        User $analyst,
        ClinicalUsageAccessContext $context,
        string $code,
        int $ttlMinutes,
        ?string $subjectLabel = null,
    ): array {
        return [
            'generatedAt' => now()->format('d/m/Y H:i:s'),
            'otp_code' => $code,
            'ttl_minutes' => $ttlMinutes,
            'analyst_name' => $analyst->name,
            'analyst_email' => $analyst->email,
            'context_label' => $context->label(),
            'subject_label' => $subjectLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function emailSubject(array $payload): string
    {
        return 'Clave OTP · ambiente restrictivo · '.($payload['context_label'] ?? 'uso clínico');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function whatsappBody(array $payload): string
    {
        $subject = filled($payload['subject_label'] ?? null)
            ? (string) $payload['subject_label']
            : '—';

        return implode("\n", [
            '*CLAVE OTP · AMBIENTE RESTRICTIVO INTEGRACORP*',
            '',
            'Un analista pide entrar a configurar uso clínico. Dicte esta clave solo a esa persona. Un solo uso; si sale de la vista debe pedir otra.',
            '',
            '*Clave:* '.$payload['otp_code'],
            '*Vence en:* '.$payload['ttl_minutes'].' minutos',
            '',
            '*Analista:* '.$payload['analyst_name'],
            '*Correo:* '.($payload['analyst_email'] ?: '—'),
            '*Vista:* '.$payload['context_label'],
            '*Registro:* '.$subject,
        ]);
    }
}
