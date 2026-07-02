<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\CompanyAssociate;
use App\Support\RunReportMessageFormatter;

final class CompanyAssociateRegistrationNotificationMessage
{
    public static function whatsappBody(CompanyAssociate $associate): string
    {
        $lines = [
            '*NUEVO ASOCIADO REGISTRADO · INTEGRACORP*',
            '',
            'Se registró un nuevo asociado desde el enlace público de nuevos negocios.',
            '',
            '*Empresa*',
            '• Nombre: '.self::value($associate->company?->name),
            '• RIF: '.self::value($associate->company?->rif),
            '',
            '*Responsable*',
            '• Nombre: '.self::value($associate->responsible?->full_name),
            '• Cédula: '.self::value($associate->responsible?->identity_card),
            '',
            '*Asociado*',
            '• Nombre: '.self::value($associate->full_name),
            '• Cédula: '.self::value($associate->identity_card),
            '• Edad: '.($associate->age !== null ? $associate->age.' años' : '—'),
            '• Sexo: '.self::value($associate->sex),
            '• Fecha nacimiento: '.($associate->birth_date?->format('d/m/Y') ?? '—'),
            '• Correo: '.self::value($associate->email),
            '• Teléfono: '.self::value($associate->phone),
            '• Registrado el: '.($associate->registered_at?->format('d/m/Y H:i:s') ?? '—'),
            '',
            '*Contacto de emergencia*',
            '• Nombre: '.self::value($associate->contact_full_name),
            '• Teléfono: '.self::value($associate->contact_phone),
            '• Correo: '.self::value($associate->contact_email),
            '',
            '⚠️ *ACCIÓN REQUERIDA:* Debe iniciar la gestión del *voucher ILS* para activar el plan del asociado.',
            '',
            'Ingrese a INTEGRACORP → Nuevos Negocios → Asociados.',
        ];

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    /**
     * @return array<string, mixed>
     */
    public static function emailPayload(CompanyAssociate $associate): array
    {
        return [
            'associate' => $associate,
            'company' => $associate->company,
            'responsible' => $associate->responsible,
            'panelUrl' => CompanyAssociatesTableContext::associateViewUrl($associate),
            'generatedAt' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    public static function emailSubject(CompanyAssociate $associate): string
    {
        return 'Nuevo asociado registrado · '.$associate->full_name.' · INTEGRACORP';
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
