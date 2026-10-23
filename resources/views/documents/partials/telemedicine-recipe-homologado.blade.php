@php
    /**
     * Recipe de medicamentos en A4 horizontal, original + copia.
     *
     * @var array<string, mixed> $data
     * @var string $logoDataUri
     */
    $data = is_array($data ?? null) ? $data : [];
    $brandCyan = '#00ADEF';
    /** Logo opaco: un PNG con transparencia sale con fondo negro si DomPDF falla al separar su canal alfa. */
    $logoDataUri = trim((string) ($logoDataUri ?? '')) !== ''
        ? \App\Support\PdfOpaqueImage::fromDataUri((string) $logoDataUri)
        : \App\Support\PdfOpaqueImage::fromPath(public_path('image/logoNewPdf.png'));
    $val = static fn (mixed $value): string => filled($value) ? (string) $value : '—';
    $medications = \App\Support\Telemedicine\TelemedicineMedicationsPdfRows::normalize(
        is_array($data['medicationsArr'] ?? null) ? $data['medicationsArr'] : []
    );
    $copies = ['Original', 'Copia'];
    $stampDataUri = \App\Support\Telemedicine\TelemedicineDoctorStamp::dataUri($data['signature'] ?? null);
    /** Mismo sello que informes y órdenes (34 × 17 mm): tiene que caber en su banda. */
    $stampSize = \App\Support\Telemedicine\TelemedicineDoctorStamp::displaySize($stampDataUri, 128, 64);
    /**
     * Bandas inferiores reservadas en CADA página de la hoja horizontal (210 mm):
     * el pie (fijo a 12 mm del borde, con el folio debajo) y, encima, la firma.
     */
    $footerBandMm = 30;
    $signatureBandMm = 26;
    $signatureTopMm = 210 - $footerBandMm - $signatureBandMm;
    $doctorName = trim((string) ($data['doctor_name'] ?? ''));
    $coverageGroup = trim((string) ($data['coverage_group'] ?? ''));
    /** Servicio principal que eligió el médico en la consulta (mismo badge que el informe). */
    $serviceTypeLabel = \App\Support\Telemedicine\TelemedicineInformeServiceType::forInformeData(is_array($data ?? null) ? $data : []);
    $caseCode = \App\Support\Telemedicine\TelemedicineDocumentCaseCode::fromData(is_array($data ?? null) ? $data : []);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recipe de medicamentos</title>
    <style>
        @page {
            margin: 0;
            size: A4 landscape;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        /*
         * El margen inferior reserva la banda de la firma y la del pie en todas
         * las páginas. Ambos son fijos: se repiten en cada hoja fuera del flujo,
         * así que nunca pisan la lista ni abren una página para ellos solos.
         */
        body {
            margin: 10mm 12mm {{ $footerBandMm + $signatureBandMm }}mm 12mm;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7pt;
            line-height: 1.25;
            color: #374151;
        }
        /*
         * Original y copia comparten tablas fila por fila: DomPDF no parte una
         * celda entre páginas, así que dos columnas independientes se desfasaban
         * al paginar. Así cada página trae las dos mitades a la misma altura.
         */
        .copies,
        table.items {
            width: 100%;
            table-layout: fixed;
            position: relative;
            z-index: 1;
        }
        .copies { border-collapse: separate; border-spacing: 0; }
        .copies td { vertical-align: top; padding: 0; }
        .copies .copy-col { width: 48.5%; }
        td.gutter,
        th.gutter {
            width: 1.5%;
            padding: 0 !important;
            border: 0 !important;
            background: #ffffff !important;
        }
        td.gutter-line,
        th.gutter-line { border-left: 1px dashed #d1d5db !important; }
        .header-bar {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0 0 5px 0;
            border-bottom: 1.5px solid {{ $brandCyan }};
        }
        .header-bar td { vertical-align: top; padding: 0; }
        .header-bar .col-logo { width: 34%; }
        .header-bar .col-title { width: 66%; text-align: right; }
        .header-bar td.header-rule-space {
            height: 8px;
            line-height: 8px;
            font-size: 8px;
            padding: 0;
            color: #ffffff;
        }
        img.header-logo { width: 88px; height: auto; display: block; }
        .doc-title {
            font-size: 8.5pt;
            font-weight: bold;
            color: {{ $brandCyan }};
            margin: 0 0 1px 0;
        }
        .doc-sub { font-size: 6.5pt; color: #6b7280; margin: 0 0 1px 0; }
        .badge {
            display: inline-block;
            margin-top: 2px;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 6pt;
            font-weight: bold;
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        .section-title {
            font-size: 7pt;
            font-weight: bold;
            color: #0c4a6e;
            margin: 5px 0 2px 0;
            padding: 2px 5px 2px 6px;
            border-left: 2.5px solid {{ $brandCyan }};
            background: #f0fdff;
        }
        .grid { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .grid td { width: 50%; padding: 1px 6px 2px 0; vertical-align: top; }
        .label {
            font-size: 5.75pt;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .value { font-size: 7pt; color: #111827; font-weight: 600; }
        .value-muted { font-size: 6.75pt; color: #4b5563; }
        table.items {
            border-collapse: collapse;
            margin-top: 0;
        }
        table.items thead { display: table-header-group; }
        table.items tr { page-break-inside: avoid; }
        table.items th.section-head {
            background: #f0fdff;
            color: #0c4a6e;
            border: 0;
            border-left: 2.5px solid {{ $brandCyan }};
            font-size: 7pt;
            padding: 2px 5px 2px 6px;
        }
        table.items th.section-gap {
            background: #ffffff;
            border: 0;
            padding: 0;
            height: 2px;
            font-size: 1px;
            line-height: 1px;
        }
        table.items th {
            background-color: {{ $brandCyan }};
            color: #ffffff;
            padding: 3px 4px;
            text-align: left;
            font-size: 6.25pt;
            border: 1px solid #0090c7;
        }
        table.items td {
            border: 1px solid #e5e7eb;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 6.75pt;
            word-wrap: break-word;
        }
        table.items tr:nth-child(even) td { background: #fafafa; }
        table.items td.items-empty-cell { border: 0; padding: 0; background: #ffffff; }
        .items-empty {
            font-size: 6.75pt;
            color: #6b7280;
            margin: 3px 0 0 0;
            padding: 4px 6px;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
        }
        /* Un pie por mitad, alineado con su firma: al cortar la hoja, original y copia quedan completas. */
        .footer-fixed {
            position: fixed;
            bottom: 12mm;
            width: 128mm;
            text-align: center;
            padding-top: 4px;
            border-top: 1px solid #e5e7eb;
            font-size: 6pt;
            color: #9ca3af;
            background: #ffffff;
            z-index: 10;
        }
        .footer-fixed-original { left: 14mm; }
        .footer-fixed-copy { right: 14mm; left: auto; }
        .footer-brand { font-weight: bold; color: {{ $brandCyan }}; }
        .doctor-signature {
            position: fixed;
            width: 128mm;
            top: {{ $signatureTopMm }}mm;
            margin: 0;
            padding: 0;
            text-align: center;
            overflow: visible;
            z-index: 11;
        }
        .doctor-signature-original { left: 14mm; }
        .doctor-signature-copy { right: 14mm; left: auto; }
        .doctor-signature-image {
            display: block;
            margin: 0 auto 3px auto;
            border: 0;
        }
        .doctor-signature-name {
            font-size: 7.5pt;
            font-weight: bold;
            color: #111827;
            line-height: 1.2;
            margin: 0 0 1px 0;
        }
        .doctor-signature-meta {
            font-size: 6.75pt;
            color: #4b5563;
            line-height: 1.25;
            margin: 0;
        }
    </style>
</head>
<body>
<table class="copies">
    <tr>
        @foreach ($copies as $copyLabel)
            @php
                $isOriginal = $copyLabel === 'Original';
            @endphp
            @unless($isOriginal)
                <td class="gutter"></td>
                <td class="gutter gutter-line"></td>
            @endunless
            <td class="copy-col">
                <table class="header-bar">
                    <tr>
                        <td class="col-logo">
                            @if($logoDataUri !== '')
                                <img class="header-logo" src="{{ $logoDataUri }}" width="88" alt="Tu Doctor en Casa">
                            @else
                                <span style="font-weight:bold;color:{{ $brandCyan }};font-size:8pt;">Tu Doctor en Casa</span>
                            @endif
                        </td>
                        <td class="col-title">
                            {{-- El original va a la farmacia (solo medicamentos): es el récipe. La copia, con las indicaciones, es la del paciente. --}}
                            <p class="doc-title">{{ $isOriginal ? 'Récipe Médico' : 'Indicaciones Médicas' }}</p>
                            <p class="doc-sub">{{ \App\Support\Telemedicine\TelemedicineDocumentCaseCode::LABEL }}: <strong>{{ $val($caseCode) }}</strong></p>
                            <p class="doc-sub">Clave: <strong>{{ $val($data['code_reference'] ?? null) }}</strong></p>
                            <p class="doc-sub">Fecha: <strong>{{ $val($data['fecha'] ?? now()->format('d/m/Y')) }}</strong></p>
                            <span class="badge">{{ $serviceTypeLabel }}</span>
                            @if($coverageGroup !== '')
                                <span class="badge">{{ $coverageGroup }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="header-rule-space" colspan="2">&nbsp;</td>
                    </tr>
                </table>

                <div class="section-title">Datos del paciente</div>
                <table class="grid">
                    <tr>
                        <td>
                            <div class="label">Paciente</div>
                            <div class="value">{{ $val($data['name_patiente'] ?? ($data['name_patient'] ?? null)) }}</div>
                        </td>
                        <td>
                            <div class="label">Cédula</div>
                            <div class="value-muted">{{ $val($data['ci_patiente'] ?? ($data['ci_patient'] ?? null)) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="label">Edad</div>
                            <div class="value-muted">{{ \App\Support\PatientAge::forTelemedicinePatientId($data['telemedicine_patient_id'] ?? null, $data['age_patiente'] ?? ($data['age_patient'] ?? null)) }}</div>
                        </td>
                        <td>&nbsp;</td>
                    </tr>
                </table>
            </td>
        @endforeach
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th class="section-head" style="width:48.5%">Medicamentos indicados</th>
            <th class="gutter"></th>
            <th class="gutter gutter-line"></th>
            <th class="section-head" colspan="2" style="width:48.5%">Medicamentos indicados</th>
        </tr>
        <tr>
            <th class="section-gap">&nbsp;</th>
            <th class="gutter"></th>
            <th class="gutter gutter-line"></th>
            <th class="section-gap" colspan="2">&nbsp;</th>
        </tr>
        @if($medications !== [])
            <tr>
                <th>Medicamento</th>
                <th class="gutter"></th>
                <th class="gutter gutter-line"></th>
                <th style="width:23%">Medicamento</th>
                <th style="width:25.5%">Indicaciones</th>
            </tr>
        @endif
    </thead>
    <tbody>
        @if($medications === [])
            <tr>
                <td class="items-empty-cell"><p class="items-empty">Sin medicamentos indicados.</p></td>
                <td class="gutter"></td>
                <td class="gutter gutter-line"></td>
                <td class="items-empty-cell" colspan="2"><p class="items-empty">Sin medicamentos indicados.</p></td>
            </tr>
        @else
            @foreach ($medications as $item)
                <tr>
                    <td class="item-original">{{ $val(is_array($item) ? ($item['medicines'] ?? null) : $item) }}</td>
                    <td class="gutter"></td>
                    <td class="gutter gutter-line"></td>
                    <td class="item-copy">{{ $val(is_array($item) ? ($item['medicines'] ?? null) : $item) }}</td>
                    <td class="item-copy">{{ $val(is_array($item) ? ($item['indications'] ?? null) : null) }}</td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>
@foreach ($copies as $copyLabel)
    <div class="doctor-signature doctor-signature-{{ $copyLabel === 'Original' ? 'original' : 'copy' }}">
        @if($stampDataUri !== '')
            <img
                class="doctor-signature-image"
                src="{{ $stampDataUri }}"
                alt="Firma y sello del médico"
                @if(is_array($stampSize))
                    width="{{ $stampSize['width'] }}"
                    height="{{ $stampSize['height'] }}"
                @endif
            >
        @endif
        @if($doctorName !== '')
            <p class="doctor-signature-name">{{ $val($doctorName) }}</p>
        @endif
        <p class="doctor-signature-meta">MPPS: {{ $val($data['code_mpps'] ?? null) }}</p>
    </div>
@endforeach
@foreach ($copies as $copyLabel)
    <div class="footer-fixed footer-fixed-{{ $copyLabel === 'Original' ? 'original' : 'copy' }}">
        <strong>TU DOCTOR EN CASA, C. A. RIF.: J-50358368-1</strong><br>
        Dirección Comercial: Av. Francisco de Miranda, Centro Lido, Torre A, Piso 12, Oficina 124. El Rosal, Caracas.<br>
        Teléfono MediChat atención 24 horas: (0424) 213 21 12- Celular Coordinación de servicios: (0414) 901 03 52<br>
        Correo: 24H@tudrencasa.com IG: @tudrencasa WEB: https://tudrencasa.com/
    </div>
@endforeach
</body>
</html>
