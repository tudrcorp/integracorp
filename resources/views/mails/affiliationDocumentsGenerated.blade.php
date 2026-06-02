<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos de afiliación</title>
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
                                En primer lugar, queremos agradecer la excelente gestión comercial y la constante confianza que deposita en Tu Doctor en Casa al presentarnos como la solución de salud ideal para sus clientes.
                            </p>
                            <p style="margin:0 0 12px 0;">
                                Nos complace informarle que la emisión del plan de su afiliado, {{ $titular }}, ha sido procesada con éxito.
                            </p>
                            <p style="margin:0 0 12px 0;">
                                Adjunto a este correo encontrará la documentación formal correspondiente para que pueda ser entregada a su cliente:
                            </p>

                            <ol style="margin:0 0 12px 20px; padding:0; color:#111111;">
                                <li style="margin:0 0 8px 0;">
                                    <strong>Kit de Bienvenida:</strong> Resumen de beneficios, canales de atención directa e instrucciones para la activación de servicios.
                                </li>
                                <li style="margin:0 0 8px 0;">
                                    <strong>Certificado de Cobertura:</strong> Documento que formaliza la vigencia y el alcance del plan.
                                </li>
                                <li style="margin:0 0 8px 0;">
                                    <strong>Tarjeta del Afiliado:</strong> Credencial digital de identificación.
                                </li>
                            </ol>

                            <p style="margin:0 0 12px 0;">
                                Le recordamos sugerirle al afiliado que conserve e imprima su tarjeta de afiliado. A través del código QR integrado, ella podrá acceder de forma inmediata a nuestros contactos de emergencia y al protocolo de atención las 24 horas, los 365 días del año.
                            </p>
                            <p style="margin:0 0 12px 0;">
                                Agradecemos una vez más su valiosa alianza estratégica. Nuestro Departamento de Negocios permanece a su entera disposición para seguir respaldando sus cuentas con el estándar de excelencia que nos caracteriza.
                            </p>

                            <p style="margin:0 0 8px 0; font-weight:700; color:#111111;">💼 Departamento Comercial | Tu Doctor Group</p>
                            <p style="margin:0 0 8px 0; color:#111111;">📲 WhatsApp: (+58) 424 222 0056 / 424 227 1498</p>
                            <p style="margin:0; color:#111111;">📩 Email: afiliaciones@tudrencasa.com / <a href="mailto:comercial@tudrencasa.com">comercial@tudrencasa.com</a></p>
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
