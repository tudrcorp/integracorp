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
<body>
    <div class="header">
        <img src="https://tudrenviajes.com/images/bannerCartaBienvenidaTop.png" alt="Banner de Bienvenida a Integracorp" style="max-width: 100%;">
    </div>

    <div style="margin: auto; width: 550px; padding: 10px; text-align: center;">
        <p><span style="font-weight: bold; font-size: 1.2em;">¡Bienvenido(a) a TuDrGroup! 🌟</span> </p>
        <p>Estimado(a), {{ $name }}</p>
        <p>Como nuevo agente, te unes a una labor vital, la de ser el pilar que nuestros clientes necesitan para proteger lo más valioso, su salud y la de su grupo familiar.</p>
        <p>Tu compromiso será fundamental para guiar a las personas hacia la mejor protección y darles la tranquilidad que necesitan.</p>
        <p>Te extendemos todo nuestro apoyo, recursos y la experiencia de nuestro equipo para que no solo alcances tus metas, sino que las superes. ¡Juntos, transformamos el mundo!</p>
        <p>En este correo encontrarás adjunta tu carta de bienvenida , donde te damos la recepción oficial y te presentamos como parte del equipo.</p>
        <!-- 
            Usuario: soleydarodriguez1@gmail.com
            Clave: 12345678
            URL: https://tudrencasa.com

            ¡INGRESE EN LA OPCIÓN DEL MENU COMERCIAL, ASOCIADO A SU ROL DENTRO DE NUESTRA PÁGINA WEB!

            Contáctanos para mayor información.
            📱 WhatsApp: (+58) 424 227 1498
            ✉️ Email: comercial@tudrencasa.com

        -->
        <p>Usuario: soleydarodriguez1@gmail.com</p>
        <p>Clave: 12345678</p>
        <p>URL: https://tudrencasa.com</p>
        <p>¡INGRESE EN LA OPCIÓN DEL MENU COMERCIAL, ASOCIADO A SU ROL DENTRO DE NUESTRA PÁGINA WEB!</p>
        <p>Contáctanos para mayor información.</p>
        <p>📱 WhatsApp: (+58) 424 227 1498</p>
        <p>✉️ Email: comercial@tudrencasa.com</p>
        <p>Atentamente,</p>
    </div>

    <div class="footer">
        {{-- <img src="https://tudrenviajes.com/images/footer_pre_imagen.jpg" alt="Logo TuDrEnCasa" style="max-width: 100%;"> --}}
        <img src="https://app.piedy.com/images/BANER-GUSTAVO-2.png" alt="Logo TuDrEnCasa" style="max-width: 100%;">
        <p style="font-size: 0.8em; font-style: italic;">Gracias por confiar en nosotros para gestionar las necesidades médicas de tu empresa</p>
    </div>
</body>
</html>


