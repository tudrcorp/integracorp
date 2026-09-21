<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clave OTP fuera de límite</title>
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
                            <h2 style="margin: 0 0 8px; color: #1f2937; font-size: 18px;">Clave para un servicio fuera de límite</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Generada el {{ $generatedAt ?? '—' }}</p>
                            <p style="margin: 0 0 16px;">Dicte esta clave al médico. <strong>No se la envíe al paciente.</strong> Vence en {{ $ttl_minutes ?? 5 }} minutos y es de un solo uso.</p>

                            <p style="margin: 0 0 20px; padding: 16px; text-align: center; font-size: 32px; letter-spacing: 8px; font-weight: bold; background: #f8fafc; border: 1px dashed #052F60; color: #052F60;">
                                {{ $otp_code ?? '------' }}
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 16px;">
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; width: 35%; color: #6b7280;">Médico</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $doctor_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Paciente</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $patient_name ?? '—' }} · CI {{ $patient_ci ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Plan</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $plan_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Beneficio</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $benefit ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Servicio</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $service_name ?: ($channel ?? '—') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Cupo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $quota_label ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Motivo</td>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb; white-space: pre-wrap;">{{ $reason ?? '—' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
