@php
    use App\Support\QuotePdfCoverageTable;
@endphp

<table style="width: 100%; font-type: Helvetica, sans-serif;">
    <tr style="background-color: {{ $headerBackground }}; font-size: 10px;">
        <th style="font-weight: bold; color: white;">RANGO DE EDAD</th>
        <th style="font-weight: bold; color: white;">POBLACIÓN</th>
        @foreach ($coverageColumns as $coverage)
            <th style="font-weight: bold; color: white;">
                TARIFA ANUAL<br>US$ {{ QuotePdfCoverageTable::formatLabel($coverage) }}
            </th>
        @endforeach
    </tr>
    @foreach ($tableRows as $row)
        <tr>
            <td style="font-weight: bold; font-size: 10px;">{{ $row['age_range'] }} años</td>
            <td style="font-weight: bold; font-size: 10px;">
                @if (($populationOnlyIfMultiple ?? false) && $row['total_persons'] <= 1)
                @else
                    {{ $row['total_persons'] }} Persona(s)
                @endif
            </td>
            @foreach ($coverageColumns as $coverage)
                @php
                    $key = QuotePdfCoverageTable::coverageKey($coverage);
                    $amount = $row['amounts'][$key] ?? null;
                @endphp
                <td style="font-weight: bold; font-size: 10px;">
                    @if ($amount !== null)
                        {{ $amount }} US$
                    @else
                        -
                    @endif
                </td>
            @endforeach
        </tr>
    @endforeach
</table>

<table style="width: 100%; border-collapse: collapse; font-type: Helvetica, sans-serif;">
    <tbody>
        <tr>
            <td colspan="{{ $labelColspan }}"
                style="font-weight: bold; color: white; font-size: 10px; width: 88px; background-color: {{ $headerBackground }}">
                TARIFA GRUPAL ANUAL
            </td>
            @foreach ($coverageColumns as $coverage)
                @php
                    $key = QuotePdfCoverageTable::coverageKey($coverage);
                    $total = $totals[$key] ?? null;
                @endphp
                <td style="text-align: center; font-weight: bold; font-size: 10px;">
                    @if ($total !== null)
                        {{ round($total, 2) }} US$
                    @else
                        -
                    @endif
                </td>
            @endforeach
        </tr>
        <tr>
            <td colspan="{{ $labelColspan }}"
                style="font-weight: bold; color: white; font-size: 10px; width: 88px; background-color: {{ $headerBackground }}">
                TARIFA GRUPAL SEMESTRAL
            </td>
            @foreach ($coverageColumns as $coverage)
                @php
                    $key = QuotePdfCoverageTable::coverageKey($coverage);
                    $total = $totals[$key] ?? null;
                @endphp
                <td style="text-align: center; font-weight: bold; font-size: 10px;">
                    @if ($total !== null)
                        {{ round($total / 2) }} US$
                    @else
                        -
                    @endif
                </td>
            @endforeach
        </tr>
        <tr>
            <td colspan="{{ $labelColspan }}"
                style="font-weight: bold; color: white; font-size: 10px; width: 88px; background-color: {{ $headerBackground }}">
                TARIFA GRUPAL TRIMESTRAL
            </td>
            @foreach ($coverageColumns as $coverage)
                @php
                    $key = QuotePdfCoverageTable::coverageKey($coverage);
                    $total = $totals[$key] ?? null;
                @endphp
                <td style="text-align: center; font-weight: bold; font-size: 10px;">
                    @if ($total !== null)
                        {{ round($total / 4) }} US$
                    @else
                        -
                    @endif
                </td>
            @endforeach
        </tr>
    </tbody>
</table>
