@php
    /** @var array{vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}|null $exam */
    $exam = is_array($exam ?? null) ? $exam : null;
@endphp

@if ($exam !== null && ($exam['vitals'] !== [] || $exam['systems'] !== []))
    {{--
        Una sola tabla con el título en el encabezado: DomPDF repite el <thead> en
        cada página, así que el título nunca queda solo al pie de una hoja y, si el
        examen cruza de página, la continuación sigue rotulada.
        Una fila por bloque: DomPDF no parte una celda entre páginas.
    --}}
    <table class="items exam-amd" style="table-layout: fixed; margin-top: 5px;">
        <thead>
            {{-- Fila medidora invisible: con layout fijo DomPDF toma los anchos de la primera fila (ignora <colgroup>), y la del título ocupa todo el ancho. --}}
            <tr>
                <th style="width: 24%; height: 0; padding: 0; border: 0; background: #ffffff; font-size: 0; line-height: 0;"></th>
                <th style="width: 76%; height: 0; padding: 0; border: 0; background: #ffffff; font-size: 0; line-height: 0;"></th>
            </tr>
            <tr>
                <th colspan="2" style="background: {{ $brandTint ?? '#f0fdff' }}; color: {{ $brandDeep ?? '#0c4a6e' }}; border: 0; border-left: 2.5px solid {{ $brandCyan ?? '#00ADEF' }}; font-size: 7.5pt; padding: 2px 5px 2px 6px;">Examen físico · Atención médica domiciliaria (AMD)</th>
            </tr>
        </thead>
        <tbody>
            @if ($exam['vitals'] !== [])
                @php($vitalWidth = round(100 / count($exam['vitals']), 2))
                <tr>
                    <td colspan="2" style="padding: 2px 0 0 0; border: 0; background: #ffffff;">
                        {{-- Tabla y no texto: .prose-box conserva los saltos de línea (pre-wrap). --}}
                        <table class="items" style="table-layout: fixed; margin-top: 0;">
                            <thead>
                                <tr>
                                    @foreach ($exam['vitals'] as $vital)
                                        <th style="width: {{ $vitalWidth }}%">{{ $vital['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    @foreach ($exam['vitals'] as $vital)
                                        <td class="center">{{ $vital['value'] }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            @endif

            @foreach ($exam['systems'] as $system)
                <tr>
                    <td style="width: 24%; font-weight: bold; vertical-align: top;">{{ $system['label'] }}:</td>
                    <td style="width: 76%; vertical-align: top; word-wrap: break-word;">{{ $system['text'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
