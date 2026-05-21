@php
    /** @var \App\Models\OperationServiceOrder $order */
    $coord = $order->operationCoordinationService;
    $brandCyan = '#00ADEF';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $quoteMeta['quote_number'] ?? 'Cotización' }}</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        body { margin: 18mm 20mm; font-family: DejaVu Sans, sans-serif; color: #374151; font-size: 9pt; }
        .header { border-bottom: 1px solid {{ $brandCyan }}; padding-bottom: 8px; margin-bottom: 12px; }
        .header table { width: 100%; border-collapse: collapse; }
        .title { text-align: right; }
        .title h1 { margin: 0; font-size: 14pt; color: {{ $brandCyan }}; }
        .title p { margin: 2px 0 0; font-size: 8pt; color: #6b7280; }
        .section-title { font-size: 10pt; font-weight: bold; margin: 10px 0 6px; color: #0f172a; }
        .meta-table, .items-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 4px 8px 4px 0; vertical-align: top; }
        .label { font-size: 7pt; color: #94a3b8; text-transform: uppercase; margin-bottom: 1px; }
        .value { font-size: 9pt; color: #111827; font-weight: 600; }
        .items-table th, .items-table td { border: 1px solid #e5e7eb; padding: 6px; font-size: 8.5pt; }
        .items-table th { background: #f8fafc; text-align: left; }
        .summary { margin-top: 10px; border: 1px solid #dbeafe; background: #eff6ff; border-radius: 8px; padding: 10px; }
        .summary-row { margin: 2px 0; font-size: 9pt; }
        .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 6px; text-align: center; font-size: 7pt; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    @if($logoDataUri !== '')
                        <img src="{{ $logoDataUri }}" alt="Tu Doctor en Casa" style="width:85px;">
                    @endif
                </td>
                <td class="title">
                    <h1>Cotización de medicamentos</h1>
                    <p>Número: <strong>{{ $quoteMeta['quote_number'] ?? '—' }}</strong></p>
                    <p>Fecha: <strong>{{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</strong></p>
                </td>
            </tr>
        </table>
    </div>

    <p class="section-title">Datos generales</p>
    <table class="meta-table">
        <tr>
            <td>
                <div class="label">Orden</div>
                <div class="value">{{ $order->order_number }}</div>
            </td>
            <td>
                <div class="label">Paciente</div>
                <div class="value">{{ $coord?->patient ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Referencia</div>
                <div class="value">{{ $coord?->reference_number ?? '—' }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="label">Proveedor</div>
                <div class="value">{{ $quoteMeta['supplier_name'] ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Tasa BCV</div>
                <div class="value">{{ number_format((float) ($quoteMeta['bcv_rate'] ?? 0), 2, ',', '.') }} Bs./US$</div>
            </td>
        </tr>
    </table>

    <p class="section-title">Ítems cotizados</p>
    <table class="items-table">
        <thead>
            <tr>
                <th>Medicamento</th>
                <th>Cantidad</th>
                <th>Precio unitario US$</th>
                <th>Subtotal US$</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item['item_name'] ?? '—' }}</td>
                    <td>{{ $item['quantity'] ?? 0 }}</td>
                    <td>{{ number_format((float) ($item['unit_amount_usd'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($item['line_total_usd'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-row"><strong>Total USD:</strong> US$ {{ number_format((float) ($quoteMeta['total_amount_usd'] ?? 0), 2, ',', '.') }}</div>
        <div class="summary-row"><strong>Total Bs.:</strong> Bs. {{ number_format((float) ($quoteMeta['total_amount_ves'] ?? 0), 2, ',', '.') }}</div>
    </div>

    <div class="footer">
        Documento generado automáticamente por el módulo de operaciones.
    </div>
</body>
</html>
