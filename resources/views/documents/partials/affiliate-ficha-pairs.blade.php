@php
    /** @var list<array{0: string, 1: string}> $pairs */
    $chunks = array_chunk($pairs, 2);
@endphp
<table class="pairs">
    @foreach ($chunks as $chunk)
        <tr>
            <td class="label">{{ $chunk[0][0] }}</td>
            <td class="value">{{ $chunk[0][1] }}</td>
            <td class="label">{{ $chunk[1][0] ?? '' }}</td>
            <td class="value">{{ $chunk[1][1] ?? '' }}</td>
        </tr>
    @endforeach
</table>
