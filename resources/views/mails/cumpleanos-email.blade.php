<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif;">

    <!-- Contenedor principal centrado de 600px -->
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="
                    padding: 0px; 
                    background-color: #ffffff; 
                    border: 1px solid #e7e7e7; 
                    border-radius: 8px;
                ">
                <!-- Contenido interno con padding de 5px -->
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 0px;">
                            <img src="{{ config('parameters.PUBLIC_URL') . '/' . $file }}" style="display: block; width: 100%; max-width: 600px; height: auto;" alt="Felicidades">
                        </td>
                    </tr>
                    {{-- <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="https://app.piedy.com/images/BANER-GUSTAVO-2.png" alt="Banner Tu Dr. en Casa" style="max-width: 100%; height: auto; border-radius: 8px;">
                        </td>
                    </tr> --}}
                </table>
            </td>
        </tr>
    </table>

</body>

</html>
