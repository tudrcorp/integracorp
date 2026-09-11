@php
    $entries = $entries ?? [];
@endphp

@if (count($entries) > 0)
    <table class="items">
        <thead>
            <tr>
                <th class="section-head" colspan="5">Informes AMD</th>
            </tr>
            <tr>
                <th style="width:16%">Fecha</th>
                <th style="width:16%">Consulta</th>
                <th style="width:20%">Médico</th>
                <th style="width:28%">Diagnóstico</th>
                <th style="width:20%">Documento</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry['created_at_label'] ?? '—' }}</td>
                    <td>{{ $entry['consultation_reference'] ?? '—' }}</td>
                    <td>{{ $entry['doctor_name'] ?? '—' }}</td>
                    <td>{{ $entry['diagnostic_impression'] ?? '—' }}</td>
                    <td>{{ $entry['document_name'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
