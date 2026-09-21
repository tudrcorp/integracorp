<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Models\TelemedicinePatient;
use App\Models\User;

final class ClinicalServiceOverrideNotificationMessage
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(
        User $doctor,
        TelemedicinePatient $patient,
        ClinicalEntitlement $entitlement,
        string $code,
        string $reason,
        int $ttlMinutes,
    ): array {
        return [
            'generatedAt' => now()->format('d/m/Y H:i:s'),
            'otp_code' => $code,
            'ttl_minutes' => $ttlMinutes,
            'doctor_name' => $doctor->name,
            'patient_name' => $patient->full_name,
            'patient_ci' => $patient->nro_identificacion,
            'plan_name' => $patient->plan?->description,
            'benefit' => $entitlement->benefitLabel,
            'channel' => $entitlement->channel->shortLabel(),
            'service_name' => $entitlement->telemedicineServiceListName,
            'quota_label' => $entitlement->helperText(),
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function emailSubject(array $payload): string
    {
        return 'Clave OTP · servicio fuera de límite · '.($payload['patient_name'] ?? 'paciente');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function whatsappBody(array $payload): string
    {
        $service = $payload['service_name'] ?: $payload['channel'];

        return implode("\n", [
            '*CLAVE OTP · SERVICIO FUERA DE LÍMITE*',
            '',
            'Dicte esta clave al médico. No se la envíe al paciente.',
            '',
            '*Clave:* '.$payload['otp_code'],
            '*Vence en:* '.$payload['ttl_minutes'].' minutos (un solo uso)',
            '',
            '*Médico:* '.$payload['doctor_name'],
            '*Paciente:* '.$payload['patient_name'].' · CI '.$payload['patient_ci'],
            '*Plan:* '.($payload['plan_name'] ?: '—'),
            '*Beneficio:* '.$payload['benefit'],
            '*Servicio:* '.$service,
            '*Cupo:* '.$payload['quota_label'],
            '',
            '*Motivo del médico:*',
            $payload['reason'],
        ]);
    }
}
