@php
    /** @var list<array{date: string, doctor: string, reference: string, vitals: list<array{label: string, value: string}>, systems: list<array{label: string, text: string, is_default: bool}>}> $entries */
    $entries = $entries ?? [];
@endphp

@if ($entries !== [])
    <table class="flow">
        <tr class="stick">
            <td>
                <div class="section-title">Examen físico AMD</div>
            </td>
        </tr>
        @foreach ($entries as $exam)
            <tr class="stick">
                <td>
                    <div class="item-kicker">{{ $exam['date'] }}@if ($exam['doctor'] !== '—') · {{ $exam['doctor'] }}@endif · {{ $exam['reference'] }}</div>
                </td>
            </tr>
            @if ($exam['vitals'] !== [])
                <tr>
                    <td>
                        <div class="prose-box">@foreach ($exam['vitals'] as $vital)<strong>{{ mb_strtoupper($vital['label']) }}:</strong> {{ $vital['value'] }}@if (! $loop->last) &nbsp;·&nbsp; @endif @endforeach</div>
                    </td>
                </tr>
            @endif
            {{-- Una fila por sistema: DomPDF no parte una celda entre páginas. --}}
            @foreach ($exam['systems'] as $system)
                <tr>
                    <td style="padding: 1px 0 2px 0;">
                        <span class="value" style="font-size: 7.25pt;">{{ $system['label'] }}:</span>
                        <span class="value-muted">{{ $system['text'] }}</span>
                    </td>
                </tr>
            @endforeach
        @endforeach
    </table>
@endif
