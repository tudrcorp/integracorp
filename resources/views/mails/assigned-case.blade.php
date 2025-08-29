<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif;">

    <!-- Contenedor principal centrado de 600px -->
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 700px; margin: 0 auto;">
        <tr>
            <td style="
                    padding: 5px; 
                    background-color: #ffffff; 
                    border: 1px solid #e7e7e7; 
                    border-radius: 8px;
                ">
                <!-- Contenido interno con padding de 5px -->
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="https://app.piedy.com/images/BANER-GUSTAVO-1.png" alt="Banner Tu Dr. en Casa" style="max-width: 100%; height: auto; border-radius: 8px;">
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 15px 10px; color: #333333; font-size: 12px; line-height: 1.6;">
                            <h1 style="color: #2c3e50; font-size: 22px; margin: 0 0 10px 0;">NOTIFICACIÓN</h1>
                            <p style="margin: 0; color: #555555;">
                                Hemos preparado tu cotización interactiva. Haz clic en el botón de abajo para ver todos los detalles.
                            </p>
                            <p><strong>¡Hola Dr. {{ $name }}! 👋</strong></p>
                            <p>Te informamos con gusto que el caso <strong>#{{ $code }}</strong> acaba de ser asignado a tu equipo de atención.</p>
                            <p>Paciente: <strong>{{ $name_patient }}</strong></p>
                            <p>Motivo: {{ $reason }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 15px 10px; font-size: 12px; color: #aaaaaa;">
                            Gracias por confiar en nosotros para gestionar las necesidades médicas de tu empresa
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="https://app.piedy.com/images/BANER-GUSTAVO-2.png" alt="Banner Tu Dr. en Casa" style="max-width: 100%; height: auto; border-radius: 4px;">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>

</html>

