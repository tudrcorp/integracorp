@php
    /** @var array{columns: list<string>, rows: list<list<string>>, total: int, hidden: int, empty: string}|null $table */
@endphp
@if ($table === null || $table['rows'] === [])
    <div class="muted">{{ $table['empty'] ?? 'Sin registros.' }}</div>
@else
    <table class="grid">
        <thead>
            <tr>
                @foreach ($table['columns'] as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($table['rows'] as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($table['hidden'] > 0)
        <div class="note">Se muestran los {{ count($table['rows']) }} más recientes de {{ $table['total'] }}.</div>
    @endif
@endif
