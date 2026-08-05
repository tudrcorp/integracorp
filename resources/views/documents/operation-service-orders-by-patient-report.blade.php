@php
    /** @var \Illuminate\Support\Collection<int, array{patient: string, document: string, orders_count: int, orders: list<array<string, string>>}> $groups */
    /** @var int $totalOrders */
    /** @var int $totalPatients */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    $brandCyan = '#00ADEF';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de órdenes por paciente</title>
    <style>
        @page { margin: 8mm 6mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7pt;
            line-height: 1.35;
            color: #1f2937;
        }
        .logo-row { text-align: center; margin-bottom: 6px; }
        .logo-row img { max-height: 32px; width: auto; }
        .doc-title-wrap { text-align: center; margin-bottom: 8px; }
        .doc-heading {
            font-size: 12pt;
            font-weight: bold;
            color: #0c4a6e;
            margin: 0 0 4px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid {{ $brandCyan }};
            display: inline-block;
        }
        .doc-meta {
            font-size: 7pt;
            color: #475569;
            margin: 0 0 8px 0;
        }
        .summary {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .summary td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            background: #f8fafc;
            font-size: 7.5pt;
        }
        .summary strong { color: #0f172a; }
        .patient-block {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .patient-header {
            background: #e0f2fe;
            border: 1px solid #7dd3fc;
            border-bottom: none;
            padding: 5px 8px;
            font-weight: bold;
            color: #0c4a6e;
            font-size: 8pt;
        }
        .patient-header .count {
            float: right;
            font-weight: bold;
            color: #0369a1;
        }
        table.detail {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 2px;
        }
        table.detail th,
        table.detail td {
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 6.5pt;
        }
        table.detail thead th {
            background: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
            text-align: center;
            border-bottom: 2px solid {{ $brandCyan }};
        }
        .col-order { width: 9%; }
        .col-case { width: 8%; }
        .col-service { width: 10%; }
        .col-status { width: 8%; }
        .col-payment { width: 8%; }
        .col-supplier { width: 12%; }
        .col-priority { width: 8%; }
        .col-desc { width: 15%; }
        .col-usd { width: 7%; }
        .col-ves { width: 7%; }
        .col-date { width: 8%; }
        .empty {
            text-align: center;
            color: #94a3b8;
            font-style: italic;
            padding: 16px;
        }
        .doc-footer {
            text-align: center;
            font-size: 6pt;
            color: #64748b;
            margin-top: 8mm;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    @if (! empty($logoDataUri))
        <div class="logo-row">
            <img src="{{ $logoDataUri }}" alt="" />
        </div>
    @endif

    <div class="doc-title-wrap">
        <div class="doc-heading">Reporte detallado de órdenes por paciente</div>
        <p class="doc-meta">
            Generado: {{ $generatedAt->format('d/m/Y H:i') }}
            · Validación de cantidad de servicios por paciente
        </p>
    </div>

    <table class="summary">
        <tr>
            <td><strong>Pacientes:</strong> {{ $totalPatients }}</td>
            <td><strong>Órdenes / servicios:</strong> {{ $totalOrders }}</td>
            <td><strong>Promedio por paciente:</strong>
                {{ $totalPatients > 0 ? number_format($totalOrders / $totalPatients, 2, ',', '.') : '0' }}
            </td>
        </tr>
    </table>

    @forelse ($groups as $group)
        <div class="patient-block">
            <div class="patient-header">
                <span class="count">{{ $group['orders_count'] }} {{ $group['orders_count'] === 1 ? 'servicio' : 'servicios' }}</span>
                {{ $group['patient'] }}
                @if (($group['document'] ?? '—') !== '—')
                    · CI/Doc: {{ $group['document'] }}
                @endif
            </div>
            <table class="detail">
                <thead>
                    <tr>
                        <th class="col-order">Nº orden</th>
                        <th class="col-case">Caso</th>
                        <th class="col-service">Tipo servicio</th>
                        <th class="col-status">Estado</th>
                        <th class="col-payment">Pago</th>
                        <th class="col-supplier">Proveedor</th>
                        <th class="col-priority">Prioridad</th>
                        <th class="col-desc">Descripción</th>
                        <th class="col-usd">USD</th>
                        <th class="col-ves">VES</th>
                        <th class="col-date">Creado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['orders'] as $order)
                        <tr>
                            <td>{{ $order['order_number'] }}</td>
                            <td>{{ $order['case_code'] }}</td>
                            <td>{{ $order['service_type'] }}</td>
                            <td>{{ $order['status'] }}</td>
                            <td>{{ $order['status_payment'] }}</td>
                            <td>{{ $order['supplier'] }}</td>
                            <td>{{ $order['priority'] }}</td>
                            <td>{{ $order['description'] }}</td>
                            <td style="text-align:right;">{{ $order['amount_usd'] }}</td>
                            <td style="text-align:right;">{{ $order['amount_ves'] }}</td>
                            <td>{{ $order['created_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="empty">No hay órdenes de servicio para el filtro actual.</p>
    @endforelse

    <div class="doc-footer">
        INTEGRACORP · TU DR. GROUP · www.tudrgroup.com · Gestión de Órdenes de Servicio
    </div>
</body>
</html>
