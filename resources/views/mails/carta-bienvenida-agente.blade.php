<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            text-align: center;
            /* Centra todo el contenido */
        }


        .header {
            /* background-color: #00539C; */
            /* Azul oscuro */
            color: white;
            padding: 10px;
            text-align: center;
        }

        .content {
            margin-top: 10px;
            text-align: justify;
            /* Justifica el texto */
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
                        <td align="center" style="padding:10px 28px 4px 28px;">
                            <h1 style="margin:0; font-size:24px; line-height:1.25; color:#0f172a; font-weight:700;">
                                ¡Bienvenido(a) a TuDrGroup!
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:10px 28px 24px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            {{-- <p><span style="font-weight: bold; font-size: 1.2em;">¡Bienvenido(a) a TuDrGroup! 🌟</span> </p> --}}
                            <p>Estimado(a), {{ $name }}</p>
                            <p>Como nuevo agente, te unes a una labor vital, la de ser el pilar que nuestros clientes necesitan para proteger lo más valioso, su salud y la de su grupo familiar.</p>
                            <p>Tu compromiso será fundamental para guiar a las personas hacia la mejor protección y darles la tranquilidad que necesitan.</p>
                            <p>Te extendemos todo nuestro apoyo, recursos y la experiencia de nuestro equipo para que no solo alcances tus metas, sino que las superes. ¡Juntos, transformamos el mundo!</p>
                            <p>En este correo encontrarás adjunta tu carta de bienvenida , donde te damos la recepción oficial y te presentamos como parte del equipo.</p>
                            <p>Usuario: {{ $email }}</p>
                            <p>Clave: {{ $password }}</p>
                            <p>URL: https://tudrencasa.com</p>
                            <p>¡INGRESE EN LA OPCIÓN DEL MENU COMERCIAL, ASOCIADO A SU ROL DENTRO DE NUESTRA PÁGINA WEB!</p>
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
                            Gracias por confiar en nosotros para gestionar las necesidades médicas de tu empresa.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>


