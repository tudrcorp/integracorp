@php
    $fieldRows = \App\Support\Operations\TelemedicineCaseBitacora::pdfFieldRows($map ?? []);
@endphp

<table class="flow">
    <tr class="stick">
        <td>
            <div class="section-title">{{ $title }}</div>
        </td>
    </tr>
    @if ($fieldRows === [])
        <tr>
            <td>
                <p class="items-empty">Sin información en esta sección.</p>
            </td>
        </tr>
    @else
        @include('documents.partials.bitacora-caso-field-rows', ['fieldRows' => $fieldRows, 'valueClass' => 'value'])
    @endif
</table>
