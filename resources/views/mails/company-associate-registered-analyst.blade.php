<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo asociado registrado</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f5f7;">
    @php
        $logoPath = public_path('image/logoNewPdf.png');

        if (! file_exists($logoPath)) {
            $logoPath = public_path('image/logoNewTDG.png');
        }

        $logoSrc = isset($message) && file_exists($logoPath)
            ? $message->embed($logoPath)
            : asset('image/logoNewPdf.png');
    @endphp
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 5px; background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="{{ $logoSrc }}" alt="INTEGRACORP" width="220" style="max-width: 220px; width: 100%; height: auto; display: block; margin: 0 auto; border: 0; border-radius: 8px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 20px; color: #333333; font-size: 14px; line-height: 1.6;">
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">Nuevo asociado registrado</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Notificación generada el {{ $generatedAt }}</p>
                            <p style="margin: 0 0 16px; color: #555555;">
                                Se registró un nuevo asociado desde el enlace público de <strong>nuevos negocios</strong>.
                                A continuación encontrará el detalle completo del registro, la empresa y el responsable al que pertenece.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Empresa</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $company?->name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">RIF</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $company?->rif ?? '—' }}</td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Responsable</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $responsible?->full_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Cédula</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $responsible?->identity_card ?? '—' }}</td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Asociado</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->full_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Cédula</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->identity_card }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Edad</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->age }} años</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Sexo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->sex }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Fecha de nacimiento</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->birth_date?->format('d/m/Y') ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Correo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->email ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Teléfono</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->phone ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Registrado el</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->registered_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Contacto de emergencia</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->contact_full_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Teléfono</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->contact_phone ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Correo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $associate->contact_email ?? '—' }}</td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px;">
                                <tr>
                                    <td style="padding: 14px 16px; border-radius: 8px; background-color: #fff7ed; border: 1px solid #fdba74; color: #9a3412; font-size: 14px; line-height: 1.6;">
                                        <strong style="color: #c2410c;">Acción requerida:</strong>
                                        Debe iniciar la gestión del <strong>voucher ILS</strong> del asociado para poder activar el plan.
                                        Ingrese a INTEGRACORP → Nuevos Negocios → Asociados y gestione el voucher desde el registro correspondiente.
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; text-align: center;">
                                <a href="{{ $panelUrl }}" style="display: inline-block; padding: 12px 20px; background-color: #0f172a; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 13px; font-weight: bold;">
                                    Ver asociado en INTEGRACORP
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
