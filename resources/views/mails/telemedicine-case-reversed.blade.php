<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reverso de caso de telemedicina</title>
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
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto;">
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
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">Reverso de caso de telemedicina</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Notificación generada el {{ $generatedAt ?? '—' }}</p>
                            <p style="margin: 0 0 16px; color: #555555;">
                                Un médico revirtió el caso <strong>#{{ $case_code ?? '—' }}</strong>.
                                El caso fue <strong>eliminado</strong> para que los analistas de TDG o del proveedor puedan reasignarlo.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #fecaca; background-color: #fef2f2; font-weight: bold; color: #991b1b;">
                                        Nota / observación del médico
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 12px; border: 1px solid #fecaca; background-color: #fff7ed; color: #9a3412; font-size: 15px; line-height: 1.5; white-space: pre-wrap;">
                                        {{ $reversal_note ?? '—' }}
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Caso</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Código</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $case_code ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Estado al reversar</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $status ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Gestión</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $managed_by ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Prioridad</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $priority ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Motivo original</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $reason ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Asignado por</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $assigned_by ?? '—' }}</td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Paciente</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Nombre</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $patient_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Teléfono</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $patient_phone ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Edad / Sexo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $patient_age ?? '—' }} / {{ $patient_sex ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Dirección</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $patient_address ?? '—' }}</td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #f3f4f6; font-weight: bold; color: #111827;">Médico que revirtió</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Médico</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $doctor_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Usuario</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $reversed_by ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Fecha</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $reversed_at ?? '—' }}</td>
                                </tr>
                            </table>

                            <p style="margin: 0; padding: 12px; background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; color: #92400e; font-size: 13px;">
                                <strong>Acción requerida:</strong> reasigne el paciente desde Operaciones (TDG o proveedor).
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
