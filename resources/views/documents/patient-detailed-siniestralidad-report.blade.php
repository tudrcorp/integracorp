@php
    /** @var array{
     *     patient: array{id: int, full_name: string, nro_identificacion: string, code: string, type_affiliation: string, business_unit: string, phone: string, email: string},
     *     year: int,
     *     through_month: int,
     *     summary: array{services_count: int, total_bill_price: float},
     *     year_series: array{year: int, labels: list<string>, values: list<int>},
     *     chart_data_uri: string,
     *     services: list<array{id: int, reference_number: string, date_solicitud: string, date_service: string, created_at: string, specific_service: string, servicie: string, status: string, bill_price: float, bill_number: string}>
     * } $report
     */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    /** @var string $logoDataUri */
    $brandCyan = '#00ADEF';
    $patient = $report['patient'];
    $summary = $report['summary'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte detallado por paciente</title>
    <style>
        @page { margin: 10mm 8mm; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            line-height: 1.35;
            color: #1f2937;
        }
        .logo-row { text-align: center; margin-bottom: 6px; }
        .logo-row img { max-height: 30px; width: auto; }
        .doc-heading {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            color: #0c4a6e;
            margin: 0 0 4px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid {{ $brandCyan }};
        }
        .doc-meta {
            text-align: center;
            font-size: 7.5pt;
            color: #475569;
            margin: 0 0 10px 0;
        }
        .chart-wrap {
            margin: 0 0 10px 0;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px;
            background: #f8fafc;
            text-align: center;
        }
        .chart-wrap img {
            width: 100%;
            max-height: 220px;
        }
        .patient-card,
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .patient-card td,
        .summary td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            background: #f8fafc;
            vertical-align: top;
        }
        .summary strong,
        .patient-card strong { color: #0f172a; }
        h2.section {
            font-size: 10pt;
            color: #0c4a6e;
            margin: 10px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #7dd3fc;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.grid th,
        table.grid td {
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 7pt;
        }
        table.grid th {
            background: #e0f2fe;
            color: #0c4a6e;
            text-align: left;
        }
        .num { text-align: right; }
        .money { font-weight: bold; color: #065f46; }
        .empty {
            padding: 14px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
        }
        .totals-row td {
            background: #ecfdf5;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="logo-row">
        @if ($logoDataUri !== '')
            <img src="{{ $logoDataUri }}" alt="Logo">
        @endif
    </div>

    <p class="doc-heading">Reporte detallado de siniestralidad por paciente</p>
    <p class="doc-meta">
        Generado: {{ $generatedAt->format('d/m/Y H:i') }}
        · Solo servicios FINALIZADO
        · Costo: Precio de Factura (bill_price)
    </p>

    <div class="chart-wrap">
        @if (filled($report['chart_data_uri']))
            <img src="{{ $report['chart_data_uri'] }}" alt="Gráfico de línea servicios por mes">
        @endif
    </div>

    <table class="patient-card">
        <tr>
            <td><strong>Paciente:</strong><br>{{ $patient['full_name'] }}</td>
            <td><strong>Identificación:</strong><br>{{ $patient['nro_identificacion'] }}</td>
            <td><strong>Código:</strong><br>{{ $patient['code'] }}</td>
        </tr>
        <tr>
            <td><strong>Afiliación:</strong><br>{{ $patient['type_affiliation'] }}</td>
            <td><strong>Unidad:</strong><br>{{ $patient['business_unit'] }}</td>
            <td><strong>Contacto:</strong><br>{{ $patient['phone'] }} · {{ $patient['email'] }}</td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td><strong>Total de servicios FINALIZADO:</strong> {{ number_format($summary['services_count'], 0, ',', '.') }}</td>
            <td><strong>Costo total para la empresa (USD):</strong> {{ number_format($summary['total_bill_price'], 2, ',', '.') }}</td>
            <td><strong>Año del gráfico:</strong> {{ $report['year'] }} (Ene–{{ ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][$report['through_month']] }})</td>
        </tr>
    </table>

    <h2 class="section">Detalle de servicios realizados</h2>

    @if ($report['services'] === [])
        <div class="empty">Este paciente no tiene servicios FINALIZADO registrados.</div>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 14%;">Referencia</th>
                    <th style="width: 14%;">Creado</th>
                    <th style="width: 18%;">Servicio</th>
                    <th style="width: 18%;">Específico</th>
                    <th style="width: 12%;">Factura</th>
                    <th style="width: 16%;" class="num">Costo (USD)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['services'] as $service)
                    <tr>
                        <td>{{ $service['id'] }}</td>
                        <td>{{ $service['reference_number'] }}</td>
                        <td>{{ $service['created_at'] }}</td>
                        <td>{{ $service['servicie'] }}</td>
                        <td>{{ $service['specific_service'] }}</td>
                        <td>{{ $service['bill_number'] }}</td>
                        <td class="num money">{{ number_format($service['bill_price'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="totals-row">
                    <td colspan="6" class="num">TOTAL</td>
                    <td class="num money">{{ number_format($summary['total_bill_price'], 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
