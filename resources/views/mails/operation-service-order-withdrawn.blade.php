<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de servicio retirada</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937;">
    <p>Estimado(a),</p>
    <p>Le informamos que la <strong>orden de servicio N° {{ e($orderNumber) }}</strong> ya no está a su cargo: fue reasignada a otro proveedor por el departamento de operaciones de <strong>Tu Doctor en Casa</strong>.</p>
    <p>Por favor no preste el servicio de esta orden. Si ya había coordinado algo con el paciente, responda a este correo para ayudarle.</p>
    <p style="color: #6b7280; font-size: 12px;">Este mensaje es informativo.</p>
    @include('mails.partials.tdg-corporate-footer')
</body>
</html>
