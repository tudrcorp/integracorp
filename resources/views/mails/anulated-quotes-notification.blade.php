<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizaciones anuladas</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto;">
        <tr>
            <td style="padding: 5px; background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="{{ config('parameters.PUBLIC_URL', config('app.url')).'/logoNewPdfTDEC.png' }}" alt="Tu Dr. en Casa" style="max-width: 100%; height: auto; border-radius: 8px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 15px 10px; color: #333333; font-size: 14px; line-height: 1.6;">
                            <p style="margin: 0 0 12px; color: #555555;">
                                Se informa el reporte diario de cotizaciones individuales generadas por el agente que fueron anuladas automáticamente.
                            </p>
                            <p style="margin: 0; color: #333333;">
                                <strong>Número de cotizaciones anuladas: {{ $anulatedCount }}</strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
