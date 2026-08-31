@php
    /** @var \App\Models\OperationQuoteGenerator $quote */
    /** @var \App\Models\OperationCoordinationService $coordination */
    $brandCyan = '#00ADEF';
    $profitPercentage = $quote->porcentaje_ganancia ?? 0;
    $items = \App\Support\Operations\OperationQuoteGeneratorPublicAmounts::itemsWithProfit(
        is_array($quote->items) ? $quote->items : [],
        $profitPercentage
    );
    $publicCostoUsd = \App\Support\Operations\OperationQuoteGeneratorPublicAmounts::applyProfit($quote->costo_dolares, $profitPercentage);
    $publicSubtotalUsd = \App\Support\Operations\OperationQuoteGeneratorPublicAmounts::applyProfit($quote->subtotal, $profitPercentage);
    $publicTotalUsd = (float) ($quote->total ?? 0);
    $patientName = \App\Support\Telemedicine\TelemedicinePatientDisplayName::forCoordination($coordination);
    $providerPhone = \App\Support\Operations\OperationServiceOrderProviderContacts::fromModels(
        null,
        $quote->supplier instanceof \App\Models\Supplier ? $quote->supplier : null,
    )['phone'];
    $providerPhone = filled($providerPhone) ? $providerPhone : '—';
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
            font-size: 8pt;
            color: #374151;
        }
        .header { border-bottom: 1.5px solid {{ $brandCyan }}; padding-bottom: 10px; margin-bottom: 14px; }
        .header table { width: 100%; border-collapse: collapse; }
        .logo { width: 110px; }
        .title { text-align: right; }
        .title h1 { margin: 0; font-size: 12.5pt; color: {{ $brandCyan }}; }
        .title p { margin: 2px 0 0 0; font-size: 7.5pt; color: #6b7280; }
        .section-title {
            margin: 12px 0 8px 0;
            font-size: 9pt;
            font-weight: bold;
            color: #0c4a6e;
            border-left: 3px solid {{ $brandCyan }};
            padding-left: 8px;
        }
        .meta-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .meta-table td { width: 50%; padding: 5px 10px 5px 0; vertical-align: top; }
        .label { font-size: 6.5pt; color: #9ca3af; text-transform: uppercase; margin-bottom: 2px; }
        .value { font-size: 8pt; color: #111827; font-weight: 600; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .items-table th, .items-table td {
            border: 1px solid #e5e7eb;
            padding: 6px;
            font-size: 7.5pt;
            vertical-align: top;
        }
        .items-table th { background: #f8fafc; text-align: left; }
        .section-title--observations {
            margin-top: 22px;
        }
        .observations-box {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0 0 4px 0;
        }
        .observations-box td {
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            font-size: 7.5pt;
            line-height: 1.45;
            color: #374151;
        }
        .summary {
            width: 100%;
            max-width: 100%;
            margin-top: 16px;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #d1d5db;
        }
        .summary-heading {
            background: #f3f4f6;
            color: #111827;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 7px 10px;
            border-bottom: 1px solid #d1d5db;
        }
        .summary-label {
            width: 70%;
            padding: 7px 10px;
            font-size: 7.25pt;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }
        .summary-amount {
            width: 30%;
            padding: 7px 10px;
            font-size: 7.5pt;
            font-weight: 600;
            color: #111827;
            text-align: right;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
        }
        .summary-total .summary-label {
            background: #f9fafb;
            color: #111827;
            font-size: 8pt;
            font-weight: bold;
            border-bottom: none;
        }
        .summary-total .summary-amount {
            background: #f9fafb;
            color: #111827;
            font-size: 9.5pt;
            font-weight: bold;
            border-bottom: none;
        }
        .footer {
            position: fixed;
            bottom: 16mm;
            left: 20mm;
            right: 20mm;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 6.5pt;
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
                <div class="value">{{ $patientName }}</div>
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
                <div class="label" style="margin-top: 6px">Teléfono</div>
                <div class="value">{{ $providerPhone }}</div>
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
                </tr>
            @empty
                <tr>
                    <td colspan="5">Sin ítems registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if(filled($quote->observations))
        <p class="section-title section-title--observations">Observaciones</p>
        <table class="observations-box">
            <tr>
                <td>{{ $quote->observations }}</td>
            </tr>
        </table>
    @endif

    <table class="summary">
        <tr>
            <td class="summary-heading" colspan="2">Resumen de cotización</td>
        </tr>
        <tr>
            <td class="summary-label">Costo base</td>
            <td class="summary-amount">US$ {{ number_format($publicCostoUsd, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">Subtotal</td>
            <td class="summary-amount">US$ {{ number_format($publicSubtotalUsd, 2, ',', '.') }}</td>
        </tr>
        <tr class="summary-total">
            <td class="summary-label">Total</td>
            <td class="summary-amount">US$ {{ number_format($publicTotalUsd, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        Cotización generada automáticamente desde Sistema IntegraCorp. Coordinación de Servicios Médicos.
    </div>
</body>
</html>
