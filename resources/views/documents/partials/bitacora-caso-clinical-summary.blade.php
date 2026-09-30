@php
    /** @var list<array{stage: string, tone: string, date: string, doctor: string, reference: string, fields: array<string, string>, truncated: bool}> $entries */
    $entries = $entries ?? [];
    $stageColors = [
        'initial' => '#0284c7',
        'follow_up' => '#d97706',
        'discharge' => '#059669',
    ];
@endphp

<table class="flow">
    <tr class="stick">
        <td>
            <div class="section-title">Resumen clínico del caso</div>
        </td>
    </tr>
    @if ($entries === [])
        <tr>
            <td>
                <p class="items-empty">Este caso aún no tiene consultas registradas.</p>
            </td>
        </tr>
    @else
        {{-- Una fila por etapa: DomPDF no parte una celda entre páginas. --}}
        @foreach ($entries as $entry)
            @php($color = $stageColors[$entry['tone']] ?? $stageColors['follow_up'])
            <tr class="stick">
                <td style="padding: 4px 0 1px 6px; border-left: 2.5px solid {{ $color }};">
                    <span style="font-size: 7.5pt; font-weight: bold; color: {{ $color }};">{{ $entry['stage'] }}</span>
                    <span class="value-muted">
                        · {{ $entry['date'] }}@if ($entry['doctor'] !== '—') · {{ $entry['doctor'] }}@endif · {{ $entry['reference'] }}
                    </span>
                </td>
            </tr>
            <tr>
                <td style="padding: 1px 0 5px 6px; border-left: 2.5px solid {{ $color }};">
                    @if ($entry['fields'] === [])
                        <span class="value-muted">Sin notas clínicas en este registro.</span>
                    @else
                        <table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
                            @foreach ($entry['fields'] as $label => $value)
                                <tr>
                                    <td style="width: 24%; padding: 1px 6px 1px 0; vertical-align: top;">
                                        <span class="label">{{ $label }}</span>
                                    </td>
                                    <td style="width: 76%; padding: 1px 0; vertical-align: top; word-wrap: break-word;">
                                        <span class="value-muted">{{ $value }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                    @if ($entry['truncated'])
                        <div style="font-size: 6.25pt; color: #9ca3af; margin-top: 1px;">
                            Texto resumido. La nota completa está en «Consultas y notas médicas».
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    @endif
</table>
