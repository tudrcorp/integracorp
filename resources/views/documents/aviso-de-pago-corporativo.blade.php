<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago</title>

    <style>
        /**
         * Márgenes de hoja: en DomPDF el margin del body es más fiable que padding en contenedores al 100%.
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
            margin: 15mm 20mm 0 20mm;
            padding: 0;
            width: auto;
            max-width: 100%;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #000000;
        }

        /**
         * Marca de agua de seguridad: logo al 85% del área útil, centrado, baja opacidad.
         */
        .document-watermark {
            position: fixed;
            top: 50%;
            left: 7.5%;
            width: 85%;
            max-width: 85%;
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
            text-align: center;
            transform: translateY(-50%);
        }

        .document-watermark img {
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .doc-root {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 100%;
            /* Reserva espacio para pie fijo sin forzar segunda hoja (95mm generaba página en blanco). */
            padding: 0 0 62mm 0;
        }

        .doc-header-table {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .doc-header-table td {
            vertical-align: top;
            padding: 0;
            word-wrap: break-word;
        }

        .doc-header-logo img {
            width: 200px;
            max-width: 100%;
            height: auto;
            display: block;
        }

        .doc-header-meta {
            text-align: right;
            font-size: 12px;
            text-transform: uppercase;
            line-height: 1.1;
            padding-left: 8px;
        }

        .doc-header-meta p {
            margin: 0 0 1px 0;
            line-height: 1.1;
        }

        .client-info {
            margin-top: 22px;
            margin-bottom: 10px;
            font-size: 12px;
            text-transform: uppercase;
            line-height: 1.1;
            width: 100%;
            max-width: 100%;
        }

        .client-info p {
            margin: 0 0 2px 0;
            line-height: 1.1;
        }

        .client-info .client-address {
            margin: 0 0 2px 0;
            line-height: 1.15;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            max-width: 100%;
        }

        .client-info .client-address strong {
            display: inline;
        }

        .client-info .client-address-value {
            font-weight: bold;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            white-space: normal;
        }

        .plans-section {
            margin-top: 4px;
            margin-bottom: 6px;
        }

        table.plans-table {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            border-spacing: 0;
            font-size: 11px;
        }

        table.plans-table thead th {
            padding: 6px 4px;
            text-align: left;
            border-bottom: 2px solid #000000;
            text-transform: uppercase;
        }

        table.plans-table thead th.desc-col {
            width: 72%;
        }

        table.plans-table thead th.amount-col {
            width: 28%;
            text-align: right;
        }

        table.plans-table tbody td {
            padding: 3px 4px;
            vertical-align: top;
            text-align: left;
            word-wrap: break-word;
        }

        table.plans-table tbody td.amount-col {
            width: 28%;
            text-align: right;
            white-space: nowrap;
        }

        table.plans-table tbody tr.total-row td {
            font-weight: bold;
            text-align: right;
            padding-top: 6px;
        }

        table.plans-table tbody tr.vigencia-row td {
            font-weight: bold;
            text-align: right;
            padding-top: 4px;
            font-size: 10px;
        }

        .plan-line {
            margin: 0;
            line-height: 1.15;
            text-transform: uppercase;
            font-size: 10px;
        }

        .payment-info {
            margin: 6px 0 0 0;
            font-size: 11px;
            text-transform: uppercase;
            line-height: 1.15;
        }

        .payment-info p {
            margin: 0 0 3px 0;
            line-height: 1.15;
        }

        .stamp-section {
            position: fixed;
            bottom: 58mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            margin: 0;
            z-index: 5;
        }

        .stamp-section img {
            width: 110px;
            height: auto;
            display: inline-block;
        }

        /**
         * Pie legal fijo, alineado a la derecha, justo encima del banner decorativo.
         */
        .footer-legal {
            position: fixed;
            bottom: 26mm;
            left: 20mm;
            right: 20mm;
            text-align: right;
            font-size: 9px;
            text-transform: uppercase;
            line-height: 1.05;
            z-index: 10;
        }

        .footer-legal p {
            margin: 0;
            padding: 0;
            line-height: 1.05;
            text-align: right;
        }

        .footer-legal p + p {
            margin-top: 1px;
        }

        .footer-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 11;
            line-height: 0;
        }

        .footer-banner img {
            width: 100%;
            height: auto;
            display: block;
            margin: 0;
        }
    </style>
</head>

<body>

    <div class="document-watermark" aria-hidden="true">
        <img src="{{ public_path('image/logoNewTDG.png') }}" alt="">
    </div>

    <div class="doc-root">

        <table class="doc-header-table">
            <tr>
                <td style="width: 48%;">
                    <div class="doc-header-logo">
                        <img src="{{ public_path('storage/administracion/logoNewPdfTDEC.png') }}" alt="Tu Doctor en Casa">
                    </div>
                </td>
                <td style="width: 52%;">
                    <div class="doc-header-meta">
                        <p><strong>Recibo de Pago: Nro. {{ $data['invoice_number'] }}</strong></p>
                        <p><strong>Fecha de Emisión: {{ $data['emission_date'] }}</strong></p>
                        <p><strong>Condiciones de Pago: Contado</strong></p>
                    </div>
                </td>
            </tr>
        </table>

        <div class="client-info">
            <p><strong>A Nombre de: {{ $data['full_name_ti'] }}</strong></p>
            <p><strong>Documento: J-{{ $data['ci_rif_ti'] }}</strong></p>
            <p class="client-address">
                <strong>Dirección:</strong>
                <span class="client-address-value">{{ $data['address_ti'] }}</span>
            </p>
            <p><strong>Teléfono: {{ $data['phone_ti'] }}</strong></p>
            <p><strong>Correo: {{ $data['email_ti'] }}</strong></p>
        </div>

        @php
            $fechaHasta = now()->format('d/m/Y');
        @endphp

        <div class="plans-section">
            <table class="plans-table">
                <thead>
                    <tr>
                        <th class="desc-col">Descripción</th>
                        <th class="amount-col">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < count($data['plan']); $i++)
                        @php
                            $plan = \App\Models\Plan::where('id', $data['plan'][$i]['plan_id'])->first()->description;

                            if ($plan == 'PLAN INICIAL') {
                                $coverage = '';
                            } else {
                                $coverage = \App\Models\Coverage::where('id', $data['plan'][$i]['coverage_id'])->first()->price;
                            }

                            if ($data['plan'][$i]['payment_frequency'] == 'ANUAL') {
                                $total_amount = $data['plan'][$i]['subtotal_anual'];
                                $fechaHasta = date('d/m/Y', strtotime('+1 years'));
                            }
                            if ($data['plan'][$i]['payment_frequency'] == 'TRIMESTRAL') {
                                $total_amount = $data['plan'][$i]['subtotal_quarterly'];
                                $fechaHasta = date('d/m/Y', strtotime('+3 months'));
                            }
                            if ($data['plan'][$i]['payment_frequency'] == 'SEMESTRAL') {
                                $total_amount = $data['plan'][$i]['subtotal_semestral'];
                                $fechaHasta = date('d/m/Y', strtotime('+6 months'));
                            }
                            if ($data['plan'][$i]['payment_frequency'] == 'MENSUAL') {
                                $total_amount = $data['plan'][$i]['subtotal_anual'] / 12;
                                $fechaHasta = date('d/m/Y', strtotime('+1 months'));
                            }

                            $age_range = \App\Models\AgeRange::where('id', $data['plan'][$i]['age_range_id'])->first()->range;
                        @endphp
                        <tr>
                            <td class="desc-col">
                                <p class="plan-line">
                                    {{ $plan }}@if ($coverage !== ''), COBERTURA: {{ round($coverage) }}US$@endif<br>
                                    RANGO DE EDAD: {{ $age_range }} años<br>
                                    FRECUENCIA DE PAGO: {{ $data['plan'][$i]['payment_frequency'] }}<br>
                                    COBERTURA GEOGRAFICA – LOCAL VENEZUELA
                                </p>
                            </td>
                            <td class="amount-col">{{ number_format($total_amount, 2) }}US$</td>
                        </tr>
                    @endfor

                    <tr class="total-row">
                        <td colspan="2">Monto Total: {{ number_format($data['total_amount'], 2) }}US$</td>
                    </tr>

                    @php
                        if (! empty($data['desde'] ?? null) && ! empty($data['hasta'] ?? null)) {
                            $vigenciaDesde = $data['desde'];
                            $vigenciaHasta = $data['hasta'];
                        } else {
                            $vigenciaDesde = now()->format('d/m/Y');
                            $vigenciaHasta = $fechaHasta;
                        }
                    @endphp

                    <tr class="vigencia-row">
                        <td colspan="2">
                            PERÍODO DE VIGENCIA DESDE EL {{ $vigenciaDesde }} HASTA EL {{ $vigenciaHasta }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="payment-info">
            <p><strong>Ref. de Pago: {{ $data['reference'] }}</strong></p>
            <p><strong>{{ $data['payment_method'] }}</strong></p>
            <p><strong>Validado: Dpto. Finanzas: {{ $data['emission_date'] }}</strong></p>
        </div>

    </div>

    <div class="stamp-section">
        <img src="{{ public_path('storage/sello-nuevo.png') }}" alt="">
    </div>

    <div class="footer-legal">
        <p>TU DOCTOR EN CASA, C. A. J-503583681</p>
        <p>Oficina Comercial: Av. Francisco de Miranda,<br>
            Centro Lido, Torre A, Ofic.124<br> El Rosal Caracas - Venezuela</p>
        <p>Teléfono.: (+58) 212 308 28 55 / 0424-287-5732</p>
        <p>Email: administracion@tudrencasa.com</p>
        <p>www.tudrencasa.com</p>
    </div>

    <div class="footer-banner">
        <img src="{{ public_path('storage/bannerFooterv2.png') }}" alt="">
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $pdf->text(480, 800, "Pag $PAGE_NUM/$PAGE_COUNT", $font, 10);
            ');
        }
    </script>
</body>

</html>
