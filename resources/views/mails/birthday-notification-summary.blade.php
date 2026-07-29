<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de tarjetas de cumpleaños</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f5f7;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto;">
        <tr>
            <td style="padding: 5px; background-color: #ffffff; border: 1px solid #e7e7e7; border-radius: 8px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding: 20px 10px;">
                            <img src="{{ config('parameters.PUBLIC_URL', config('app.url')).'/logoNewPdfTDEC.png' }}" alt="Tu Dr. en Casa" style="max-width: 100%; height: auto; border-radius: 8px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 20px; color: #333333; font-size: 14px; line-height: 1.6;">
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">Resumen de tarjetas de cumpleaños</h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Generado el {{ $generatedAt }}</p>
                            <p style="margin: 0 0 16px; color: #555555;">
                                Este correo corresponde al <strong>resumen de ejecución</strong> de la tarea diaria. No es una copia testigo de cada tarjeta enviada.
                            </p>
                            <pre style="margin: 0; padding: 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; white-space: pre-wrap; word-break: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px; line-height: 1.5; color: #111827;">{{ $summaryMessage }}</pre>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
