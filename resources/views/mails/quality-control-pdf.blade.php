<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTitle }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937;">
    <p>Estimado(a),</p>
    <p>Adjunto encontrará el documento <strong>{{ e($documentTitle) }}</strong>, generado por el departamento de operaciones de <strong>Tu Doctor en Casa</strong>@if (filled($senderName)) y enviado por {{ e($senderName) }}@endif.</p>
    <p>Si tiene alguna consulta, puede responder a este correo.</p>
    <p style="color: #6b7280; font-size: 12px;">Este documento contiene datos de pacientes. Por favor no lo comparta con terceros no autorizados.</p>
    @include('mails.partials.tdg-corporate-footer')
</body>
</html>
