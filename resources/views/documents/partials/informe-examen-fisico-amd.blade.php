@php
    /** @var array{vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}|null $exam */
    $exam = is_array($exam ?? null) ? $exam : null;
@endphp

@if ($exam !== null && ($exam['vitals'] !== [] || $exam['systems'] !== []))
    <div class="section-title section-title--block">Examen físico</div>

    @if ($exam['vitals'] !== [])
        @php($vitalWidth = round(100 / count($exam['vitals']), 2))
        {{-- Tabla y no texto: .prose-box conserva los saltos de línea (pre-wrap). --}}
        <table class="items" style="table-layout: fixed;">
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
    @endif

    @if ($exam['systems'] !== [])
        {{-- Una fila por sistema: DomPDF no parte una celda entre páginas. --}}
        <table class="items" style="table-layout: fixed;">
            <tbody>
                @foreach ($exam['systems'] as $system)
                    <tr>
                        <td style="width: 24%; font-weight: bold; vertical-align: top;">{{ $system['label'] }}:</td>
                        <td style="width: 76%; vertical-align: top; word-wrap: break-word;">{{ $system['text'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif
