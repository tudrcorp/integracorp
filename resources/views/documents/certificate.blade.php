<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Certificado de Afiliación</title>

    <style>
        /*
         * El certificado pagina solo: la población corporativa puede tener miles de afiliados.
         * Por eso nada del contenido usa `position: absolute` (antes la firma vivía en
         * `top: 930px` y se montaba sobre la tabla) y el marco de la marca es un elemento
         * `fixed`, que DomPDF repite en todas las páginas.
         */
        @page {
            /*
             * Margen derecho de 90px: la línea azul del marco cae en x≈713 de los 794px de la
             * página, así que el contenido debe terminar antes para no montarse sobre ella.
             */
            margin: 132px 90px 104px 58px;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 10px;
            color: #000000;
        }

        .page-frame {
            position: fixed;
            top: -132px;
            left: -58px;
            width: 794px;
            height: 1123px;
        }

        .page-frame img {
            width: 794px;
            height: 1123px;
        }

        .allied-logo {
            position: fixed;
            top: -108px;
            right: -18px;
            text-align: right;
        }

        .allied-logo img {
            max-width: 170px;
            max-height: 82px;
        }

        .allied-name {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
        }

        h2.section-title {
            margin: 0 0 8px 0;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            font-family: 'Helvetica', Arial, sans-serif;
        }

        h2.section-title.spaced {
            margin-top: 20px;
        }

        table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        /* Datos principales de la afiliación */
        .table-info td {
            padding: 3px 6px 3px 0;
            vertical-align: top;
            font-size: 11px;
            font-family: 'Helvetica', Arial, sans-serif;
        }

        .table-info .label {
            color: #575757;
            font-weight: bold;
            text-transform: uppercase;
            width: 128px;
        }

        .table-info .value {
            color: #000000;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        /* Población */
        .table-people thead {
            display: table-header-group;
        }

        .table-people tr {
            page-break-inside: avoid;
        }

        .table-people th {
            background-color: #b5b5b5;
            color: #ffffff;
            border: 1px solid #cccccc;
            padding: 5px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }

        .table-people td {
            border: 1px solid #cccccc;
            padding: 4px 6px;
            text-align: left;
            font-size: 9.5px;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        /* Beneficios */
        .benefits-block {
            margin-top: 14px;
        }

        .table-benefits td {
            border-bottom: 1px solid #cccccc;
            padding: 5px 4px;
            font-size: 9px;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .table-benefits tr {
            page-break-inside: avoid;
        }

        .table-benefits .mark {
            width: 92px;
            text-align: right;
            padding-right: 10px;
        }

        .table-benefits .amount {
            font-size: 13px;
            font-weight: bold;
        }

        .benefit-note {
            margin: 8px 0 0 0;
            font-size: 8px;
            text-align: justify;
            page-break-inside: avoid;
        }

        /*
         * La numeración va con contadores CSS: `DomPdfBatchRenderOptions` arranca el motor con
         * `isPhpEnabled => false`, así que un <script type="text/php"> jamás llega a ejecutarse.
         */
        .page-number {
            position: fixed;
            /*
             * DomPDF ancla los elementos `fixed` al área de contenido, no al papel: por eso el
             * desplazamiento negativo, que lo baja hasta la franja libre bajo el pie del marco.
             */
            bottom: -78px;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 7.5px;
            color: #7a7a7a;
        }

        .page-number:after {
            /* `counter(pages)` devuelve 0 con este motor; se numera sin total. */
            content: "Página " counter(page);
        }

        .signature {
            margin-top: 26px;
            page-break-inside: avoid;
        }

        .signature img {
            width: 180px;
            height: 70px;
        }

        .signature.allied {
            text-align: center;
        }

        .signature.allied img {
            width: auto;
            max-width: 260px;
            height: auto;
            max-height: 90px;
        }
    </style>
</head>

<body>
    @php
        $brandColor = $brandColor ?? '#26b2ca';
        $logoDataUri = $logoDataUri ?? '';
        $signatureDataUri = $signatureDataUri ?? '';
        $isAlliedCertificate = $isAlliedCertificate ?? false;
        $companyName = $companyName ?? '';
        $pagador = $pagador ?? [];
        $affiliateTableRows = $affiliateTableRows ?? [];
        $coberturaFormatted = $coberturaFormatted ?? '0,00';
        $showPlanColumn = $showPlanColumn ?? false;
        $benefitSections = $benefitSections ?? [[
            'plan_id' => $pagador['plan_id'] ?? null,
            'plan_label' => '',
            'rows' => $beneficiosRows ?? [],
            'note' => null,
        ]];
        $peopleHeaders = ['Nombre y apellido', 'Documento de identidad', 'Fecha de nacimiento', 'Parentesco'];
        if ($showPlanColumn) {
            $peopleHeaders[] = 'Plan';
        }
    @endphp

    <div class="page-number"></div>

    {{-- Marco de la marca: primero en el documento para que el contenido se dibuje encima. --}}
    @if (! $isAlliedCertificate)
        <div class="page-frame">
            <img src="{{ public_path('storage/certificados/fondo-certificado.png') }}" alt="">
        </div>
    @elseif ($logoDataUri !== '')
        <div class="allied-logo">
            <img src="{{ $logoDataUri }}" alt="{{ $companyName }}">
        </div>
    @elseif ($companyName !== '')
        <div class="allied-logo">
            <span class="allied-name" style="color: {{ $brandColor }};">{{ $companyName }}</span>
        </div>
    @endif

    <h2 class="section-title" style="color: {{ $brandColor }};">Certificado de afiliación</h2>

    <table class="table-info">
        <tbody>
            <tr>
                <td class="label">Contratante:</td>
                <td class="value">{{ $pagador['name'] ?? '' }}</td>
                <td class="label">Agente:</td>
                <td class="value">{{ $pagador['agente_agencia'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Código de afiliación:</td>
                <td class="value">{{ $pagador['code'] ?? '' }}</td>
                <td class="label">Tarifa anual:</td>
                <td class="value">US$ {{ number_format((float) ($pagador['tarifa_anual'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Plan:</td>
                <td class="value">{{ $pagador['plan'] ?? '' }}</td>
                <td class="label">Frecuencia de pago:</td>
                <td class="value">{{ $pagador['frecuencia_pago'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="label">Fecha de afiliación:</td>
                <td class="value">{{ $pagador['fecha_afiliacion'] ?? '' }}</td>
                <td class="label">Tarifa periodo:</td>
                <td class="value">US$ {{ number_format((float) ($pagador['tarifa_periodo'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">Vigencia:</td>
                <td class="value">
                    Desde: {{ $pagador['fecha_vigencia'] ?? '' }}<br>
                    Hasta: {{ $pagador['fecha_vigencia_final'] ?? '' }}
                </td>
                <td class="label">Periodo facturado:</td>
                <td class="value">
                    Desde: {{ $pagador['fecha_vigencia'] ?? '' }}<br>
                    Hasta: {{ $pagador['periodo_facturado_hasta'] ?? '' }}
                </td>
            </tr>
        </tbody>
    </table>

    <h2 class="section-title spaced" style="color: {{ $brandColor }};">Datos de afiliado y beneficiarios</h2>

    <table class="table-people">
        <thead>
            <tr>
                @foreach ($peopleHeaders as $header)
                    <th @if ($loop->first) style="width: 34%;" @endif>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($affiliateTableRows as $index => $celda)
                <tr style="background-color: {{ $index % 2 === 0 ? '#ffffff' : '#f7f7f7' }};">
                    <td>{{ $celda['full_name'] }}</td>
                    <td>{{ $celda['nro_identificacion'] }}</td>
                    <td>{{ $celda['birth_date'] }}</td>
                    <td>{{ $celda['relationship'] }}</td>
                    @if ($showPlanColumn)
                        <td>{{ $celda['plan_label'] ?? '' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($benefitSections as $section)
        @continue(empty($section['rows']))

        <div class="benefits-block">
            <h2 class="section-title" style="color: {{ $brandColor }};">
                @if (filled($section['plan_label'] ?? '') && count($benefitSections) > 1)
                    Beneficios del {{ $section['plan_label'] }}
                @else
                    Beneficios del plan seleccionado
                @endif
            </h2>

            <table class="table-benefits">
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            <td>{{ $row['text'] }}</td>
                            <td class="mark">
                                @if ($row['show_cobertura'])
                                    <span class="amount" style="color: {{ $isAlliedCertificate ? $brandColor : '#000000' }};">US$ {{ $coberturaFormatted }}</span>
                                @else
                                    <img src="{{ public_path('storage/certificados/check-beneficios.png') }}" style="width: 12px; height: 12px;" alt="">
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (filled($section['note'] ?? null))
                <p class="benefit-note">{{ $section['note'] }}</p>
            @endif
        </div>
    @endforeach

    @if ($isAlliedCertificate)
        @if ($signatureDataUri !== '')
            <div class="signature allied">
                <img src="{{ $signatureDataUri }}" alt="Firma {{ $companyName }}">
            </div>
        @endif
    @else
        <div class="signature">
            <img src="{{ public_path('storage/certificados/firmaHC-Certificados.png') }}" alt="Firma autorizada">
        </div>
    @endif
</body>

</html>
