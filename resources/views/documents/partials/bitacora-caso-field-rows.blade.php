@php
    $valueClass = $valueClass ?? 'value';
@endphp

@foreach ($fieldRows as $row)
    @if (($row['type'] ?? 'pair') === 'stack')
        @php
            $cell = $row['cells'][0] ?? ['label' => '', 'chunks' => []];
            $chunks = $cell['chunks'] ?? [];
        @endphp
        @foreach ($chunks as $chunkIndex => $chunk)
            <tr>
                <td>
                    @if ($chunkIndex === 0)
                        <div class="label">{{ $cell['label'] }}</div>
                    @endif
                    <div class="prose-box">{{ $chunk }}</div>
                </td>
            </tr>
        @endforeach
    @else
        <tr>
            <td>
                <table class="grid">
                    <tr>
                        @foreach ($row['cells'] as $cell)
                            <td>
                                <div class="label">{{ $cell['label'] }}</div>
                                <div class="{{ $valueClass }}">{{ $cell['value'] }}</div>
                            </td>
                        @endforeach
                        @if (count($row['cells']) === 1)
                            <td></td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>
    @endif
@endforeach
