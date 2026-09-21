@php
    $entries = $entries ?? [];
@endphp

@if (count($entries) > 0)
    <table class="flow">
        <tr class="stick">
            <td>
                <div class="section-title">{{ $title }}</div>
            </td>
        </tr>
        @foreach ($entries as $index => $entry)
            @php
                $fieldRows = \App\Support\Operations\TelemedicineCaseBitacora::pdfFieldRows($entry ?? []);
            @endphp
            <tr class="stick">
                <td>
                    <div class="item-kicker">{{ $title }} {{ $index + 1 }}</div>
                </td>
            </tr>
            @if ($fieldRows === [])
                <tr>
                    <td>
                        <p class="items-empty">Sin detalle en este registro.</p>
                    </td>
                </tr>
            @else
                @include('documents.partials.bitacora-caso-field-rows', ['fieldRows' => $fieldRows, 'valueClass' => 'value-muted'])
            @endif
        @endforeach
    </table>
@endif
