<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura {{ $invoiceNumber }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937;">
    <p>Estimado(a) {{ e($patientName) }},</p>
    <p>Adjunto encontrará la <strong>factura {{ e($invoiceNumber) }}</strong> por <strong>{{ e($totalLabel) }}</strong>, correspondiente a servicios no cubiertos por su plan que le prestó <strong>Tu Doctor en Casa</strong>.</p>
    <p>Estado de la factura: <strong>{{ e($statusLabel) }}</strong>.@if (filled($senderName)) Enviada por {{ e($senderName) }}.@endif</p>
    <p>Si tiene alguna consulta, puede responder a este correo.</p>
    <p style="color: #6b7280; font-size: 12px;">Documento interno, no constituye factura fiscal. Contiene datos personales: por favor no lo comparta con terceros.</p>
    @include('mails.partials.tdg-corporate-footer')
</body>
</html>
