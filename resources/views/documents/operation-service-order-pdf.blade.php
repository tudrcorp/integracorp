@php
    /** @var \App\Models\OperationServiceOrder $order */
    $coord = $order->operationCoordinationService;
    $brandCyan = '#00ADEF';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de servicio {{ $order->order_number }}</title>
    <style>
        /**
         * Márgenes de hoja: en DomPDF el margin del body es más fiable que @page o padding en contenedores.
         */
        @page {
            margin: 0;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        html {
            margin: 0;
            padding: 0;
        }
        body {
            margin: 18mm 20mm 18mm 20mm;
            padding: 0;
            height: auto;
            min-height: 0;
            width: auto;
            max-width: 100%;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.5pt;
            line-height: 1.28;
            color: #374151;
        }
        .page-frame {
            padding: 0;
            margin: 0;
            width: 100%;
            max-width: 100%;
        }
        /**
         * Marca de agua: ~60% del ancho de hoja, centrada verticalmente, rotada (efecto seguridad).
         */
        .watermark {
            position: fixed;
            top: 50%;
            left: 20%;
            width: 60%;
            max-width: 60%;
            opacity: 0.052;
            z-index: 0;
            pointer-events: none;
            transform: translateY(-50%) rotate(-14deg);
            transform-origin: center center;
        }
        .watermark img {
            width: 100%;
            height: auto;
            display: block;
        }
        .doc-root {
            position: relative;
            z-index: 1;
        }
        .header-bar {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0 0 7px 0;
            padding: 0 0 6px 0;
            border-bottom: 1.5px solid {{ $brandCyan }};
        }
        .header-bar td {
            vertical-align: top;
            padding: 0 8px 0 0;
            overflow: visible;
        }
        .header-bar td:last-child {
            padding-right: 0;
        }
        .header-bar .col-logo {
            width: 32%;
        }
        .header-bar .col-title {
            width: 68%;
        }
        .logo-cell img.header-logo,
        img.header-logo {
            max-width: 74px;
            height: auto;
            display: block;
        }
        .title-cell {
            text-align: right;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: none;
        }
        .doc-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: {{ $brandCyan }};
            margin: 0 0 2px 0;
            line-height: 1.2;
            letter-spacing: 0;
        }
        .doc-sub {
            font-size: 7.25pt;
            color: #6b7280;
            margin: 0 0 2px 0;
            line-height: 1.25;
        }
        .doc-sub:last-of-type {
            margin-bottom: 0;
        }
        .badge {
            display: inline-block;
            margin-top: 3px;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 6.5pt;
            font-weight: bold;
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        .section-title {
            font-size: 7.5pt;
            font-weight: bold;
            color: #0c4a6e;
            margin: 6px 0 3px 0;
            padding: 2px 5px 2px 6px;
            border-left: 2.5px solid {{ $brandCyan }};
            background: #f0fdff;
            line-height: 1.2;
        }
        .two-col-section:first-of-type .section-title {
            margin-top: 0;
        }
        .section-title--block {
            margin-top: 7px;
        }
        .grid {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
        }
        .grid td {
            padding: 2px 8px 3px 0;
            vertical-align: top;
            width: 50%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .grid tr td:nth-child(2) {
            padding-right: 0;
        }
        .label {
            font-size: 6.25pt;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            line-height: 1.15;
            margin-bottom: 1px;
        }
        .value {
            font-size: 7.5pt;
            color: #111827;
            font-weight: 600;
            line-height: 1.28;
        }
        .value-muted {
            font-size: 7.35pt;
            color: #4b5563;
            line-height: 1.28;
            word-wrap: break-word;
        }
        table.items {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            font-size: 6.75pt;
            table-layout: fixed;
        }
        table.items th {
            background-color: {{ $brandCyan }};
            color: #ffffff;
            padding: 4px 4px;
            text-align: left;
            font-size: 6.5pt;
            font-weight: bold;
            line-height: 1.15;
            border: 1px solid #0090c7;
        }
        table.items td {
            border: 1px solid #e5e7eb;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.22;
            font-size: 7pt;
        }
        table.items tr:nth-child(even) td {
            background: #fafafa;
        }
        .items-empty {
            font-size: 7.25pt;
            color: #6b7280;
            margin: 3px 0 0 0;
            padding: 5px 8px;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 3px;
            line-height: 1.3;
        }
        /**
         * Pie fijo al borde inferior de cada hoja (mismos márgenes que body).
         */
        .footer-fixed {
            position: fixed;
            bottom: 18mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            line-height: 1.4;
            padding: 6px 4px 0 4px;
            border-top: 1px solid #e5e7eb;
            font-size: 6.5pt;
            color: #9ca3af;
            background-color: #ffffff;
            z-index: 10;
        }
        .footer-brand {
            font-weight: bold;
            color: {{ $brandCyan }};
        }
        .two-col-section {
            margin-bottom: 0;
        }
        .doc-content {
            position: relative;
            z-index: 1;
            padding: 0 0 26mm 0;
        }
    </style>
</head>
<body>
<div class="page-frame">
<div class="doc-root">
@if($logoDataUri !== '')
    <div class="watermark" aria-hidden="true">
        <img src="{{ $logoDataUri }}" alt="">
    </div>
@endif
<div class="doc-content">
    <table class="header-bar" width="100%">
        <tr>
            <td class="col-logo">
                @if($logoDataUri !== '')
                    <img class="header-logo" src="{{ $logoDataUri }}" alt="Tu Doctor en Casa">
                @else
                    <span style="font-weight:bold;color:{{ $brandCyan }};font-size:8pt;">Tu Doctor en Casa</span>
                @endif
            </td>
            <td class="col-title title-cell">
                <p class="doc-title">Orden de servicio</p>
                <p class="doc-sub">N° <strong>{{ $order->order_number }}</strong></p>
                <p class="doc-sub">Fecha: <strong>{{ $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</strong></p>
                @if(filled($order->status))
                    <span class="badge">Estado: {{ $order->status }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="two-col-section">
        <div class="section-title">Datos de la orden</div>
        <table class="grid">
            <tr>
                <td>
                    <div class="label">Tipo de servicio</div>
                    <div class="value">{{ $order->service_type ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Prioridad</div>
                    <div class="value">{{ $order->telemedicinePriority?->name ?? '—' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Ubicación despacho</div>
                    <div class="value-muted">{{ $order->operationInventoryUbication?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">ID coordinación</div>
                    <div class="value-muted">{{ $order->operation_coordination_service_id }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Ítems / unidades</div>
                    <div class="value-muted">{{ $order->total_items ?? 0 }} / {{ $order->total_items_unit ?? 0 }}</div>
                </td>
                <td>
                    <div class="label">Moneda</div>
                    <div class="value-muted">{{ $order->currency ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if($coord)
        <div class="two-col-section">
            <div class="section-title">Coordinación y paciente</div>
            <table class="grid">
                <tr>
                    <td>
                        <div class="label">Paciente</div>
                        <div class="value">{{ $coord->patient ?? '—' }}</div>
                    </td>
                    <td>
                        <div class="label">CI paciente</div>
                        <div class="value-muted">{{ $coord->ci_patient ?? '—' }}</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="label">Teléfono / Ref.</div>
                        <div class="value-muted">{{ $coord->phone_holder ?? '—' }} · {{ $coord->reference_number ?? '—' }}</div>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="label">Dirección</div>
                        <div class="value-muted">{{ $coord->address ?? '—' }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Estado / ciudad</div>
                        <div class="value-muted">
                            {{ $coord->state?->definition ?? '—' }}
                            @if($coord->city)
                                — {{ $coord->city->definition }}
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="label">Contratante</div>
                        <div class="value-muted">{{ $coord->contractor ?? '—' }}</div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="two-col-section">
        <div class="section-title">Proveedor y descripción</div>
        <table class="grid">
            <tr>
                <td>
                    <div class="label">Proveedor natural</div>
                    <div class="value">{{ $order->doctorNurse?->name ?? '—' }}</div>
                    <div class="label">Proveedor jurídico</div>
                    <div class="value-muted">{{ $order->supplier?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Proveedor No Convenido</div>
                    <div class="value-muted">{{ $order->supplier_external ?? '—' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <div class="label">Descripción</div>
                    <div class="value-muted">{{ $order->description ?? '—' }}</div>
                </td>
            </tr>
            @if(filled($order->observations))
                <tr>
                    <td colspan="2">
                        <div class="label">Observaciones</div>
                        <div class="value-muted">{{ $order->observations }}</div>
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <div class="two-col-section">
        <div class="section-title">Montos y pago</div>
        <table class="grid">
            <tr>
                <td>
                    <div class="label">Método de pago</div>
                    <div class="value-muted">{{ $order->payment_method ?? '—' }}</div>
                </td>
                <td>
                    <div class="label">Tasa BCV</div>
                    <div class="value-muted">
                        @if($order->tasa_bcv !== null)
                            {{ number_format((float) $order->tasa_bcv, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Total USD</div>
                    <div class="value-muted">
                        @if($order->total_amount_usd !== null)
                            USD {{ number_format((float) $order->total_amount_usd, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </div>
                </td>
                <td>
                    <div class="label">Total VES</div>
                    <div class="value-muted">
                        @if($order->total_amount_ves !== null)
                            VES {{ number_format((float) $order->total_amount_ves, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title section-title--block">Ítems de la orden</div>
    @if($order->operationServiceOrderItems->isEmpty())
        <p class="items-empty">Sin ítems registrados.</p>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th style="width:19%">Ítem</th>
                    <th style="width:10%">Cat.</th>
                    <th style="width:7%">Und.</th>
                    <th style="width:6%">Cant.</th>
                    <th style="width:10%">Monto</th>
                    <th style="width:7%">Mon.</th>
                    <th style="width:41%">Indic.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->operationServiceOrderItems as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->item_unit ?? '—' }}</td>
                        <td>{{ $item->quantity ?? 0 }}</td>
                        <td>
                            @if($item->amount !== null)
                                {{ number_format((float) $item->amount, 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $item->currency ?? '—' }}</td>
                        <td>{{ $item->dosage_instruction ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</div>
</div>

<div class="footer-fixed">
    Documento generado por el <span class="footer-brand">departamento de operaciones de Tu Doctor en Casa</span>.<br>
    Uso interno; la reproducción no autorizada puede estar restringida según políticas de la organización.
</div>
</body>
</html>
