<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Organigrama</title>

    <style>
        /*
         * Reglas de DomPDF que ya costaron romper documentos:
         * - DejaVu Sans: el separador «›» y el «·» solo existen en esta fuente.
         * - table-layout: fixed con anchos explícitos, o un título largo expulsa columnas.
         * - Una tarjeta por celda y una fila por grupo de tarjetas: DomPDF no parte una
         *   celda entre páginas, así que cada fila puede saltar de hoja por separado.
         * - El aire va en el padding de las celdas, no en divs al 100 %.
         */
        @page {
            margin: 26px 26px 34px 26px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #0f172a;
            margin: 0;
        }

        table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .brand {
            border-bottom: 2px solid #052f60;
            margin-bottom: 10px;
        }

        .brand td {
            vertical-align: middle;
            border: 0;
            padding: 0 0 10px 0;
        }

        .brand img {
            width: 140px;
            height: auto;
        }

        .brand .title {
            text-align: right;
        }

        .brand h1 {
            margin: 0;
            font-size: 15px;
            color: #052f60;
        }

        .brand p {
            margin: 3px 0 0;
            font-size: 8px;
            color: #64748b;
        }

        .counters td {
            border: 1px solid #e2e8f0;
            padding: 6px;
            text-align: center;
        }

        .counters .value {
            font-size: 13px;
            font-weight: bold;
            color: #052f60;
        }

        .counters .label {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
        }

        .sheet {
            margin-top: 14px;
        }

        .sheet tr {
            page-break-inside: avoid;
        }

        .sheet-path-label {
            padding: 0 0 4px 0;
            font-size: 7.5px;
            color: #64748b;
        }

        .sheet-head td {
            padding: 0 0 8px 0;
            border: 0;
        }

        .card {
            border: 1px solid #cbd5e1;
            border-top-width: 3px;
            padding: 6px 7px;
            vertical-align: top;
            background: #ffffff;
        }

        .card.head {
            background: #f1f5f9;
        }

        .card.vacant {
            border-style: dashed;
        }

        .gap {
            width: 10px;
            border: 0;
        }

        .row-gap td {
            height: 8px;
            border: 0;
            padding: 0;
        }

        .title {
            font-weight: bold;
            font-size: 9px;
        }

        .meta {
            display: block;
            margin-top: 2px;
            font-size: 7px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .person {
            display: block;
            margin-top: 3px;
        }

        .contact {
            display: block;
            font-size: 7px;
            color: #64748b;
        }

        .vacancy {
            display: block;
            margin-top: 3px;
            color: #b45309;
            font-weight: bold;
        }

        .reports {
            display: block;
            margin-top: 4px;
            font-size: 7px;
            color: #334155;
        }

        .index {
            margin-top: 16px;
        }

        .index thead {
            display: table-header-group;
        }

        .index th {
            background: #052f60;
            color: #ffffff;
            border: 1px solid #052f60;
            padding: 4px 6px;
            text-align: left;
            font-size: 7.5px;
            text-transform: uppercase;
        }

        .index tr {
            page-break-inside: avoid;
        }

        .index td {
            border: 1px solid #e2e8f0;
            padding: 3px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .section-title {
            margin: 18px 0 0;
            font-size: 11px;
            color: #052f60;
        }

        .note {
            margin-top: 8px;
            font-size: 7.5px;
            color: #64748b;
        }

        .page-number {
            position: fixed;
            bottom: -24px;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 7px;
            color: #94a3b8;
        }

        .page-number:after {
            content: "Página " counter(page);
        }
    </style>
</head>

<body>
    @php
        $toneFor = fn (?string $color): string => match ($color) {
            'teal' => '#0f766e',
            'green' => '#15803d',
            'amber' => '#b45309',
            'red' => '#b91c1c',
            'purple' => '#6d28d9',
            'slate' => '#475569',
            default => '#052f60',
        };

        $renderCard = function (array $node, int $reports, bool $isHead) use ($toneFor, $showContact): string {
            $html = '<span class="title">'.e($node['title']).'</span>';
            $meta = array_filter([
                $node['kind'] === 'STAFF' ? 'Staff' : null,
                $node['depto'] ?? null,
            ]);

            if ($meta !== []) {
                $html .= '<span class="meta">'.e(implode(' · ', $meta)).'</span>';
            }

            foreach ($node['occupants'] as $occupant) {
                $html .= '<span class="person">'.e($occupant['name']).($occupant['role'] === 'INTERINO' ? ' <em>(interino)</em>' : '').'</span>';

                if ($showContact) {
                    $contact = array_filter([$occupant['phone'], $occupant['email']]);

                    if ($contact !== []) {
                        $html .= '<span class="contact">'.e(implode(' · ', $contact)).'</span>';
                    }
                }
            }

            if ($node['vacancies'] > 0) {
                $html .= '<span class="vacancy">'.($node['vacancies'] === 1 ? 'Vacante' : $node['vacancies'].' plazas vacantes').'</span>';
            }

            if (! $isHead && $reports > 0) {
                $html .= '<span class="reports">'.$reports.' puesto(s) a cargo</span>';
            }

            return $html;
        };
    @endphp

    <div class="page-number"></div>

    <table class="brand">
        <tr>
            <td>
                <img src="{{ public_path('image/logoNewPdf.png') }}" alt="Tu Dr En Casa">
            </td>
            <td class="title">
                <h1>Organigrama · {{ $version['name'] }}</h1>
                <p>
                    {{ $version['status_label'] }}@if ($version['published_at']) desde {{ $version['published_at'] }}@endif
                    · Generado: {{ $generatedAt }}
                    @if ($rootTitle) · Desde «{{ $rootTitle }}»@endif
                </p>
            </td>
        </tr>
    </table>

    <table class="counters">
        <tr>
            <td>
                <div class="value">{{ $stats['positions'] }}</div>
                <div class="label">Puestos</div>
            </td>
            <td>
                <div class="value">{{ $stats['people'] }}</div>
                <div class="label">Personas</div>
            </td>
            <td>
                <div class="value">{{ $stats['vacancies'] }}</div>
                <div class="label">Plazas vacantes</div>
            </td>
            <td>
                <div class="value">{{ $stats['interims'] }}</div>
                <div class="label">Interinos</div>
            </td>
        </tr>
    </table>

    @forelse ($sheets as $sheet)
        <table class="sheet">
            {{-- Ruta y jefe en la misma fila: así nunca queda la ruta sola al pie de una hoja. --}}
            <tr class="sheet-head">
                <td colspan="{{ $cardsPerRow * 2 - 1 }}">
                    <div class="sheet-path-label">{{ implode(' › ', $sheet['path']) }}</div>
                    <table>
                        <tr>
                            <td style="width: 33%; border: 0;"></td>
                            <td class="card head {{ $sheet['head']['occupants'] === [] ? 'vacant' : '' }}" style="width: 34%; border-top-color: {{ $toneFor($sheet['head']['color']) }};">
                                {!! $renderCard($sheet['head'], 0, true) !!}
                            </td>
                            <td style="width: 33%; border: 0;"></td>
                        </tr>
                    </table>
                </td>
            </tr>
            @foreach ($sheet['rows'] as $row)
                <tr>
                    @for ($i = 0; $i < $cardsPerRow; $i++)
                        @if ($i > 0)
                            <td class="gap"></td>
                        @endif
                        @if (isset($row[$i]))
                            <td class="card {{ $row[$i]['node']['occupants'] === [] ? 'vacant' : '' }}" style="border-top-color: {{ $toneFor($row[$i]['node']['color']) }};">
                                {!! $renderCard($row[$i]['node'], $row[$i]['reports'], false) !!}
                            </td>
                        @else
                            <td style="border: 0;"></td>
                        @endif
                    @endfor
                </tr>
                <tr class="row-gap">
                    <td colspan="{{ $cardsPerRow * 2 - 1 }}"></td>
                </tr>
            @endforeach
        </table>
    @empty
        <p class="note">El organigrama no tiene puestos con subordinados para mostrar.</p>
    @endforelse

    @if ($truncated)
        <p class="note">El documento muestra las primeras láminas. Para ver un área completa, exporta desde ese puesto.</p>
    @endif

    <h2 class="section-title" style="page-break-before: always;">Índice completo</h2>

    <table class="index">
        <thead>
            <tr>
                <th style="width: 6%;">Nivel</th>
                <th style="width: 38%;">Puesto</th>
                <th style="width: 34%;">Ocupante</th>
                <th style="width: 22%;">Departamento</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($index as $row)
                <tr>
                    <td style="text-align: center; color: #64748b;">{{ $row['level'] }}</td>
                    <td style="padding-left: {{ 6 + (($row['level'] - 1) * 12) }}px; border-left: 3px solid {{ $toneFor($row['node']['color']) }};">
                        <strong>{{ $row['node']['title'] }}</strong>
                        @if ($row['node']['kind'] === 'STAFF')
                            <span class="meta">Staff</span>
                        @endif
                    </td>
                    <td>
                        @forelse ($row['node']['occupants'] as $occupant)
                            {{ $occupant['name'] }}@if ($occupant['role'] === 'INTERINO') (interino)@endif@if (! $loop->last)<br>@endif
                        @empty
                            <span style="color: #b45309;">Vacante</span>
                        @endforelse
                    </td>
                    <td>{{ $row['node']['depto'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="note">
        Cada lámina muestra un puesto y los que dependen directamente de él; la ruta arriba indica de quién depende.
        Las personas y sus datos salen de RRHH al momento de generar el documento.
    </p>
</body>

</html>
