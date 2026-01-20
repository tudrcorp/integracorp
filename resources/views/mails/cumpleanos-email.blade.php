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
                    padding: 5px; 
                    background-color: #ffffff; 
                    border: 1px solid #e7e7e7; 
                    border-radius: 8px;
                ">
                <!-- Contenido interno con padding de 5px -->
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        {{-- <td align="center" style="padding: 20px 10px;">
                            <img src="{{ config('parameters.PUBLIC_URL') . '/' . $file }}" style="display: block; width: 100%; max-width: 600px; height: auto;" alt="Felicidades">
                        </td> --}}
                        <td align="center" style="padding: 20px 0;">
                            <!-- Contenedor con ancho máximo para asegurar proporcionalidad -->
                            <div
                                style="position: relative; width: 100%; max-width: 600px; margin: 0 auto; overflow: hidden; border-radius: 12px; shadow: 0 4px 15px rgba(0,0,0,0.1);">
                        
                                <!-- 1. IMAGEN DE FONDO (TARJETA) -->
                                <img src="{{ config('parameters.PUBLIC_URL') . '/' . $file }}"
                                    style="display: block; width: 100%; height: auto;" alt="Tarjeta de Cumpleaños">
                        
                                <!-- 2. CAPA DEL NOMBRE (FLOTANTE Y CURSIVA) -->
                                <!-- 
                                            Ajusta 'top' para subir o bajar el nombre.
                                            'font-size: 5vw' hace que el texto sea proporcional al ancho de la pantalla (responsive).
                                        -->
                                <div style="position: absolute; top: 45%; left: 0; width: 100%; text-align: center; pointer-events: none;">
                                    <span style="
                                                font-family: 'Dancing Script', 'Brush Script MT', 'cursive'; 
                                                font-size: 48px; 
                                                font-size: 8vw; 
                                                color: #ffffff; 
                                                font-style: italic;
                                                font-weight: normal;
                                                text-shadow: 2px 2px 8px rgba(0,0,0,0.4);
                                                display: inline-block;
                                                padding: 0 10px;
                                                line-height: 1.2;
                                            ">
                                        GUSTAVO CAMACHO
                                    </span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="https://app.piedy.com/images/BANER-GUSTAVO-2.png" alt="Banner Tu Dr. en Casa" style="max-width: 100%; height: auto; border-radius: 8px;">
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>

</html>
