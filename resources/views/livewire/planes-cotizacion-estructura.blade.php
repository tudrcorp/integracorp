@php
    use App\Support\QuotePdfCoverageTable;
    use App\Support\QuotePdfPlanStructure;

    $note = QuotePdfPlanStructure::acuteIllnessNote();
    $priceColumnCount = count($coverageColumns);
@endphp

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $planTitle }}</title>
    <style>
        /*
         * Márgenes con padding en celdas de tabla, no con @page ni con un div al
         * 100%: esta página se embebe dentro de otro documento que ya declara su
         * @page, y un div con padding desborda a la derecha aun con box-sizing.
         *
         * Cada bloque va en su propia fila, y no todo dentro de una sola celda,
         * porque DomPDF no parte una celda entre páginas: con un plan de muchos
         * beneficios empujaba la hoja entera y dejaba la primera en blanco.
         */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #1f2937;
        }

        .page-frame {
            width: 100%;
            border-collapse: collapse;
            border: 0;
        }

        .page-cell {
            padding: 0 15mm;
            border: 0;
            vertical-align: top;
        }

        .page-cell-first {
            padding-top: 15mm;
        }

        .page-cell-last {
            padding-bottom: 15mm;
        }

        .is-compact .header-table {
            margin-bottom: 10px;
        }

        .is-compact .page-cell-first {
            padding-top: 10mm;
        }

        .is-compact .page-cell-last {
            padding-bottom: 8mm;
        }

        .is-compact .validity {
            margin: 10px 0 6px 0;
        }

        .is-compact .note-title {
            margin: 10px 0 2px 0;
        }

        .is-compact .data-table {
            margin-top: 10px;
        }

        .is-compact .data-table th,
        .is-compact .data-table td {
            padding: 3px 3px;
            font-size: 6.5pt;
        }

        .is-compact .page-cell-last {
            padding-bottom: 6mm;
        }

        /*
         * Sin altura fija ni spacer alto: DomPDF los interpreta como
         * «no cabe» y manda el pie a la página 2 aunque haya espacio vacío.
         * El pie va pegado al contenido, centrado, con el QR a la derecha.
         */
        .storefront-footer-slot {
            vertical-align: top;
            padding-top: 16mm;
            padding-bottom: 6mm;
            page-break-before: avoid;
            page-break-inside: avoid;
        }

        .storefront-footer {
            position: relative;
            width: 100%;
            min-height: 58px;
            page-break-inside: avoid;
        }

        .storefront-footer__copy {
            width: 100%;
            text-align: center;
            color: #052F60;
            font-size: 6pt;
            line-height: 1.25;
        }

        .storefront-footer__copy img {
            width: 72px;
            height: auto;
            margin-bottom: 2px;
        }

        .storefront-footer__copy p {
            margin: 0;
        }

        .storefront-footer__qr {
            position: absolute;
            right: 0;
            top: 0;
            width: 64px;
            text-align: center;
        }

        .storefront-footer__qr a {
            color: #052F60;
            text-decoration: none;
        }

        .storefront-footer__qr img {
            width: 48px;
            height: 48px;
            border: 0;
        }

        .storefront-footer__qr-caption {
            display: block;
            margin-top: 2px;
            font-size: 5pt;
            color: #052F60;
            line-height: 1.2;
            font-weight: bold;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 26px;
        }

        .plan-title {
            color: #29ABE2;
            font-size: 15pt;
            font-style: italic;
            font-weight: bold;
            margin: 0;
        }

        .meta {
            font-size: 9.5pt;
            margin: 0 0 6px 0;
        }

        .validity {
            font-size: 8.5pt;
            font-style: italic;
            font-weight: bold;
            margin: 22px 0 10px 0;
        }

        .note-title {
            color: #29ABE2;
            font-size: 9.5pt;
            font-weight: bold;
            margin: 24px 0 4px 0;
        }

        .note-body {
            font-size: 8.5pt;
            margin: 0;
            line-height: 1.5;
        }

        .conditions {
            font-size: 8.5pt;
            margin: 16px 0 0 0;
            padding-left: 16px;
            line-height: 1.45;
        }

        .conditions li {
            margin-bottom: 3px;
        }

        /*
         * table-layout fijo: sin esto un beneficio de nombre largo estira la
         * tabla y empuja las últimas columnas fuera de la hoja.
         */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 24px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #d7e7f2;
            padding: 5px 4px;
            font-size: 7pt;
            vertical-align: middle;
            word-wrap: break-word;
        }

        .data-table th {
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            background-color: #082f62;
            border-color: #082f62;
        }

        .label-col {
            text-align: left;
        }

        .value-col {
            text-align: center;
            font-weight: bold;
        }

        .total-label {
            color: #ffffff;
            font-weight: bold;
            background-color: #082f62;
            border-color: #082f62;
            text-align: left;
        }
    </style>
</head>

<body class="{{ $compact ? 'is-compact' : '' }}">
    {{-- Un solo hijo de body: Livewire (con APP_DEBUG) cuenta raíces en <body>
         y el pie fijo no puede vivir como segundo hermano del page-frame. --}}
    <div class="pdf-root">
    <table class="page-frame">
        <tr>
            <td class="page-cell page-cell-first">
                <table class="header-table">
                    <tr>
                        <td style="vertical-align: top; border: 0; padding: 0;">
                            <p class="plan-title">{{ $planTitle }}</p>
                        </td>
                        <td style="vertical-align: top; border: 0; padding: 0; width: 30%; text-align: right;">
                            <img src="{{ public_path('image/logoNewPdf.png') }}" style="width: 165px; height: auto;"
                                alt="Tu Doctor En Casa">
                        </td>
                    </tr>
                </table>

                <p class="meta"><strong>Datos del afiliado titular:</strong> Sr(a): {{ $name }}</p>
                <p class="meta"><strong>Nombre del Agente:</strong> {{ $name_user }}</p>
                <p class="meta"><strong>Fecha de emisión:</strong> {{ now()->format('d/m/Y') }}</p>
                <p class="meta"><strong>Nro. Control:</strong> {{ $number_control }}</p>

                <p class="validity">{{ QuotePdfPlanStructure::validityNote() }}</p>
            </td>
        </tr>
        <tr>
            <td class="page-cell">
                @include('livewire.partials.quote-pdf-benefits-table', [
                    'benefitColumns' => $benefitColumns,
                    'benefitRows' => $benefitRows,
                    'headerBackground' => '#29ABE2',
                    'isDense' => $isDense,
                    'compact' => $compact,
                ])

                <p class="note-title">{{ $note['title'] }}</p>
                <p class="note-body">{{ $note['body'] }}</p>
            </td>
        </tr>
        <tr>
            <td class="page-cell">
                @if ($priceColumnCount > 0)
                    {{-- Tarifas y totales en una sola tabla: separarlas hacía que
                         cada una calculara sus columnas por su cuenta y los totales
                         quedaran desalineados de las tarifas que resumen. --}}
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="label-col" style="width: 26%;">RANGO DE EDAD</th>
                                <th style="width: 16%;">POBLACIÓN</th>
                                @foreach ($coverageColumns as $coverage)
                                    <th>TARIFA ANUAL<br>US$ {{ QuotePdfCoverageTable::formatLabel($coverage) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tableRows as $row)
                                <tr>
                                    <td class="label-col" style="font-weight: bold;">{{ $row['age_range'] }} años</td>
                                    <td class="value-col">{{ $row['total_persons'] }} persona(s)</td>
                                    @foreach ($coverageColumns as $coverage)
                                        @php
                                            $key = QuotePdfCoverageTable::coverageKey($coverage);
                                            $amount = $row['amounts'][$key] ?? null;
                                        @endphp
                                        <td class="value-col">{{ $amount !== null ? round($amount).' US$' : '-' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach

                            @foreach ([['TARIFA GRUPAL ANUAL', 1], ['TARIFA GRUPAL SEMESTRAL', 2], ['TARIFA GRUPAL TRIMESTRAL', 4]] as [$totalLabel, $divisor])
                                <tr>
                                    <td class="total-label" colspan="2">{{ $totalLabel }}</td>
                                    @foreach ($coverageColumns as $coverage)
                                        @php
                                            $key = QuotePdfCoverageTable::coverageKey($coverage);
                                            $total = $totals[$key] ?? null;
                                        @endphp
                                        <td class="value-col">
                                            {{ $total !== null ? round($total / $divisor).' US$' : '-' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    {{-- Paquete de beneficios: una sola tarifa por rango de edad,
                         sin columnas de cobertura que desglosar. --}}
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="label-col" style="width: 28%;">RANGO DE EDAD</th>
                                <th>POBLACIÓN</th>
                                <th>TOTAL ANUAL</th>
                                <th>TOTAL SEMESTRAL</th>
                                <th>TOTAL TRIMESTRAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($flatRateRows as $flatRow)
                                <tr>
                                    <td class="label-col" style="font-weight: bold;">{{ $flatRow['age_range'] }} años</td>
                                    <td class="value-col">{{ $flatRow['total_persons'] }} persona(s)</td>
                                    <td class="value-col">{{ round($flatRow['annual']) }} US$</td>
                                    <td class="value-col">{{ round($flatRow['biannual']) }} US$</td>
                                    <td class="value-col">{{ round($flatRow['quarterly']) }} US$</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
        </tr>
        @if ($showConditions)
            <tr>
                <td class="page-cell page-cell-last">
                    <ul class="conditions">
                        @foreach (QuotePdfPlanStructure::conditions() as $condition)
                            <li>{{ $condition }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        @endif

        @if ($storefrontFooter)
            @php
                $paymentQr = null;
                $paymentUrl = null;

                try {
                    $paymentUrl = \App\Support\Storefront\StorefrontPaymentMethodsDocument::publicUrl();
                    $paymentQr = \App\Support\Storefront\StorefrontPaymentMethodsDocument::qrDataUri();
                } catch (Throwable) {
                    $paymentQr = null;
                    $paymentUrl = null;
                }
            @endphp
            <tr>
                <td class="page-cell page-cell-last storefront-footer-slot">
                    <div class="storefront-footer">
                        @if (filled($paymentQr) && filled($paymentUrl))
                            <div class="storefront-footer__qr">
                                <a href="{{ $paymentUrl }}" title="Descargar métodos de pago">
                                    <img src="{{ $paymentQr }}" alt="Métodos de pago">
                                    <span class="storefront-footer__qr-caption">Haz clic aquí<br>o escanea</span>
                                </a>
                            </div>
                        @endif
                        <div class="storefront-footer__copy">
                            <img src="{{ public_path('image/logoNewPdf.png') }}" alt="Tu Dr En Casa">
                            <p><strong>Cotización generada por Integracorp-pwa</strong></p>
                            <p>Teléfonos de Contacto: 0424-222-0056 / 0424227-1498</p>
                            <p>Email: comercial@tudrencasa.com</p>
                            <p>Instagram: @tudrencasa</p>
                        </div>
                    </div>
                </td>
            </tr>
        @endif
    </table>
    </div>
</body>

</html>
