<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">
    <title>{{ $data['title'] }}</title>
    <style>
        @page {
            margin: 0.6cm 1cm;
            size: A4;
        }

        body {
            font-family: 'Inter', 'Helvetica', 'Arial', sans-serif;
            color: #1a1a1a;
            line-height: 1.2;
            font-size: 9px;
            margin: 0;
            padding: 0;
        }

        .preview-container {
            background: white;
            color: #1a1a1a;
            padding: 12px 14px;
            border-radius: 5px;
            font-family: 'Inter', sans-serif;
            position: relative;
            border: 1px solid #eee;
        }

        .preview-logo {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 85px;
            text-align: right;
        }

        .preview-logo img {
            width: 100%;
            height: auto;
            max-width: 85px;
            margin-bottom: 3px;
        }

        .preview-header {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 6px;
            margin-bottom: 10px;
            margin-right: 95px;
        }

        .preview-title {
            font-size: 14px;
            font-weight: 800;
            color: #1e3a8a;
            margin: 0;
            text-transform: uppercase;
        }

        .preview-subtitle {
            font-size: 10px;
            color: #64748b;
            margin: 3px 0 0;
        }

        .preview-meta {
            margin-top: 3px;
            font-size: 8px;
            color: #94a3b8;
        }

        .section-label {
            background: #eff6ff;
            color: #1d4ed8;
            padding: 3px 8px;
            border-radius: 99px;
            font-size: 8px;
            font-weight: 800;
            display: inline-block;
            margin-top: 10px;
            margin-bottom: 5px;
        }

        .section-label:first-of-type {
            margin-top: 4px;
        }

        .preview-table,
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            margin-bottom: 14px;
            font-size: 8px;
            table-layout: fixed;
        }

        .preview-table th,
        .preview-table td,
        th,
        td {
            border: 1px solid #e2e8f0;
            text-align: center;
            padding: 4px 6px;
            word-wrap: break-word;
        }

        .preview-table th,
        th {
            background: #f1f5f9;
            color: #475569;
            text-transform: uppercase;
            font-size: 8px;
        }

        .col-label {
            width: 35%;
            text-align: left !important;
            font-weight: 500;
        }

        .col-data {
            width: auto;
        }

        .check-cell {
            color: #10b981;
            font-weight: bold;
            font-size: 10px;
        }

        .price-cell {
            font-weight: 700;
            color: #0f172a;
            font-size: 8px;
        }

        .total-summary-row {
            background: #1e3a8a !important;
            color: white !important;
            font-weight: 800;
        }

        .total-summary-row td {
            border-color: #1e3a8a;
            color: white !important;
        }

        .subtotal-row {
            background: #f1f5f9;
            font-weight: 700;
            color: #334155;
        }

        .subtotal-row td {
            background: #f1f5f9;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 7pt;
            color: #475569;
            line-height: 1.3;
            page-break-inside: avoid;
        }

        .footer-note {
            font-style: italic;
            color: #64748b;
            margin: 3px 0;
        }

        .footer-disclaimer {
            background-color: #f8fafc;
            padding: 5px 10px;
            border-radius: 4px;
            margin-top: 4px;
            font-size: 7pt;
            color: #1d4ed8;
            border-left: 2px solid #3b82f6;
        }
    </style>

</head>

<body>
    <div class="preview-container">
        <div class="preview-logo">
            <img src="{{ asset('image/logoNewPdf.png') }}" alt="Logo">
            <div style="font-size: 7px; color: #94a3b8;">Fecha: {{ $data['date'] }}</div>
        </div>
        <div class='preview-header'>
            <h3 class='preview-title'>CLIENTE: GUSTAVO CAMACHO</h3>
            <p class='preview-subtitle'>RIF: J-436426645654</p>
            <div class='preview-meta'><span>AGENTE: <strong>{{ $data['user_name'] }}</strong></span></div>
        </div>
        {{-- <table class="header-table">
            <tr>
                <td>
                    <h1 class="title">{{ $data['title'] }}</h1>
                    <div class="subtitle">{{ $data['subtitle'] }}</div>
                </td>
                <td class="meta-info">
                    <div><strong>Fecha:</strong> {{ $data['date'] }}</div>
                    <div><strong>Documento:</strong> COT-{{ date('Ymd') }}</div>
                </td>
            </tr>
        </table> --}}
    </div>

    <div class="section-label">1. BENEFICIOS Y COBERTURAS</div>
    <table id="beneficios" class="preview-table">
        <thead>
            <tr>
                <th class="col-label">Descripción del Beneficio</th>
                @foreach($data['all_coverages'] as $cov)
                    <th class="col-data">US$ {{ (int) $cov->price }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data['benefits_processed'] as $b)
                <tr>
                    <td class="col-label">{{ $b['name'] }}</td>
                    @foreach($data['all_coverages'] as $cov)
                        @php
                            $exit = App\Models\BenefitCoverage::where('benefit_id', $b['id'])->where('coverage_id', $cov->id)->first();
                        @endphp
                        <td>
                            @if($exit)
                                <span class="check-cell">US$ {{ (int) $cov->price }}</span>
                            @else
                                <span class="check-cell">✔</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($data['upgrade_benefits']))
    <div class="section-label">1.1 BENEFICIOS UPGRADE SELECCIONADOS</div>
    <table id="upgrade-benefits" class="preview-table">
        <thead>
            <tr>
                <th class="col-label">Descripción del beneficio</th>
                <th class="col-data">Valor (US$)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['upgrade_benefits'] as $ub)
                <tr>
                    <td class="col-label">{{ $ub['name'] }}</td>
                    <td class="price-cell">${{ number_format($ub['pvp'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="subtotal-row">
                <td class="col-label">Subtotal Beneficios Upgrade</td>
                <td class="price-cell">${{ number_format($data['total_upgrade'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="section-label">2. ANÁLISIS DE COSTOS POR POBLACIÓN</div>
    <table id="costos" class="preview-table">
        <thead>
            <tr>
                <th class="col-label">RANGO DE EDAD - POBLACIÓN</th>
                @foreach($data['all_coverages'] as $cov) <th class="col-data">US$ {{ (int) $cov->price }}</th> @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data['age_analysis'] as $row)
                @php
    $firstCoverage = collect($row['costs_by_coverage'])->first();
    $population = $firstCoverage['pop'] ?? 0;
                @endphp
                <tr>
                    <td class="col-label">{{ $row['age_range'] }} años - {{ $population }} Persona(s)</td>

                    @foreach($data['all_coverages'] as $cov)
                        @php $cell = $row['costs_by_coverage'][$cov->id] ?? null; @endphp
                        <td class="price-cell">{{ $cell ? '$' . number_format($cell['total'], 0) : '-' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>


    <div class="section-label">3. TARIFAS TOTALES POR FRECUENCIA DE PAGO</div>
    <table id="totales" class="preview-table">
        <tbody>
            <tr class="total-summary-row">
                <td class="col-label">TOTAL ANUALIZADO (100%)</td>
                @foreach($data['summary_columns'] as $val)
                    <td class="col-data">${{ number_format($val, 2) }}</td>
                @endforeach
            </tr>
            <tr class="subtotal-row">
                <td class="col-label">VALOR SEMESTRAL (50%)</td>
                @foreach($data['summary_columns'] as $val)
                    <td class="col-data">${{ number_format($val / 2, 2) }}</td>
                @endforeach
            </tr>
            <tr class="subtotal-row">
                <td class="col-label">VALOR MENSUAL (12 Cuotas)</td>
                @foreach($data['summary_columns'] as $val)
                    <td class="col-data">${{ number_format($val / 12, 2) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    {{-- <div class="footer">
        Cotización válida por 30 días. Sujeta a políticas de suscripción y verificación de datos.
    </div> --}}
    <!-- ========== FOOTER ========== -->
    <div class="footer">
        <div class="footer-note">
            Cotización generada el {{ now()->format('d/m/Y H:i') }} |
            Sistema: {{ config('app.name', 'Sistema de Cotizaciones') }}
        </div>
        <div class="footer-disclaimer">
            Esta cotización es válida por 15 días calendario a partir de la fecha de emisión.
            Los valores están sujetos a verificación de información, políticas de suscripción y
            autorización final por parte de la compañía aseguradora. No constituye una póliza vigente.
            <span style="font-size: 7pt; text-align: center; display: block;"><a href="https://integracorp.tudrgroup.com/storage/condicionados/CondicionesESPECIAL.pdf">Condiciones Generales del Plan</a></span>
        </div>
    </div>


</body>

</html>