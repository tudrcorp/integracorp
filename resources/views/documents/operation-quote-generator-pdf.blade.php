@php
    /** @var \App\Models\OperationQuoteGenerator $quote */
    /** @var \App\Models\OperationCoordinationService $coordination */
    $brandCyan = '#00ADEF';
    $items = is_array($quote->items) ? $quote->items : [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización #{{ $quote->id }}</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 18mm 20mm;
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
        .section-title {
            margin: 12px 0 8px 0;
            font-size: 10pt;
            font-weight: bold;
            color: #0c4a6e;
            border-left: 3px solid {{ $brandCyan }};
            padding-left: 8px;
        }
        .meta-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .meta-table td { width: 50%; padding: 5px 10px 5px 0; vertical-align: top; }
        .label { font-size: 7pt; color: #9ca3af; text-transform: uppercase; margin-bottom: 2px; }
        .value { font-size: 9pt; color: #111827; font-weight: 600; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .items-table th, .items-table td {
            border: 1px solid #e5e7eb;
            padding: 6px;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .items-table th { background: #f8fafc; text-align: left; }
        .summary {
            margin-top: 14px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            border-radius: 8px;
            padding: 12px;
        }
        .summary-row { margin: 3px 0; font-size: 9pt; }
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
                    <h1>Cotización de servicios</h1>
                    <p>Número: <strong>#{{ $quote->id }}</strong></p>
                    <p>Fecha: <strong>{{ optional($quote->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</strong></p>
                </td>
            </tr>
        </table>
    </div>

    <p class="section-title">Información principal</p>
    <table class="meta-table">
        <tr>
            <td>
                <div class="label">Paciente</div>
                <div class="value">{{ $coordination->patient ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Referencia</div>
                <div class="value">{{ $coordination->reference_number ?? '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Tipo de servicio</div>
                <div class="value">{{ $quote->type_service ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Estatus</div>
                <div class="value">{{ $quote->status ?? 'PENDIENTE POR APROBAR' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Proveedor</div>
                <div class="value">{{ $quote->supplier?->name ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Dirección del proveedor</div>
                <div class="value">{{ $quote->supplier_address ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <p class="section-title">Ítems cotizados</p>
    <table class="items-table">
        <thead>
            <tr>
                <th>Categoría</th>
                <th>Ítem</th>
                <th>Detalle</th>
                <th>Cobertura</th>
                <th>P. unit. (USD)</th>
                <th>P. unit. (Bs.)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['category'] ?? '—' }}</td>
                    <td>{{ $item['label'] ?? '—' }}</td>
                    <td>{{ $item['detail'] ?? '—' }}</td>
                    <td>{{ $item['coverage_label'] ?? '—' }}</td>
                    <td>US$ {{ number_format((float) ($item['unit_price_usd'] ?? 0), 2, ',', '.') }}</td>
                    <td>Bs. {{ number_format((float) ($item['unit_price_ves'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Sin ítems registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if(filled($quote->observations))
        <p class="section-title">Observaciones</p>
        <p style="margin: 0 0 12px 0; font-size: 8.5pt; line-height: 1.45; color: #374151;">{{ $quote->observations }}</p>
    @endif

    <div class="summary">
        <div class="summary-row"><strong>Costo base (USD):</strong> US$ {{ number_format((float) ($quote->costo_dolares ?? 0), 2, ',', '.') }}</div>
        <div class="summary-row"><strong>Ganancia aplicada:</strong> {{ number_format((float) ($quote->porcentaje_ganancia ?? 0), 2, ',', '.') }}%</div>
        <div class="summary-row"><strong>Subtotal (USD):</strong> US$ {{ number_format((float) ($quote->subtotal ?? 0), 2, ',', '.') }}</div>
        <div class="summary-row"><strong>Total (USD):</strong> US$ {{ number_format((float) ($quote->total ?? 0), 2, ',', '.') }}</div>
        <div class="summary-row"><strong>Tasa BCV aplicada:</strong> {{ number_format((float) $bcvRate, 2, ',', '.') }} Bs./US$</div>
        <div class="summary-row"><strong>Total (Bs.):</strong> Bs. {{ number_format((float) ($quote->costo_bolivares ?? 0), 2, ',', '.') }}</div>
    </div>

    <div class="footer">
        Cotización generada automáticamente desde la modal de gestión de coordinación de servicios.
    </div>
</body>
</html>
