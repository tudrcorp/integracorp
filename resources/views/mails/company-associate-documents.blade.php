<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarjeta de afiliado</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; padding: 24px;">
    <div style="max-width: 640px; margin: 0 auto;">
        <p style="font-size: 18px; font-weight: bold; color: #305B93;">Tu tarjeta de afiliado está lista</p>

        <p>Estimado(a) <strong>{{ $recipientName }}</strong>,</p>

        <p>
            Su registro en <strong>Tu Doctor Group</strong> fue procesado correctamente.
            Adjuntamos su <strong>tarjeta de afiliado</strong> y el <strong>código QR</strong> del plan INCLUSIÓN.
        </p>

        <p>
            Vigencia de la tarjeta:
            <strong>{{ $validity['desde'] }}</strong>
            @if (filled($validity['hasta']) && $validity['hasta'] !== $validity['desde'])
                al <strong>{{ $validity['hasta'] }}</strong>
            @endif
        </p>

        <p>Conserve ambos documentos para su atención y presentación cuando sea requerido.</p>

        <p style="margin-top: 32px; font-size: 14px; color: #6b7280;">
            Tu Doctor Group · INTEGRACORP
        </p>
    </div>
</body>
</html>
