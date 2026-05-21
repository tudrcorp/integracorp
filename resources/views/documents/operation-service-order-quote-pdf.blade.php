@php
    /** @var \App\Models\OperationServiceOrder $order */
    $coord = $order->operationCoordinationService;
    $brandCyan = '#00ADEF';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización asociada {{ $order->order_number }}</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 18mm 20mm 18mm 20mm;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #374151;
        }
        .header { border-bottom: 1.5px solid {{ $brandCyan }}; padding-bottom: 10px; margin-bottom: 14px; }
        .header table { width: 100%; border-collapse: collapse; }
        .logo { width: 85px; }
        .title { text-align: right; }
        .title h1 { margin: 0; font-size: 14pt; color: {{ $brandCyan }}; }
        .title p { margin: 2px 0 0 0; font-size: 8.5pt; color: #6b7280; }
        .section { margin-top: 12px; }
        .section-title {
            margin: 0 0 8px 0;
            font-size: 10pt;
            font-weight: bold;
            color: #0c4a6e;
            border-left: 3px solid {{ $brandCyan }};
            padding-left: 8px;
        }
        .grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .grid td { width: 50%; padding: 5px 10px 5px 0; vertical-align: top; }
        .label { font-size: 7pt; color: #9ca3af; text-transform: uppercase; margin-bottom: 2px; }
        .value { font-size: 9pt; color: #111827; font-weight: 600; }
        .summary {
            margin-top: 14px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            border-radius: 8px;
            padding: 12px;
        }
        .summary strong { color: #0f172a; }
        .footer {
            position: fixed;
            bottom: 16mm;
            left: 20mm;
            right: 20mm;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 7pt;
            text-align: center;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    @if($logoDataUri !== '')
                        <img src="{{ $logoDataUri }}" alt="Tu Doctor en Casa" class="logo">
                    @endif
                </td>
                <td class="title">
                    <h1>Cotización asociada</h1>
                    <p>Orden de servicio: <strong>{{ $order->order_number }}</strong></p>
                    <p>Fecha: <strong>{{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</strong></p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Información principal</p>
        <table class="grid">
            <tr>
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
                <td>
                    <div class="label">Servicio seleccionado</div>
                    <div class="value">{{ $quoteData['service_label'] ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Proveedor</div>
                    <div class="value">{{ $order->supplier?->name ?? ($order->supplier_external ?: '—') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <div><strong>Precio cotizado (USD):</strong> US$ {{ number_format((float) ($quoteData['price_usd'] ?? 0), 2, ',', '.') }}</div>
        <div><strong>Tasa BCV aplicada:</strong> {{ number_format((float) ($quoteData['bcv_rate'] ?? 0), 2, ',', '.') }} Bs./US$</div>
        <div><strong>Precio cotizado (Bs.):</strong> Bs. {{ number_format((float) ($quoteData['price_ves'] ?? 0), 2, ',', '.') }}</div>
    </div>

    <div class="footer">
        Cotización generada automáticamente desde la modal de negociación de coordinación de servicios.
    </div>
</body>
</html>
