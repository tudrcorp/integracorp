@php
    /** @var list<array{service_type: string, total: int, pendiente: int, en_gestion: int, finalizado: int, caducada: int, cancelado: int, otros: int, amount_usd: float, amount_ves: float}> $rows */
    /** @var int $totalOrders */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    $brandCyan = '#00ADEF';
    $totalFinalizado = collect($rows)->sum('finalizado');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de órdenes por tipo de servicio</title>
    <style>
        @page { margin: 12mm 10mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            line-height: 1.35;
            color: #1f2937;
        }
        .logo-row { text-align: center; margin-bottom: 8px; }
        .logo-row img { max-height: 36px; width: auto; }
        .doc-title-wrap { text-align: center; margin-bottom: 10px; }
        .doc-heading {
            font-size: 13pt;
            font-weight: bold;
            color: #0c4a6e;
            margin: 0 0 4px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid {{ $brandCyan }};
            display: inline-block;
        }
        .doc-meta {
            font-size: 7.5pt;
            color: #475569;
            margin: 0 0 10px 0;
        }
        .summary {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .summary td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            background: #f8fafc;
            font-size: 8pt;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.report th,
        table.report td {
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            vertical-align: middle;
            word-wrap: break-word;
            font-size: 7.5pt;
            text-align: center;
        }
        table.report thead th {
            background: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
            border-bottom: 2px solid {{ $brandCyan }};
        }
        table.report tbody td.service {
            text-align: left;
            font-weight: bold;
            color: #0f172a;
        }
        table.report tfoot td {
            background: #e0f2fe;
            font-weight: bold;
        }
        .empty {
            text-align: center;
            color: #94a3b8;
            font-style: italic;
            padding: 16px;
        }
        .doc-footer {
            text-align: center;
            font-size: 6.5pt;
            color: #64748b;
            margin-top: 10mm;
            padding-top: 8px;
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
        <div class="doc-heading">Reporte de servicios realizados</div>
        <p class="doc-meta">
            Generado: {{ $generatedAt->format('d/m/Y H:i') }}
            · Conteo de órdenes por tipo de servicio
        </p>
    </div>

    <table class="summary">
        <tr>
            <td><strong>Tipos de servicio:</strong> {{ count($rows) }}</td>
            <td><strong>Total órdenes:</strong> {{ $totalOrders }}</td>
            <td><strong>Finalizados:</strong> {{ $totalFinalizado }}</td>
        </tr>
    </table>

    @if (count($rows) === 0)
        <p class="empty">No hay órdenes de servicio para el filtro actual.</p>
    @else
        <table class="report">
            <thead>
                <tr>
                    <th style="width:22%;">Tipo de servicio</th>
                    <th style="width:8%;">Total</th>
                    <th style="width:8%;">Pendiente</th>
                    <th style="width:9%;">En gestión</th>
                    <th style="width:9%;">Finalizado</th>
                    <th style="width:8%;">Caducada</th>
                    <th style="width:8%;">Cancelado</th>
                    <th style="width:7%;">Otros</th>
                    <th style="width:10%;">USD</th>
                    <th style="width:11%;">VES</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="service">{{ $row['service_type'] }}</td>
                        <td>{{ $row['total'] }}</td>
                        <td>{{ $row['pendiente'] }}</td>
                        <td>{{ $row['en_gestion'] }}</td>
                        <td>{{ $row['finalizado'] }}</td>
                        <td>{{ $row['caducada'] }}</td>
                        <td>{{ $row['cancelado'] }}</td>
                        <td>{{ $row['otros'] }}</td>
                        <td style="text-align:right;">{{ number_format($row['amount_usd'], 2, ',', '.') }}</td>
                        <td style="text-align:right;">{{ number_format($row['amount_ves'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="service">TOTAL</td>
                    <td>{{ $totalOrders }}</td>
                    <td>{{ collect($rows)->sum('pendiente') }}</td>
                    <td>{{ collect($rows)->sum('en_gestion') }}</td>
                    <td>{{ $totalFinalizado }}</td>
                    <td>{{ collect($rows)->sum('caducada') }}</td>
                    <td>{{ collect($rows)->sum('cancelado') }}</td>
                    <td>{{ collect($rows)->sum('otros') }}</td>
                    <td style="text-align:right;">{{ number_format(collect($rows)->sum('amount_usd'), 2, ',', '.') }}</td>
                    <td style="text-align:right;">{{ number_format(collect($rows)->sum('amount_ves'), 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="doc-footer">
        INTEGRACORP · TU DR. GROUP · www.tudrgroup.com · Gestión de Órdenes de Servicio
    </div>
</body>
</html>
