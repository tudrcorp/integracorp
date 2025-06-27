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
            padding: 20px;
            text-align: center;
        }

        .content {
            margin-top: 20px;
            text-align: justify;
            /* Justifica el texto */
        }


        .footer {
            margin-top: 40px;
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
        <img src="https://app.piedy.com/images/BANER-GUSTAVO-1.png" alt="Logo Bancamiga" style="max-width: 100%;">
    </div>

    <div style="margin: auto; width: 600px; padding: 10px; text-align: center;">
        <p><span style="font-weight: bold; font-size: 1.2em;">¡Bienvenido(a) a TuDrEnCasa! 🌟</span> </p>

        <p>Estimado(a): <span style="font-weight: bold">{{ $titular }}</span> ,</p>
        {{-- <p>Estimado(a): <span style="font-weight: bold">{{ $titular['full_name_ti'] }}</span> ,</p> --}}

        <p>¡Nos complace darte la más cordial bienvenida🌟, queremos informarte que tu preafiliación ha sido procesada con éxito, y estamos muy contentos de tenerte como parte de nuestra comunidad.

            Adjunto a este correo encontrarás tu Certificado de Afiliación , un documento importante que acredita tu incorporación a nuestros servicios. Por favor, guárdalo en un lugar seguro y no dudes en contactarnos si necesitas ayuda para revisarlo o interpretarlo.

            <br> En TuDrEnCasa, estamos comprometidos en brindarte una atención cercana, personalizada y de calidad. Nuestro equipo estará siempre disponible para acompañarte en cada paso y asegurarnos de que recibas los mejores beneficios según el plan que has elegido. </p>

        <p>Una vez más, ¡bienvenido/a! Esperamos ser parte de tu bienestar y el de tu familia. Juntos vamos a cuidar lo que más importa. 💙 </p>
    </div>

    <div class="footer">
        <img src="https://app.piedy.com/images/BANER-GUSTAVO-2.png" alt="Logo Tubanca" style="max-width: 100%;">
        <p style="font-size: 0.8em; font-style: italic;">Gracias por confiar en nosotros para gestionar las necesidades médicas de tu empresa</p>
    </div>
</body>
</html>

