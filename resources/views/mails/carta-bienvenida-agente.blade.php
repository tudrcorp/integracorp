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
        <img src="https://tudrenviajes.com/images/bannerBienvenidaAgente.png" alt="Logo Bancamiga" style="max-width: 100%;">
    </div>

    <div style="margin: auto; width: 600px; padding: 10px; text-align: center;">

        <p><span style="font-weight: bold; font-size: 1.2em;">¡Bienvenido(a) a TuDrEnCasa! 🌟</span> </p>

        <p>Estimado(a), {{ $name }}</p>
        <p>Nos complace darte la más cálida bienvenida a Gustavo Camacho , nuestro nuevo agente quien desde hoy inicia sus labores en nuestra empresa. Estamos seguros de que su experiencia y entusiasmo serán un gran aporte para el equipo.</p>
        <p>En este correo encontrarás adjunta tu carta de bienvenida , donde te damos la recepción oficial y te presentamos como parte del equipo.</p>
        <br>
        <p>Atentamente,</p>
    </div>

    <div class="footer">
        <img src="https://app.piedy.com/images/BANER-GUSTAVO-2.png" alt="Logo Tubanca" style="max-width: 100%;">
        <p style="font-size: 0.8em; font-style: italic;">Gracias por confiar en nosotros para gestionar las necesidades médicas de tu empresa</p>
    </div>
</body>
</html>


