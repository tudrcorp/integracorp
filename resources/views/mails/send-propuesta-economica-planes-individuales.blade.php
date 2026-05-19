<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propuesta Economica</title>
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
                                Propuesta Economica
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:10px 28px 24px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            <p style="margin:0 0 12px 0;">Estimado(a), {{ $titular }}</p>
                            <p style="margin:0 0 12px 0;">
                                Agradecemos su interés en nuestros Planes Individuales. A continuación, encontrará adjunto al presente correo el documento con la cotización detallada de las coberturas y tarifas correspondientes. No dude en contactarnos si tiene
                                alguna duda o requiere información adicional.
                            </p>
                            <p style="margin:0 0 12px 0;">
                                Si tiene alguna duda o requiere informacion adicional...
                            </p>
                            <p>Contáctanos para mayor información.</p>
                            <p>📱 WhatsApp: (+58) 424 227 1498</p>
                            <p>✉️ Email: comercial@tudrencasa.com</p>
                            <p style="margin:0;">Atentamente</p>
                            <p>Departamento Comercial</p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;">
                    <tr>
                        <td align="center" style="padding:12px 18px 0 18px; color:#94a3b8; font-size:12px; line-height:1.5;">
                            Gracias por confiar en nosotros para su proteccion y bienestar.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

