<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $headline }}</title>
</head>

<body style="margin:0; padding:0; background:#f3f6fb; font-family:Arial, Helvetica, sans-serif;">
    @php
        $logoPath = public_path('image/logoNewPdf.png');
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
                            <img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" width="220" style="max-width:220px; width:100%; height:auto; display:block; margin:0 auto; border:0; outline:none; text-decoration:none;">
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{{ $accentColor }}14; border:1px solid {{ $accentColor }}; border-radius:12px;">
                                <tr>
                                    <td align="center" style="padding:14px 18px; color:{{ $accentColor }}; font-size:16px; font-weight:bold;">
                                        {{ $headline }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 28px 8px 28px; color:#334155; font-size:15px; line-height:1.6;">
                            <p style="margin:0 0 12px 0;">{{ $intro }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 28px 12px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                @foreach ($details as $label => $value)
                                    <tr>
                                        <td style="padding:8px 10px; border-bottom:1px solid #eef2f7; color:#64748b; font-size:13px; width:42%; vertical-align:top;">{{ $label }}</td>
                                        <td style="padding:8px 10px; border-bottom:1px solid #eef2f7; color:#0f172a; font-size:14px; font-weight:bold; white-space:pre-wrap;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:6px 28px 26px 28px; color:#334155; font-size:14px; line-height:1.6;">
                            <p style="margin:0;">Puedes revisar el detalle completo en el panel de Negocios → Configuración → Guia-Chat.</p>
                            <p style="margin:16px 0 0 0;">Atentamente,<br>Tu Dr. en Casa</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
