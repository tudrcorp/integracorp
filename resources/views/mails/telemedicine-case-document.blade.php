<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento de telemedicina</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            text-align: center;
        }

        .header {
            color: white;
            padding: 10px;
            text-align: center;
        }

        .content {
            margin-top: 10px;
            text-align: justify;
        }

        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 0.9em;
            color: #555;
        }

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .social-icons img {
            width: 40px;
            height: 40px;
        }

        ul {
            list-style-type: disc;
            padding-left: 20px;
        }

        li {
            margin-bottom: 10px;
        }
    </style>
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
                        <td align="center" style="padding:10px 28px 24px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            <p>Estimado(a) <strong>{{ $patientName }}</strong>,</p>
                            <p>Le informamos que en este correo va adjunto el documento <strong>{{ $documentName }}</strong>, generado en el marco de su atención de telemedicina.</p>
                            <p>Por favor, revíselo con atención y guárdelo de forma segura. Si tiene alguna duda, no dude en consultarnos.</p>
                            <p>Su salud es nuestra prioridad.</p>
                            <p>Atentamente,<br>Tu Dr. en Casa</p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px;">
                    <tr>
                        <td align="center" style="padding:12px 18px 0 18px; color:#94a3b8; font-size:12px; line-height:1.5;">
                            Gracias por confiar en nosotros para gestionar las necesidades médicas de tu empresa.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
