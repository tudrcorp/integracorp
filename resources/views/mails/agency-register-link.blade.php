<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Agencia</title>
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
                            <img
                                src="{{ $logoSrc }}"
                                alt="{{ config('app.name') }}"
                                width="220"
                                style="max-width:220px; width:100%; height:auto; display:block; margin:0 auto; border:0; outline:none; text-decoration:none;"
                            >
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:10px 28px 4px 28px;">
                            <h1 style="margin:0; font-size:24px; line-height:1.25; color:#0f172a; font-weight:700;">
                                Link de registro directo
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:10px 28px 0 28px; color:#334155; font-size:15px; line-height:1.65;">
                            <p style="margin:0 0 12px 0;">Estimado(a) Aliado(a),</p>
                            <p style="margin:0 0 12px 0;">
                                Nos complace recibirte como parte de nuestra comunidad corporativa. En Tu Dr. en Casa,
                                estamos comprometidos con brindarte una experiencia excepcional en salud y atención médica personalizada.
                            </p>
                            <p style="margin:0;">
                                Para comenzar, accede al formulario y completa tu registro de agencia.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:26px 28px 8px 28px;">
                            <a
                                href="{{ $content['link'] }}"
                                target="_blank"
                                style="display:inline-block; background:#0ea5e9; color:#ffffff; text-decoration:none; font-weight:700; font-size:15px; line-height:1; padding:14px 24px; border-radius:999px;"
                            >
                                Ir al formulario de registro
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:10px 28px 24px 28px; color:#64748b; font-size:12px; line-height:1.6;">
                            Una segunda opción de acceder es: copia y pega este enlace en tu navegador.<br>
                            <a href="{{ $content['link'] }}" target="_blank" style="color:#0369a1; word-break:break-all;">{{ $content['link'] }}</a>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;">
                    <tr>
                        <td align="center" style="padding:12px 18px 0 18px; color:#94a3b8; font-size:12px; line-height:1.5;">
                            Gracias por confiar en nosotros para gestionar las necesidades médicas de tus clientes.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

