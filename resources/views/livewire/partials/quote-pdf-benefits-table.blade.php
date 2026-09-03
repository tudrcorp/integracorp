@php
    use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;

    /** @var list<array{column_key: string, header_label: string}> $benefitColumns */
    /** @var array<string, array{benefit_label: string, cells: array<string, mixed>}> $benefitRows */

    $benefitColumns = (array) ($benefitColumns ?? []);
    $benefitRows = (array) ($benefitRows ?? []);
    $headerBackground = $headerBackground ?? '#29ABE2';
    $benefitsHeading = $benefitsHeading ?? 'BENEFICIOS DEL PLAN';

    // Con muchas coberturas la tabla se aprieta en vez de recortar columnas:
    // ocultar una cobertura de una propuesta comercial sería peor que
    // imprimirla con tipografía menor.
    $isDense = (bool) ($isDense ?? false);
    $fontSize = $isDense ? '6pt' : '7pt';
    // El check se dibuja algo mayor que el texto para que pese lo mismo que un
    // monto en la lectura de la columna.
    $checkFontSize = $isDense ? '8pt' : '9pt';
    $cellPadding = $isDense ? '4px 3px' : '5px 4px';

    // El ancho de la primera columna se reparte para que las de cobertura
    // queden iguales entre sí y la tabla nunca exceda el ancho de la hoja.
    $columnCount = count($benefitColumns);
    $labelWidth = match (true) {
        $columnCount <= 2 => 60,
        $columnCount <= 4 => 46,
        default => 34,
    };
    $valueWidth = $columnCount > 0 ? (100 - $labelWidth) / $columnCount : 0;
@endphp

@if ($benefitColumns === [] || $benefitRows === [])
    {{-- Un plan sin estructura cargada no puede dibujar la matriz. Se dice, en
         vez de sacar una tabla vacía que el cliente no sabría interpretar. --}}
    <p style="font-size: 8pt; color: #6b7280;">
        Este plan todavía no tiene beneficios y coberturas configurados.
    </p>
@else
    {{-- table-layout fijo: un beneficio de nombre largo estiraría la tabla y
         empujaría las últimas columnas fuera de la hoja. --}}
    <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 6px;">
        <thead>
            <tr>
                <th
                    style="width: {{ $labelWidth }}%; font-weight: bold; color: white; font-size: {{ $fontSize }}; text-align: left; padding: {{ $cellPadding }}; background-color: {{ $headerBackground }}; border: 1px solid {{ $headerBackground }};">
                    {{ $benefitsHeading }}
                </th>
                @foreach ($benefitColumns as $benefitColumn)
                    <th
                        style="width: {{ $valueWidth }}%; font-weight: bold; color: white; font-size: {{ $fontSize }}; text-align: center; padding: {{ $cellPadding }}; background-color: {{ $headerBackground }}; border: 1px solid {{ $headerBackground }};">
                        {{ $benefitColumn['header_label'] ?? '—' }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($benefitRows as $benefitRow)
                <tr style="background-color: {{ $loop->even ? '#ffffff' : '#f4fafd' }};">
                    <td
                        style="font-size: {{ $fontSize }}; text-align: left; padding: {{ $cellPadding }}; border: 1px solid #d7e7f2; word-wrap: break-word;">
                        {{ $loop->iteration }}. {{ $benefitRow['benefit_label'] ?? '—' }}
                    </td>
                    @foreach ($benefitColumns as $benefitColumn)
                        @php
                            $columnKey = (string) ($benefitColumn['column_key'] ?? '');
                            $cell = (array) data_get($benefitRow, "cells.{$columnKey}", []);
                            $coverageAmount = $cell['coverage_amount'] ?? null;
                            $display = PlanGeneratorPreviewBuilder::benefitCellDisplay(
                                (bool) ($cell['is_selected'] ?? false),
                                $coverageAmount,
                            );
                        @endphp
                        <td
                            style="font-size: {{ $fontSize }}; text-align: center; padding: {{ $cellPadding }}; border: 1px solid #d7e7f2;">
                            {{-- Misma regla que el generador y la ficha del plan: con tope
                                 solo el monto, sin tope el check, y cero cuenta como sin tope. --}}
                            @if ($display === 'amount')
                                <span style="font-weight: bold; color: #052F60;">
                                    US$ {{ PlanGeneratorPreviewBuilder::formatCoverageAmount((float) $coverageAmount) }}
                                </span>
                            @elseif ($display === 'check')
                                {{-- La fuente va en línea y no heredada: esta página se
                                     embebe dentro de otro documento cuyo body usa Arial, y
                                     DomPDF imprime ✓ como «?» con cualquier fuente que no
                                     tenga el glifo. DejaVu Sans es la que lo trae. --}}
                                <span
                                    style="font-family: 'DejaVu Sans', sans-serif; font-weight: bold; font-size: {{ $checkFontSize }}; line-height: 1; color: {{ $headerBackground }};">✓</span>
                            @else
                                <span style="color: #9ca3af;">–</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
