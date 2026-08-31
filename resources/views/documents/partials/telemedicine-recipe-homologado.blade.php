@php
    /**
     * Recipe de medicamentos en A4 horizontal, original + copia.
     *
     * @var array<string, mixed> $data
     * @var string $logoDataUri
     */
    $data = is_array($data ?? null) ? $data : [];
    $brandCyan = '#00ADEF';
    $logoDataUri = $logoDataUri ?? '';
    if ($logoDataUri === '') {
        $logoPath = public_path('image/logoNewPdf.png');
        if (is_file($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }
    }
    $val = static fn (mixed $value): string => filled($value) ? (string) $value : '—';
    $medications = \App\Support\Telemedicine\TelemedicineMedicationsPdfRows::normalize(
        is_array($data['medicationsArr'] ?? null) ? $data['medicationsArr'] : []
    );
    $copies = ['Original', 'Copia'];
    $stampDataUri = \App\Support\Telemedicine\TelemedicineDoctorStamp::dataUri($data['signature'] ?? null);
    $stampSize = \App\Support\Telemedicine\TelemedicineDoctorStamp::displaySize($stampDataUri, 110, 110);
    $doctorName = trim((string) ($data['doctor_name'] ?? ''));
    $coverageGroup = trim((string) ($data['coverage_group'] ?? ''));
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
        body {
            margin: 10mm 12mm;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7pt;
            line-height: 1.25;
            color: #374151;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 20%;
            width: 60%;
            opacity: 0.045;
            z-index: 0;
            transform: translateY(-50%) rotate(-10deg);
        }
        .watermark img { width: 100%; height: auto; display: block; }
        .copies {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            position: relative;
            z-index: 1;
        }
        .copies .copy-col {
            width: 49%;
            vertical-align: top;
            padding: 0 8px 0 0;
        }
        .copies .copy-col:last-child {
            padding: 0 0 0 8px;
            border-left: 1px dashed #d1d5db;
        }
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
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            table-layout: fixed;
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
        .items-empty {
            font-size: 6.75pt;
            color: #6b7280;
            margin: 3px 0 0 0;
            padding: 4px 6px;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
        }
        .footer-fixed {
            position: fixed;
            bottom: 8mm;
            left: 12mm;
            right: 12mm;
            text-align: center;
            padding-top: 4px;
            border-top: 1px solid #e5e7eb;
            font-size: 6pt;
            color: #9ca3af;
            background: #ffffff;
            z-index: 10;
        }
        .footer-brand { font-weight: bold; color: {{ $brandCyan }}; }
        .panel { padding-bottom: 52mm; }
        .doctor-signature {
            position: fixed;
            top: 96mm;
            width: 128mm;
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
@if($logoDataUri !== '')
    <div class="watermark" aria-hidden="true"><img src="{{ $logoDataUri }}" alt=""></div>
@endif
<table class="copies">
    <tr>
        @foreach ($copies as $copyLabel)
            @php
                $isOriginal = $copyLabel === 'Original';
            @endphp
            <td class="copy-col">
                <div class="panel">
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
                                <p class="doc-title">Recipe de medicamentos</p>
                                <p class="doc-sub">Clave: <strong>{{ $val($data['code_reference'] ?? null) }}</strong></p>
                                <p class="doc-sub">Fecha: <strong>{{ $val($data['fecha'] ?? now()->format('d/m/Y')) }}</strong></p>
                                <span class="badge">{{ $copyLabel }}</span>
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
                                <div class="value-muted">{{ $val($data['age_patiente'] ?? ($data['age_patient'] ?? null)) }} años</div>
                            </td>
                            <td>
                                <div class="label">Tipo de servicio</div>
                                <div class="value-muted">Telemedicina</div>
                            </td>
                        </tr>
                    </table>

                    <div class="section-title">Medicamentos indicados</div>
                    @if($medications === [])
                        <p class="items-empty">Sin medicamentos indicados.</p>
                    @else
                        <table class="items items-{{ $isOriginal ? 'original' : 'copy' }}">
                            <thead>
                                <tr>
                                    @if($isOriginal)
                                        <th>Medicamento</th>
                                    @else
                                        <th style="width:48%">Medicamento</th>
                                        <th style="width:52%">Indicaciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($medications as $item)
                                    <tr>
                                        <td>{{ $val(is_array($item) ? ($item['medicines'] ?? null) : $item) }}</td>
                                        @unless($isOriginal)
                                            <td>{{ $val(is_array($item) ? ($item['indications'] ?? null) : null) }}</td>
                                        @endunless
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </td>
        @endforeach
    </tr>
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
<div class="footer-fixed">
    Documento generado por el <span class="footer-brand">departamento de telemedicina de Tu Doctor en Casa</span>.
    Uso clínico; original y copia en la misma hoja.
</div>
</body>
</html>
