<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carnet de afiliado</title>
</head>
<body style="margin:0; padding:0; background:#f3f6fb; font-family:Arial, Helvetica, sans-serif; color:#111111;">
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
                        <td align="left" style="padding:10px 28px 24px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            <p style="margin:0 0 12px 0; font-weight:700; color:#111111;">Estimado(a) {{ $recipientName }},</p>
                            <p style="margin:0 0 18px 0; color:#111111;">Gusto en saludarle.</p>

                            <p style="margin:0 0 12px 0;">
                                Le escribimos para informarle que <strong>acaba de recibir su carnet de afiliado</strong> de Tu Doctor en Casa, correspondiente a la afiliación <strong>{{ $affiliationCode }}</strong>.
                            </p>
                            <p style="margin:0 0 12px 0;">
                                Adjunto a este correo encontrará:
                            </p>
                            <ol style="margin:0 0 12px 20px; padding:0; color:#111111;">
                                <li style="margin:0 0 8px 0;">
                                    <strong>Carnet de afiliado:</strong> su credencial digital de identificación. Le recomendamos conservarla e imprimirla. Con el código QR podrá acceder de inmediato a los contactos de emergencia y al protocolo de atención las 24 horas.
                                </li>
                                <li style="margin:0 0 8px 0;">
                                    <strong>Condicionado del plan:</strong> el documento con las condiciones, coberturas y alcances de su plan.
                                </li>
                            </ol>
                            <p style="margin:0 0 12px 0;">
                                Si necesita ayuda para revisar estos documentos o tiene alguna duda sobre su cobertura, nuestro equipo está a su disposición.
                            </p>

                            <p style="margin:0 0 8px 0; font-weight:700; color:#111111;">Departamento de Afiliaciones | Tu Doctor Group</p>
                            <p style="margin:0 0 8px 0; color:#111111;">WhatsApp: (+58) 424 222 0056 / 424 227 1498</p>
                            <p style="margin:0; color:#111111;">Email: <a href="mailto:afiliaciones@tudrencasa.com">afiliaciones@tudrencasa.com</a></p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;">
                    <tr>
                        <td align="center" style="padding:12px 18px 0 18px; color:#94a3b8; font-size:12px; line-height:1.5;">
                            &copy; 2026 Tu Doctor Group. <em>Tu Doctor en Casa</em> | <em>Tu Doctor en Viajes</em>.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
