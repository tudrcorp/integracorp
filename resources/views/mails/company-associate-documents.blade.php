<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjeta de afiliado</title>
</head>
<body style="margin:0; padding:0; background:#f3f6fb; font-family:Arial, Helvetica, sans-serif;">
    @php
        $logoPath = public_path('image/logoNewPdf.png');

        if (! file_exists($logoPath)) {
            $logoPath = public_path('image/logoNewTDG.png');
        }

        $logoSrc = isset($message) && file_exists($logoPath)
            ? $message->embed($logoPath)
            : asset('image/logoNewPdf.png');
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f6fb; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px; background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:28px 24px 18px 24px; background:linear-gradient(180deg, #f9fafb 0%, #ffffff 100%);">
                            <img
                                src="{{ $logoSrc }}"
                                alt="Tu Doctor Group"
                                width="220"
                                style="max-width:220px; width:100%; height:auto; display:block; margin:0 auto; border:0; outline:none; text-decoration:none;"
                            >
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:10px 28px 4px 28px;">
                            <h1 style="margin:0; font-size:24px; line-height:1.25; color:#0f172a; font-weight:700;">
                                Tu tarjeta de afiliado está lista
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:10px 28px 0 28px; color:#334155; font-size:15px; line-height:1.65;">
                            <p style="margin:0 0 12px 0;">
                                Estimado(a) <strong style="color:#0f172a;">{{ $recipientName }}</strong>,
                            </p>
                            <p style="margin:0 0 12px 0;">
                                Su registro en <strong>Tu Doctor Group</strong> fue procesado correctamente.
                                Adjuntamos su <strong>tarjeta de afiliado</strong> y el <strong>código QR</strong> del plan INCLUSIÓN.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 0 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                                <tr>
                                    <td style="padding:14px 16px; border-bottom:1px solid #e2e8f0; font-size:13px; font-weight:700; color:#0f172a;">
                                        Resumen del afiliado
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                            <tr>
                                                <td style="padding:6px 0; width:38%; color:#64748b; font-size:13px;">Nombre</td>
                                                <td style="padding:6px 0; color:#0f172a; font-size:14px; font-weight:600;">{{ $associate->full_name }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#64748b; font-size:13px;">Documento</td>
                                                <td style="padding:6px 0; color:#0f172a; font-size:14px;">{{ $associate->identity_card }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#64748b; font-size:13px;">Plan</td>
                                                <td style="padding:6px 0; color:#0f172a; font-size:14px;">INCLUSIÓN</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0; color:#64748b; font-size:13px;">Vigencia</td>
                                                <td style="padding:6px 0; color:#0f172a; font-size:14px; font-weight:600;">
                                                    {{ $validity['desde'] }}
                                                    @if (filled($validity['hasta']) && $validity['hasta'] !== $validity['desde'])
                                                        al {{ $validity['hasta'] }}
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 0 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:14px 16px; border-radius:12px; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; font-size:14px; line-height:1.6;">
                                        <strong style="color:#1d4ed8;">Documentos adjuntos</strong><br>
                                        En este correo encontrará su tarjeta de afiliado en PDF y el código QR de inclusión.
                                        También recibirá ambos documentos por <strong>WhatsApp</strong> al número registrado.
                                        Conserve ambos documentos para su atención y presentación cuando sea requerido.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:28px 28px 8px 28px; color:#64748b; font-size:12px; line-height:1.6;">
                            Si tiene alguna consulta, comuníquese con su responsable de empresa o con el equipo de Tu Doctor Group.
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:0 28px 28px 28px; color:#94a3b8; font-size:12px; line-height:1.5;">
                            Tu Doctor Group · INTEGRACORP
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
