@php
    /** @var list<array{state: string, city: string, name: string, clasificacion: string}> $reportRows */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    $brandCyan = '#00ADEF';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Red de Proveedores en Venezuela</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 10mm 8mm;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 6.5pt;
            line-height: 1.3;
            color: #1f2937;
        }
        .doc-title-wrap {
            text-align: center;
            margin-bottom: 10px;
        }
        .doc-heading {
            font-size: 12pt;
            font-weight: bold;
            color: #0c4a6e;
            margin: 0 0 8px 0;
            padding: 6px 10px;
            letter-spacing: 0.02em;
            border-bottom: 2px solid {{ $brandCyan }};
            display: inline-block;
            max-width: 100%;
        }
        .doc-lead {
            font-size: 6.5pt;
            line-height: 1.45;
            color: #334155;
            margin: 0 auto 8px auto;
            max-width: 100%;
            text-align: center;
        }
        .doc-footer {
            text-align: center;
            font-size: 6pt;
            color: #64748b;
            margin-top: 10mm;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }
        .logo-row {
            text-align: center;
            margin-bottom: 8px;
        }
        .logo-row img {
            max-height: 36px;
            width: auto;
        }
        table.report {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.report th,
        table.report td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            vertical-align: middle;
            word-wrap: break-word;
            text-align: center;
            font-size: 6.5pt;
        }
        table.report thead th {
            background: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
            font-size: 6.5pt;
            text-align: center;
            border-bottom: 2px solid {{ $brandCyan }};
        }
        .muted {
            color: #94a3b8;
            font-style: italic;
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
        <h1 class="doc-heading">Red de Proveedores en Venezuela</h1>
        <p class="doc-lead">
            Adicional a este listado, TU DR. GROUP cuenta con una red de médicos especialistas y proveedores de asistencia
            domiciliaria a nivel nacional. Para más información visite nuestro sitio web
            <strong>www.tudrgroup.com</strong> o <strong>www.tudrencasa.com</strong>
        </p>
    </div>

    <table class="report">
        <thead>
            <tr>
                <th style="width: 22%;">Estado</th>
                <th style="width: 22%;">Ciudad</th>
                <th style="width: 36%;">Nombre</th>
                <th style="width: 20%;">Clasificación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportRows as $row)
                <tr>
                    <td>{{ $row['state'] !== '' ? $row['state'] : '—' }}</td>
                    <td>{{ $row['city'] !== '' ? $row['city'] : '—' }}</td>
                    <td>{{ $row['name'] !== '' ? $row['name'] : '—' }}</td>
                    <td>{{ $row['clasificacion'] !== '' ? $row['clasificacion'] : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">No hay proveedores registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <footer class="doc-footer">
        Generado: {{ $generatedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · INTEGRACORP
    </footer>
</body>
</html>
