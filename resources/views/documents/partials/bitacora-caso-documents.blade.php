@php
    $entries = $entries ?? [];
@endphp

@if (count($entries) === 0)
    <table class="flow">
        <tr class="stick">
            <td>
                <div class="section-title">Documentos del caso</div>
            </td>
        </tr>
        <tr>
            <td>
                <p class="items-empty">No hay documentos consignados en este caso.</p>
            </td>
        </tr>
    </table>
@else
    <table class="items">
        <thead>
            <tr>
                <th class="section-head" colspan="5">Documentos del caso</th>
            </tr>
            <tr>
                <th style="width:18%">Categoría</th>
                <th style="width:18%">Referencia</th>
                <th style="width:32%">Archivo</th>
                <th style="width:18%">Fecha</th>
                <th style="width:14%">Disponible</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry['category'] ?? '—' }}</td>
                    <td>{{ $entry['reference'] ?? '—' }}</td>
                    <td>{{ $entry['document_name'] ?? '—' }}</td>
                    <td>{{ $entry['uploaded_at_label'] ?? '—' }}</td>
                    <td>{{ ! empty($entry['exists']) ? 'Sí' : 'No' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
