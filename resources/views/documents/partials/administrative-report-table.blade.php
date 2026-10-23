@php
    /** @var array{headings: list<string>, rows: list<list<string>>, total: list<string>|null, numeric: list<int>, html?: list<int>}|null $table */
    $numeric = $table['numeric'] ?? [];
    $html = $table['html'] ?? [];
@endphp
@if (is_array($table) && $table['rows'] !== [])
    {{-- Puede seguir en la página siguiente; el encabezado se repite y nunca queda solo al pie. --}}
    <table @class(['adm-table', 'adm-table-compact' => $table['compact'] ?? false])>
        <thead>
            <tr>
                @foreach ($table['headings'] as $index => $heading)
                    <th @class(['adm-num' => in_array($index, $numeric, true)])>{{ mb_strtoupper($heading) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($table['rows'] as $row)
                <tr>
                    @foreach ($row as $index => $cell)
                        <td @class(['adm-num' => in_array($index, $numeric, true)])>
                            @if (in_array($index, $html, true)){!! $cell !!}@else{{ $cell }}@endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            @if (filled($table['total'] ?? null))
                <tr class="adm-total">
                    @foreach ($table['total'] as $index => $cell)
                        <td @class(['adm-num' => in_array($index, $numeric, true)])>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
@endif
