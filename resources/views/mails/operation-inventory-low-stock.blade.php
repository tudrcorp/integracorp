<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta de stock bajo</title>
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
                            <h2 style="margin: 0 0 6px; color: #1f2937; font-size: 18px;">
                                {{ $immediate ?? false ? 'Alerta inmediata de stock bajo' : 'Alerta diaria de stock bajo' }}
                            </h2>
                            <p style="margin: 0 0 16px; color: #6b7280; font-size: 13px;">Generado el {{ $generatedAt }}</p>
                            <p style="margin: 0 0 16px; color: #555555;">
                                @if ($immediate ?? false)
                                    Un producto activo acaba de quedar con existencia total menor o igual a <strong>{{ $threshold }}</strong>.
                                    La existencia total es la suma de todos los almacenes.
                                @else
                                    Hay <strong>{{ count($products) }}</strong> producto(s) activo(s) con existencia total menor o igual a <strong>{{ $threshold }}</strong>.
                                    La existencia total es la suma de todos los almacenes. Esta alerta se enviará diariamente hasta que se reponga el inventario.
                                @endif
                            </p>

                            @foreach ($products as $product)
                                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 18px;">
                                    <tr>
                                        <td colspan="2" style="padding: 10px 12px; border: 1px solid #e5e7eb; background-color: #fef2f2; color: #991b1b; font-weight: bold;">
                                            {{ $product['code'] }} · {{ $product['name'] }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280; width: 40%;">Categoría</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $product['category'] ?? 'Sin categoría' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Unidad</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #111827;">{{ $product['unit'] ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #6b7280;">Existencia total</td>
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #b91c1c; font-weight: bold;">{{ $product['total_existence'] }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding: 8px 12px; border: 1px solid #e5e7eb; background-color: #f9fafb; color: #374151; font-size: 13px;">
                                            <strong>Detalle por almacén</strong>
                                            @if ($product['warehouses'] === [])
                                                <div style="margin-top: 6px;">Sin stock registrado en almacenes.</div>
                                            @else
                                                <ul style="margin: 6px 0 0; padding-left: 18px;">
                                                    @foreach ($product['warehouses'] as $warehouse)
                                                        <li>{{ $warehouse['name'] }}: {{ $warehouse['existence'] }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            @endforeach

                            <p style="margin: 0; color: #6b7280; font-size: 12px;">
                                Umbral actual: {{ $threshold }}. Puede cambiarlo en Operaciones → Parámetros de Inventario.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
