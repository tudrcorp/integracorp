<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Support\RunReportMessageFormatter;

final class TelemedicineCaseReversalNotificationMessage
{
    /**
     * @param  array{
     *     case_id: int,
     *     case_code: string,
     *     patient_name: string,
     *     patient_phone: string|null,
     *     patient_address: string|null,
     *     patient_age: string|null,
     *     patient_sex: string|null,
     *     reason: string|null,
     *     status: string|null,
     *     managed_by: string|null,
     *     assigned_by: string|null,
     *     doctor_name: string|null,
     *     priority: string|null,
     *     reversed_by: string,
     *     reversal_note: string,
     *     reversed_at: string,
     *     telemedicine_patient_id: int|null
     * }  $payload
     */
    public static function whatsappBody(array $payload): string
    {
        $lines = [
            '*REVERSO DE CASO · TELEMEDICINA*',
            '',
            'Un médico revirtió un caso. El registro fue eliminado para que analistas TDG o del proveedor puedan reasignarlo.',
            '',
            '*Caso*',
            '• Código: '.self::value($payload['case_code'] ?? null),
            '• Estado al reversar: '.self::value($payload['status'] ?? null),
            '• Gestión: '.self::value($payload['managed_by'] ?? null),
            '• Prioridad: '.self::value($payload['priority'] ?? null),
            '• Motivo original: '.self::value($payload['reason'] ?? null),
            '• Asignado por: '.self::value($payload['assigned_by'] ?? null),
            '',
            '*Paciente*',
            '• Nombre: '.self::value($payload['patient_name'] ?? null),
            '• Teléfono: '.self::value($payload['patient_phone'] ?? null),
            '• Edad / Sexo: '.self::value($payload['patient_age'] ?? null).' / '.self::value($payload['patient_sex'] ?? null),
            '• Dirección: '.self::value($payload['patient_address'] ?? null),
            '',
            '*Médico que revirtió*',
            '• Nombre: '.self::value($payload['doctor_name'] ?? null),
            '• Usuario: '.self::value($payload['reversed_by'] ?? null),
            '• Fecha: '.self::value($payload['reversed_at'] ?? null),
            '',
            '📝 *NOTA / OBSERVACIÓN DEL MÉDICO*',
            self::value($payload['reversal_note'] ?? null),
            '',
            '⚠️ *ACCIÓN REQUERIDA:* Reasigne el paciente desde Operaciones (TDG o proveedor).',
        ];

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function emailPayload(array $payload): array
    {
        return [
            ...$payload,
            'generatedAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function emailSubject(array $payload): string
    {
        $code = self::value($payload['case_code'] ?? null);

        return 'Reverso de caso '.$code.' · Telemedicina · INTEGRACORP';
    }

    public static function emailLogoPath(): string
    {
        $primaryLogo = public_path('image/logoNewPdf.png');

        if (file_exists($primaryLogo)) {
            return $primaryLogo;
        }

        return public_path('image/logoNewTDG.png');
    }

    private static function value(mixed $value): string
    {
        return filled($value) ? (string) $value : '—';
    }
}
