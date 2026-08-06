@php
    /** @var array{
     *     agency: array{code: string, name: string},
     *     period_label: string,
     *     from: string,
     *     to: string,
     *     year: int,
     *     summary: array{individual_count: int, corporate_count: int, individual_population: int, corporate_population: int, individual_total: float, corporate_total: float, grand_total: float},
     *     rows: list<array{date: string, agency_name: string, type: string, plan: string, population: string, total_amount: float}>,
     *     year_series: array{year: int, labels: list<string>, individual: list<float>, corporate: list<float>},
     *     chart_data_uri: string
     * } $report
     */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    /** @var string $logoDataUri */
    $brandCyan = '#00ADEF';
    $agency = $report['agency'];
    $summary = $report['summary'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de ventas — {{ $agency['name'] }}</title>
    <style>
        @page { margin: 10mm 8mm; size: A4 landscape; }
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
        .logo-row img { max-height: 28px; width: auto; }
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
            max-height: 240px;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .summary td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            background: #f8fafc;
            vertical-align: top;
            width: 20%;
        }
        .summary strong { color: #0f172a; }
        h2.section {
            font-size: 10pt;
            color: #0c4a6e;
            margin: 8px 0 6px 0;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th {
            background: #0c4a6e;
            color: #ffffff;
            font-size: 7.5pt;
            padding: 5px 4px;
            text-align: left;
            border: 1px solid #0c4a6e;
        }
        table.data td {
            border: 1px solid #cbd5e1;
            padding: 4px;
            font-size: 7.5pt;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .num { text-align: right; white-space: nowrap; }
        .type-ind { color: #0369a1; font-weight: bold; }
        .type-corp { color: #b45309; font-weight: bold; }
        .empty {
            text-align: center;
            color: #64748b;
            padding: 16px;
            border: 1px dashed #cbd5e1;
        }
    </style>
</head>
<body>
    @if ($logoDataUri !== '')
        <div class="logo-row">
            <img src="{{ $logoDataUri }}" alt="Logo">
        </div>
    @endif

    <h1 class="doc-heading">Reporte de ventas de agencia</h1>
    <p class="doc-meta">
        Agencia: <strong>{{ $agency['name'] }}</strong> ({{ $agency['code'] }})
        · Periodo: <strong>{{ $report['period_label'] }}</strong>
        · Generado: {{ $generatedAt->format('d/m/Y H:i') }}
    </p>

    <div class="chart-wrap">
        <img src="{{ $report['chart_data_uri'] }}" alt="Gráfico individual vs corporativo">
    </div>

    <table class="summary">
        <tr>
            <td>
                <strong>Individuales</strong><br>
                {{ $summary['individual_count'] }} afiliaciones activas<br>
                {{ number_format($summary['individual_population'], 0, ',', '.') }} afiliados
            </td>
            <td>
                <strong>Corporativas</strong><br>
                {{ $summary['corporate_count'] }} afiliaciones activas<br>
                {{ number_format($summary['corporate_population'], 0, ',', '.') }} afiliados
            </td>
            <td><strong>Venta individual</strong><br>US$ {{ number_format($summary['individual_total'], 2, ',', '.') }}</td>
            <td><strong>Venta corporativa</strong><br>US$ {{ number_format($summary['corporate_total'], 2, ',', '.') }}</td>
            <td><strong>Total venta</strong><br>US$ {{ number_format($summary['grand_total'], 2, ',', '.') }}</td>
        </tr>
    </table>

    <h2 class="section">Detalle de ventas (total_amount US$)</h2>

    @if (count($report['rows']) === 0)
        <div class="empty">No hay ventas en el periodo seleccionado.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 10%;">Fecha</th>
                    <th style="width: 22%;">Agencia</th>
                    <th style="width: 12%;">Tipo</th>
                    <th style="width: 22%;">Plan</th>
                    <th style="width: 12%;">Población</th>
                    <th style="width: 22%;">Total venta US$</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['agency_name'] }}</td>
                        <td class="{{ $row['type'] === 'Individual' ? 'type-ind' : 'type-corp' }}">{{ $row['type'] }}</td>
                        <td>{{ $row['plan'] }}</td>
                        <td class="num">{{ $row['population'] }}</td>
                        <td class="num">{{ number_format((float) $row['total_amount'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
