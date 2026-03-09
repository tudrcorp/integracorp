<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">
    <title>{{ $data['title'] }}</title>
    <style>
        @page {
            margin: 0.8cm 1.2cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            line-height: 1.2;
            font-size: 9px;
            /* Ajuste para acomodar nueva columna */
            margin: 0;
            padding: 0;
        }

        .header {
            border-bottom: 1.5px solid #3b82f6;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header-table {
            width: 100%;
        }

        .header-table td {
            vertical-align: start;
            border: none;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        .meta-info {
            text-align: right;
            font-size: 9px;
            color: #64748b;
        }

        .section-header {
            background-color: #f8fafc;
            color: #1e40af;
            padding: 4px 8px;
            font-weight: bold;
            font-size: 10px;
            margin-top: 10px;
            margin-bottom: 6px;
            border-left: 3px solid #3b82f6;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }

        th,
        td {
            border: 0.5px solid #cbd5e1;
            padding: 4px 5px;
            text-align: center;
            word-wrap: break-word;
        }

        th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 10px;
            font-weight: bold;
        }

        .col-label {
            width: 30%;
            /* Reducido para dar espacio a población */
            text-align: left !important;
            /* font-weight: bold; */
            background-color: #f8fafc;
        }

        .col-label-age-range {
            width: 30%;
            /* Reducido para dar espacio a población */
            text-align: center !important;
            font-weight: bold;
            background-color: #f8fafc;
        }

        .col-pop {
            width: 10%;
            /* Espacio para la columna de población */
            background-color: #f1f5f9;
            font-weight: bold;
        }

        .text-blue {
            color: #2563eb;
            font-weight: bold;
        }

        .text-green {
            color: #10b981;
            font-weight: bold;
        }

        .bg-total {
            background-color: #1e3a8a !important;
            color: #ffffff !important;
        }

        .bg-subtotal {
            background-color: #f8fafc;
            font-weight: bold;
        }

        .check-cell {
            color: #10b981;
            font-weight: bold;
            font-size: 14px;
        }

        /* .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            padding-top: 5px;
        } */

        /* ========== FOOTER ========== */
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 8pt;
            color: #475569;
            line-height: 1.4;
            page-break-inside: avoid;
        }

        .footer-note {
            font-style: italic;
            color: #64748b;
            margin: 4px 0;
        }

        .footer-disclaimer {
            background-color: #f8fafc;
            padding: 8px 15px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 7.5pt;
            color: #1e40af;
            border-left: 3px solid #3b82f6;
        }

        .preview-container 
        { 
            background: white; 
            color: #1a1a1a; 
            margin-top: 10px;
            padding: 5px; 
            font-family: 'Inter', sans-serif; 
            position: relative; 
        }

        .preview-logo 
        { 
            position: absolute; 
            top: 5px; 
            right: 5px; 
            width: 100px; 
            text-align: right; 
        }

        .preview-logo img 
        { 
            width: 100%; 
            height: auto; 
            max-width: 120px; 
            margin-bottom: 5px; 
        }
        
        .preview-header 
        { 
            padding-bottom: 15px; 
            margin-bottom: 25px; 
            margin-right: 160px; 
        }
        
        .preview-title 
        { 
            font-size: 12px; 
            font-weight: 800; 
            color: #1e3a8a; 
            margin: 0; 
            text-transform: uppercase; 
        }
        
        .preview-subtitle 
        { 
            font-size: 12px; 
            color: #64748b; 
            margin: 5px 0 0; 
        }

    </style>

</head>

<body>
    <div class="preview-container">
        <div class="preview-logo">
            <img src="{{ asset('image/logoNewPdf.png') }}" alt="Logo">
            <div style="font-size: 8px; color: #94a3b8;">Fecha: {{ $data['date'] }}</div>
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

    <div class="section-header">1. BENEFICIOS Y COBERTURAS</div>
    <table id="beneficios">
        <thead>
            <tr>
                <th class="col-label">Descripción del Beneficio</th>
                @foreach($data['all_coverages'] as $cov)
                    <th>US$ {{ (int) $cov->price }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data['benefits_processed'] as $b)
                <tr>
                    <td class="col-label" style="font-size: 8px;">{{ $b['name'] }}</td>
                    @foreach($data['all_coverages'] as $cov)
                        @php
                            $exit = App\Models\BenefitCoverage::where('benefit_id', $b['id'])->where('coverage_id', $cov->id)->first();
                        @endphp
                        <td>
                            {{-- Lógica corregida para usar el mapeo de benefit_coverages pre-procesado --}}
                            @if($exit)
                                <span class="text-blue">US$ {{ (int) $cov->price }}</span>
                            @else
                                <span style="color: #cbd5e1;"><img src="{{ asset('image/checkPng.png') }}" alt="Check" style="width: 14px; height: auto;"></span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($data['upgrade_benefits']))
    <div class="section-header" style="margin-top: 20px;">1.1 BENEFICIOS UPGRADE SELECCIONADOS</div>
    <table id="upgrade-benefits">
        <thead>
            <tr>
                <th class="col-label">Descripción del beneficio</th>
                <th style="text-align: right;">Valor (US$)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['upgrade_benefits'] as $ub)
                <tr>
                    <td class="col-label">{{ $ub['name'] }}</td>
                    <td style="text-align: right; font-weight: bold;">${{ number_format($ub['pvp'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="bg-subtotal">
                <td class="col-label">Subtotal Beneficios Upgrade</td>
                <td style="text-align: right; font-weight: bold;">${{ number_format($data['total_upgrade'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="section-header section-label" style="margin-top: 30px;">2. ANÁLISIS DE COSTOS POR POBLACIÓN</div>
    <table id="costos">
        <thead>
            <tr>
                <th class="col-label">RANGO DE EDAD - POBLACIÓN</th>
                {{-- <th class="col-pop">POB.</th> --}}
                @foreach($data['all_coverages'] as $cov) <th>US$ {{ (int) $cov->price }}</th> @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data['age_analysis'] as $row)
                @php
    // Extraemos la población de la primera cobertura disponible en el rango
    $firstCoverage = collect($row['costs_by_coverage'])->first();
    $population = $firstCoverage['pop'] ?? 0;
                @endphp
                <tr>
                    <td class="col-label">{{ $row['age_range'] }} años - {{ $population }} Persona(s)</td>

                    @foreach($data['all_coverages'] as $cov)
                        @php $cell = $row['costs_by_coverage'][$cov->id] ?? null; @endphp
                        <td>{{ $cell ? '$' . number_format($cell['total'], 0) : '-' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>


    <div class="section-header" style="margin-top: 30px;">3. TARIFAS TOTALES POR FRECUENCIA DE PAGO</div>
    <table id="totales">
        <tbody>
            <tr class="bg-subtotal" style="background-color: #2563eb;">
                <td class="col-label" style="background-color: #2563eb;">TOTAL ANUALIZADO (100%)</td>
                @foreach($data['summary_columns'] as $val)
                    <td>${{ number_format($val, 2) }}</td>
                @endforeach
            </tr>
            <tr class="bg-subtotal">
                <td class="col-label">VALOR SEMESTRAL (50%)</td>
                @foreach($data['summary_columns'] as $val)
                    <td>${{ number_format($val / 2, 2) }}</td>
                @endforeach
            </tr>
            <tr class="bg-subtotal">
                <td class="col-label">VALOR MENSUAL (12 Cuotas)</td>
                @foreach($data['summary_columns'] as $val)
                    <td>${{ number_format($val / 12, 2) }}</td>
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
            <span style="font-size: 10px; text-align: center; display: block;"><a href="https://integracorp.tudrgroup.com/storage/condicionados/CondicionesESPECIAL.pdf">Condiciones Generales del Plan</a></span>
        </div>
    </div>


</body>

</html>